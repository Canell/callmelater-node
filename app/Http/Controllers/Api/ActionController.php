<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\RetryNotAllowedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateActionRequest;
use App\Http\Resources\ActionCollection;
use App\Http\Resources\ActionResource;
use App\Models\ScheduledAction;
use App\Services\ActionService;
use App\Services\HttpRequestService;
use App\Services\ManualRetryService;
use App\Services\QuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActionController extends Controller
{
    public function __construct(
        private ActionService $actionService,
        private HttpRequestService $httpRequestService
    ) {}

    public function index(Request $request): ActionCollection
    {
        $user = $request->user();
        $historyDays = $user->getPlanLimit('history_days', 365);
        $cutoffDate = now()->subDays($historyDays);

        $query = ScheduledAction::query()
            ->where('account_id', $user->account_id)
            ->where(function ($q) use ($cutoffDate) {
                // Show all non-terminal actions (pending, scheduled, awaiting response)
                $q->whereNotIn('resolution_status', [
                    ScheduledAction::STATUS_EXECUTED,
                    ScheduledAction::STATUS_FAILED,
                    ScheduledAction::STATUS_CANCELLED,
                    ScheduledAction::STATUS_EXPIRED,
                ])
                // Or show terminal actions within history window
                    ->orWhere('created_at', '>=', $cutoffDate);
            })
            ->orderBy('created_at', 'desc');

        // Search by name or description
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('resolution_status', $request->input('status'));
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        $actions = $query->paginate($request->input('per_page', 25));

        return new ActionCollection($actions);
    }

    public function store(CreateActionRequest $request): ActionResource
    {
        $action = $this->actionService->create(
            $request->user(),
            $request->validated()
        );

        return new ActionResource($action);
    }

    public function show(Request $request, string $id): ActionResource|JsonResponse
    {
        $user = $request->user();
        $action = ScheduledAction::with(['deliveryAttempts', 'reminderEvents', 'recipients', 'executionCycles.triggeredByUser'])
            ->where('account_id', $user->account_id)
            ->find($id);

        if (! $action) {
            return response()->json(['message' => 'Action not found'], 404);
        }

        return new ActionResource($action);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $action = ScheduledAction::where('account_id', $user->account_id)->find($id);

        if (! $action) {
            return response()->json(['message' => 'Action not found'], 404);
        }

        try {
            $this->actionService->cancel($action);

            return response()->json(['message' => 'Action cancelled']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Cancel an action by idempotency key.
     *
     * DELETE /v1/actions
     * { "idempotency_key": "rotate-keys-2025-03" }
     *
     * 200 → cancelled (or already cancelled)
     * 404 → not found
     * 409 → already executed
     */
    public function destroyByIdempotencyKey(Request $request): JsonResponse
    {
        $request->validate([
            'idempotency_key' => 'required|string|max:255',
        ]);

        $action = ScheduledAction::query()
            ->forAccount($request->user()->account_id)
            ->where('idempotency_key', $request->input('idempotency_key'))
            ->first();

        if (! $action) {
            return response()->json(['message' => 'Action not found'], 404);
        }

        // Already cancelled - return success (idempotent)
        if ($action->resolution_status === ScheduledAction::STATUS_CANCELLED) {
            return response()->json([
                'message' => 'Action already cancelled',
                'id' => $action->id,
            ]);
        }

        // Already executed - return conflict
        if ($action->resolution_status === ScheduledAction::STATUS_EXECUTED) {
            return response()->json([
                'message' => 'Action already executed',
                'id' => $action->id,
            ], 409);
        }

        try {
            $this->actionService->cancel($action);

            return response()->json([
                'message' => 'Action cancelled',
                'id' => $action->id,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }

    /**
     * Test an HTTP request without creating an action.
     *
     * POST /v1/actions/test
     */
    public function test(Request $request): JsonResponse
    {
        $request->validate([
            'url' => 'required|url:http,https',
            'method' => 'nullable|string|in:GET,POST,PUT,PATCH,DELETE',
            'headers' => 'nullable|array',
            'body' => 'nullable|array',
        ]);

        $config = [
            'url' => $request->input('url'),
            'method' => $request->input('method', 'POST'),
            'headers' => $request->input('headers', []),
            'body' => $request->input('body'),
            'timeout' => 10, // Shorter timeout for tests
        ];

        $result = $this->httpRequestService->execute($config);

        return response()->json($result);
    }

    /**
     * Manually retry a failed action.
     *
     * POST /v1/actions/{id}/retry
     */
    public function retry(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $action = ScheduledAction::where('account_id', $user->account_id)->find($id);

        if (! $action) {
            return response()->json(['message' => 'Action not found'], 404);
        }

        $retryService = app(ManualRetryService::class);
        $check = $retryService->canRetry($action, $user);

        if (! $check['allowed']) {
            return response()->json([
                'message' => 'Retry not allowed',
                'reasons' => $check['reasons'],
            ], 422);
        }

        try {
            $cycle = $retryService->retry($action, $user);

            return response()->json([
                'message' => 'Action retry initiated',
                'execution_cycle_id' => $cycle->id,
                'action' => new ActionResource($action->fresh(['deliveryAttempts', 'reminderEvents', 'recipients', 'executionCycles.triggeredByUser'])),
            ]);
        } catch (RetryNotAllowedException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get current quota usage.
     *
     * GET /v1/quota
     */
    public function quota(Request $request): JsonResponse
    {
        $user = $request->user();
        $account = $user->account;

        if (! $account) {
            return response()->json(['message' => 'No account found'], 404);
        }

        $quotaService = app(QuotaService::class);
        $usage = $quotaService->getUsage($account);

        return response()->json([
            'period' => [
                'year' => now()->year,
                'month' => now()->month,
                'month_name' => now()->format('F Y'),
            ],
            'actions' => $usage['actions'],
            'sms' => $usage['sms'],
            'plan' => $account->getPlan(),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateActionRequest;
use App\Http\Resources\ActionCollection;
use App\Http\Resources\ActionResource;
use App\Models\ScheduledAction;
use App\Services\ActionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActionController extends Controller
{
    public function __construct(
        private ActionService $actionService
    ) {}

    public function index(Request $request): ActionCollection
    {
        $query = ScheduledAction::query()
            ->forUser($request->user()->id)
            ->orderBy('created_at', 'desc');

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
        $action = ScheduledAction::query()
            ->forUser($request->user()->id)
            ->with(['deliveryAttempts', 'reminderEvents', 'recipients'])
            ->find($id);

        if (! $action) {
            return response()->json(['message' => 'Action not found'], 404);
        }

        return new ActionResource($action);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $action = ScheduledAction::query()
            ->forUser($request->user()->id)
            ->find($id);

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
            ->forUser($request->user()->id)
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
}

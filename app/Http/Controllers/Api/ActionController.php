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
}

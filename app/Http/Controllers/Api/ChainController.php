<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateChainRequest;
use App\Http\Resources\ChainResource;
use App\Models\ActionChain;
use App\Services\ChainService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ChainController extends Controller
{
    public function __construct(
        private ChainService $chainService
    ) {}

    /**
     * List all chains for the authenticated user's account.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $query = ActionChain::forAccount($user->account_id)
            ->orderByDesc('created_at');

        // Filter by status
        if ($request->has('status')) {
            $statuses = explode(',', $request->input('status'));
            $query->whereIn('status', $statuses);
        }

        $chains = $query->paginate($request->input('per_page', 20));

        return ChainResource::collection($chains);
    }

    /**
     * Create a new action chain.
     */
    public function store(CreateChainRequest $request): JsonResponse
    {
        $user = $request->user();

        $chain = $this->chainService->createChain(
            $user->account,
            $request->validated(),
            $user
        );

        return response()->json([
            'data' => new ChainResource($chain),
        ], 201);
    }

    /**
     * Get a single chain by ID.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $chain = ActionChain::forAccount($user->account_id)
            ->with('actions')
            ->findOrFail($id);

        return response()->json([
            'data' => new ChainResource($chain),
            'actions' => $chain->actions->map(fn ($action) => [
                'id' => $action->id,
                'name' => $action->name,
                'chain_step' => $action->chain_step,
                'status' => $action->resolution_status,
                'execute_at' => $action->execute_at_utc?->toIso8601String(),
                'executed_at' => $action->executed_at_utc?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Cancel a chain and all its pending actions.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $chain = ActionChain::forAccount($user->account_id)->findOrFail($id);

        if ($chain->isTerminal()) {
            return response()->json([
                'message' => 'Chain is already in a terminal state.',
                'status' => $chain->status,
            ], 400);
        }

        $this->chainService->cancelChain($chain);

        return response()->json([
            'message' => 'Chain cancelled successfully.',
            'data' => new ChainResource($chain->fresh()),
        ]);
    }
}

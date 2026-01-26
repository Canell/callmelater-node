<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateTemplateRequest;
use App\Http\Requests\Api\UpdateTemplateRequest;
use App\Http\Resources\TemplateResource;
use App\Models\ActionTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TemplateController extends Controller
{
    /**
     * List all templates for the authenticated user's account.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $templates = ActionTemplate::query()
            ->where('account_id', $request->user()->account_id)
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        return TemplateResource::collection($templates);
    }

    /**
     * Create a new template.
     */
    public function store(CreateTemplateRequest $request): JsonResponse
    {
        $template = ActionTemplate::create([
            'account_id' => $request->user()->account_id,
            'created_by_user_id' => $request->user()->id,
            ...$request->validated(),
        ]);

        return response()->json([
            'data' => new TemplateResource($template),
        ], 201);
    }

    /**
     * Get a specific template.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $template = ActionTemplate::where('account_id', $request->user()->account_id)
            ->findOrFail($id);

        return response()->json([
            'data' => new TemplateResource($template),
        ]);
    }

    /**
     * Update a template.
     */
    public function update(UpdateTemplateRequest $request, string $id): JsonResponse
    {
        $template = ActionTemplate::where('account_id', $request->user()->account_id)
            ->findOrFail($id);

        $template->update($request->validated());

        return response()->json([
            'data' => new TemplateResource($template->fresh()),
        ]);
    }

    /**
     * Delete a template.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $template = ActionTemplate::where('account_id', $request->user()->account_id)
            ->findOrFail($id);

        $template->delete();

        return response()->json(null, 204);
    }

    /**
     * Regenerate the trigger token for a template.
     */
    public function regenerateToken(Request $request, string $id): JsonResponse
    {
        $template = ActionTemplate::where('account_id', $request->user()->account_id)
            ->findOrFail($id);

        $template->regenerateToken();

        return response()->json([
            'message' => 'Trigger token regenerated successfully.',
            'data' => new TemplateResource($template->fresh()),
        ]);
    }

    /**
     * Toggle template active status.
     */
    public function toggleActive(Request $request, string $id): JsonResponse
    {
        $template = ActionTemplate::where('account_id', $request->user()->account_id)
            ->findOrFail($id);

        $template->update([
            'is_active' => ! $template->is_active,
        ]);

        return response()->json([
            'data' => new TemplateResource($template->fresh()),
            'message' => $template->is_active ? 'Template activated.' : 'Template deactivated.',
        ]);
    }

    /**
     * Get template limits for the current user's plan.
     */
    public function limits(Request $request): JsonResponse
    {
        $user = $request->user();
        $maxTemplates = $user->getPlanLimit('max_templates');
        $currentCount = ActionTemplate::where('account_id', $user->account_id)->count();

        return response()->json([
            'current' => $currentCount,
            'max' => $maxTemplates,
            'remaining' => max(0, $maxTemplates - $currentCount),
            'plan' => $user->account?->getPlan() ?? 'free',
        ]);
    }
}

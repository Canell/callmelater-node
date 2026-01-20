<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\StatusEvent;
use App\Models\SystemComponent;
use App\Services\StatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatusController extends Controller
{
    public function __construct(
        private StatusService $statusService
    ) {}

    /**
     * Get all components with their current status.
     */
    public function components(): JsonResponse
    {
        $components = SystemComponent::ordered()->get();

        return response()->json([
            'data' => $components->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'description' => $c->description,
                'current_status' => $c->current_status,
                'status_label' => $c->status_label,
                'is_visible' => $c->is_visible,
                'display_order' => $c->display_order,
                'updated_at' => $c->updated_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Update a component's status.
     */
    public function updateComponent(Request $request, SystemComponent $component): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:operational,degraded,outage',
            'message' => 'nullable|string|max:255',
            'is_visible' => 'sometimes|boolean',
            'display_order' => 'sometimes|integer|min:0',
        ]);

        // Update visibility/order if provided
        if (isset($validated['is_visible'])) {
            $component->update(['is_visible' => $validated['is_visible']]);
        }
        if (isset($validated['display_order'])) {
            $component->update(['display_order' => $validated['display_order']]);
        }

        // Update status if provided
        if (isset($validated['status'])) {
            $this->statusService->updateComponentStatus(
                $component,
                $validated['status'],
                $validated['message'] ?? null,
                $request->user()
            );
        }

        return response()->json([
            'message' => 'Component updated',
            'data' => [
                'id' => $component->id,
                'name' => $component->name,
                'current_status' => $component->fresh()->current_status,
            ],
        ]);
    }

    /**
     * Get all incidents.
     */
    public function incidents(Request $request): JsonResponse
    {
        $query = Incident::with('components:id,name,slug')
            ->orderByDesc('started_at');

        if ($request->boolean('active_only')) {
            $query->active();
        }

        $incidents = $query->paginate(20);

        return response()->json([
            'data' => $incidents->map(fn ($i) => [
                'id' => $i->id,
                'title' => $i->title,
                'impact' => $i->impact,
                'impact_label' => $i->impact_label,
                'status' => $i->status,
                'status_label' => $i->status_label,
                'summary' => $i->summary,
                'started_at' => $i->started_at->toIso8601String(),
                'resolved_at' => $i->resolved_at?->toIso8601String(),
                'duration' => $i->duration,
                'affected_components' => $i->components->pluck('name')->toArray(),
                'component_ids' => $i->components->pluck('id')->toArray(),
            ]),
            'meta' => [
                'current_page' => $incidents->currentPage(),
                'last_page' => $incidents->lastPage(),
                'total' => $incidents->total(),
            ],
        ]);
    }

    /**
     * Create a new incident.
     */
    public function createIncident(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'impact' => 'required|in:minor,major,critical',
            'summary' => 'nullable|string|max:1000',
            'component_ids' => 'required|array|min:1',
            'component_ids.*' => 'exists:system_components,id',
        ]);

        $incident = $this->statusService->createIncident(
            $validated['title'],
            $validated['impact'],
            $validated['component_ids'],
            $validated['summary'] ?? null,
            $request->user()
        );

        return response()->json([
            'message' => 'Incident created',
            'data' => [
                'id' => $incident->id,
                'title' => $incident->title,
                'status' => $incident->status,
            ],
        ], 201);
    }

    /**
     * Update an incident.
     */
    public function updateIncident(Request $request, Incident $incident): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:investigating,identified,monitoring,resolved',
            'summary' => 'nullable|string|max:1000',
            'title' => 'sometimes|string|max:255',
            'impact' => 'sometimes|in:minor,major,critical',
        ]);

        // Update basic fields
        if (isset($validated['title'])) {
            $incident->update(['title' => $validated['title']]);
        }
        if (isset($validated['impact'])) {
            $incident->update(['impact' => $validated['impact']]);
        }

        // Update status (this handles component status restoration on resolve)
        if (isset($validated['status'])) {
            $this->statusService->updateIncidentStatus(
                $incident,
                $validated['status'],
                $validated['summary'] ?? null,
                $request->user()
            );
        } elseif (isset($validated['summary'])) {
            $incident->update(['summary' => $validated['summary']]);
        }

        return response()->json([
            'message' => 'Incident updated',
            'data' => [
                'id' => $incident->id,
                'title' => $incident->title,
                'status' => $incident->fresh()->status,
            ],
        ]);
    }

    /**
     * Get status event history for a component.
     */
    public function componentHistory(SystemComponent $component): JsonResponse
    {
        $events = $component->statusEvents()
            ->with('creator:id,name')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $events->map(function (StatusEvent $e): array {
                /** @var \App\Models\User|null $creator */
                $creator = $e->creator;

                return [
                    'id' => $e->id,
                    'status' => $e->status,
                    'status_label' => $e->status_label,
                    'message' => $e->message,
                    'created_by' => $creator?->name,
                    'created_at' => $e->created_at->toIso8601String(),
                ];
            }),
        ]);
    }
}

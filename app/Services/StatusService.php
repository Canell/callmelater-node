<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\StatusEvent;
use App\Models\SystemComponent;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StatusService
{
    private const CACHE_KEY = 'public_status';
    private const CACHE_TTL = 60; // 1 minute

    /**
     * Get the public status data (cached).
     */
    public function getPublicStatus(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return $this->buildPublicStatus();
        });
    }

    /**
     * Build the public status response.
     */
    private function buildPublicStatus(): array
    {
        $components = SystemComponent::visible()
            ->ordered()
            ->get()
            ->map(fn ($c) => [
                'name' => $c->name,
                'slug' => $c->slug,
                'description' => $c->description,
                'status' => $c->current_status,
                'status_label' => $c->status_label,
                'updated_at' => $c->updated_at->toIso8601String(),
            ]);

        $activeIncidents = Incident::active()
            ->with('components:id,name,slug')
            ->orderByDesc('started_at')
            ->get()
            ->map(fn ($i) => $this->formatIncident($i));

        $recentIncidents = Incident::resolved()
            ->recent(90)
            ->with('components:id,name,slug')
            ->orderByDesc('started_at')
            ->limit(10)
            ->get()
            ->map(fn ($i) => $this->formatIncident($i));

        // Calculate overall status
        $overallStatus = $this->calculateOverallStatus($components);

        return [
            'overall_status' => $overallStatus,
            'components' => $components->toArray(),
            'active_incidents' => $activeIncidents->toArray(),
            'recent_incidents' => $recentIncidents->toArray(),
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Format an incident for the public API.
     */
    private function formatIncident(Incident $incident): array
    {
        return [
            'id' => $incident->id,
            'title' => $incident->title,
            'impact' => $incident->impact,
            'impact_label' => $incident->impact_label,
            'status' => $incident->status,
            'status_label' => $incident->status_label,
            'summary' => $incident->summary,
            'started_at' => $incident->started_at->toIso8601String(),
            'resolved_at' => $incident->resolved_at?->toIso8601String(),
            'duration' => $incident->duration,
            'affected_components' => $incident->components->map(fn (SystemComponent $c) => [
                'name' => $c->name,
                'slug' => $c->slug,
            ])->toArray(),
        ];
    }

    /**
     * Calculate the overall system status.
     */
    private function calculateOverallStatus(Collection $components): string
    {
        if ($components->contains('status', SystemComponent::STATUS_OUTAGE)) {
            return 'outage';
        }
        if ($components->contains('status', SystemComponent::STATUS_DEGRADED)) {
            return 'degraded';
        }
        return 'operational';
    }

    /**
     * Update a component's status.
     */
    public function updateComponentStatus(
        SystemComponent $component,
        string $status,
        ?string $message = null,
        ?User $user = null,
        ?Incident $incident = null
    ): StatusEvent {
        return DB::transaction(function () use ($component, $status, $message, $user, $incident) {
            // Create status event
            $event = StatusEvent::create([
                'component_id' => $component->id,
                'incident_id' => $incident?->id,
                'status' => $status,
                'message' => $message,
                'created_by' => $user?->id,
            ]);

            // Update component current status
            $component->update([
                'current_status' => $status,
            ]);

            // Clear cache
            $this->clearCache();

            return $event;
        });
    }

    /**
     * Create a new incident.
     */
    public function createIncident(
        string $title,
        string $impact,
        array $componentIds,
        ?string $summary = null,
        ?User $user = null
    ): Incident {
        return DB::transaction(function () use ($title, $impact, $componentIds, $summary, $user) {
            $incident = Incident::create([
                'title' => $title,
                'impact' => $impact,
                'status' => Incident::STATUS_INVESTIGATING,
                'summary' => $summary,
                'started_at' => now(),
                'created_by' => $user?->id,
            ]);

            // Attach affected components
            $incident->components()->attach($componentIds);

            // Update component statuses based on impact
            $newStatus = $impact === Incident::IMPACT_CRITICAL
                ? SystemComponent::STATUS_OUTAGE
                : SystemComponent::STATUS_DEGRADED;

            foreach ($componentIds as $componentId) {
                $component = SystemComponent::find($componentId);
                if ($component) {
                    $this->updateComponentStatus(
                        $component,
                        $newStatus,
                        "Incident: {$title}",
                        $user,
                        $incident
                    );
                }
            }

            $this->clearCache();

            return $incident;
        });
    }

    /**
     * Update an incident's status.
     */
    public function updateIncidentStatus(
        Incident $incident,
        string $status,
        ?string $summary = null,
        ?User $user = null
    ): Incident {
        return DB::transaction(function () use ($incident, $status, $summary, $user) {
            $incident->update([
                'status' => $status,
                'summary' => $summary ?? $incident->summary,
                'resolved_at' => $status === Incident::STATUS_RESOLVED ? now() : null,
            ]);

            // If resolved, restore component statuses to operational
            if ($status === Incident::STATUS_RESOLVED) {
                /** @var SystemComponent $component */
                foreach ($incident->components as $component) {
                    // Only restore if no other active incidents affect this component
                    $hasOtherActiveIncidents = $component->incidents()
                        ->active()
                        ->where('incidents.id', '!=', $incident->id)
                        ->exists();

                    if (!$hasOtherActiveIncidents) {
                        $this->updateComponentStatus(
                            $component,
                            SystemComponent::STATUS_OPERATIONAL,
                            "Resolved: {$incident->title}",
                            $user,
                            $incident
                        );
                    }
                }
            }

            $this->clearCache();

            return $incident;
        });
    }

    /**
     * Clear the status cache.
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Seed default components.
     */
    public function seedDefaultComponents(): void
    {
        $components = [
            ['name' => 'API', 'slug' => 'api', 'description' => 'REST API for creating and managing actions', 'display_order' => 1],
            ['name' => 'Scheduler', 'slug' => 'scheduler', 'description' => 'Action scheduling and dispatch system', 'display_order' => 2],
            ['name' => 'Webhook Delivery', 'slug' => 'webhook-delivery', 'description' => 'HTTP webhook execution', 'display_order' => 3],
            ['name' => 'Email Notifications', 'slug' => 'email-notifications', 'description' => 'Email delivery for reminders', 'display_order' => 4],
            ['name' => 'SMS Notifications', 'slug' => 'sms-notifications', 'description' => 'SMS delivery for reminders', 'display_order' => 5],
            ['name' => 'Dashboard', 'slug' => 'dashboard', 'description' => 'Web dashboard and management interface', 'display_order' => 6],
        ];

        foreach ($components as $data) {
            SystemComponent::firstOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }
    }
}

<?php

namespace App\Services;

use App\Models\AdminNotificationPreference;
use App\Models\ComponentDegradationTracking;
use App\Models\Incident;
use App\Models\ScheduledAction;
use App\Models\SystemComponent;
use App\Models\User;
use Database\Seeders\SystemUserSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class HealthMonitorService
{
    public function __construct(
        private StatusService $statusService,
        private ActionService $actionService
    ) {}

    /**
     * Run the health check and update component statuses.
     */
    public function check(): array
    {
        if (!config('callmelater.health_monitor.enabled', true)) {
            return ['enabled' => false];
        }

        $metrics = $this->collectMetrics();
        $results = [];

        // Check Webhook Delivery component
        $results['webhook_delivery'] = $this->checkWebhookDelivery($metrics);

        // Check Scheduler component
        $results['scheduler'] = $this->checkScheduler($metrics);

        // Process any degradation that needs reminders
        $this->processReminders();

        return [
            'enabled' => true,
            'metrics' => $metrics,
            'results' => $results,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Collect current health metrics.
     */
    private function collectMetrics(): array
    {
        $now = now();

        // Failure rate in last hour
        $lastHourTotal = ScheduledAction::where('updated_at', '>=', $now->copy()->subHour())
            ->whereIn('resolution_status', [
                ScheduledAction::STATUS_EXECUTED,
                ScheduledAction::STATUS_FAILED,
            ])
            ->count();

        $lastHourFailed = ScheduledAction::where('updated_at', '>=', $now->copy()->subHour())
            ->where('resolution_status', ScheduledAction::STATUS_FAILED)
            ->count();

        $failureRate = $lastHourTotal > 0
            ? round(($lastHourFailed / $lastHourTotal) * 100, 2)
            : 0;

        // Stuck actions
        $stuckExecuting = ScheduledAction::where('resolution_status', ScheduledAction::STATUS_EXECUTING)
            ->where('updated_at', '<', $now->copy()->subMinutes(10))
            ->count();

        // Queue metrics
        $queuePending = 0;
        $queueFailedLastHour = 0;

        try {
            $queuePending = Redis::llen('queues:default') ?: 0;
            $queueFailedLastHour = DB::table('failed_jobs')
                ->where('failed_at', '>=', $now->subHour())
                ->count();
        } catch (\Exception $e) {
            Log::warning('Failed to collect queue metrics', ['error' => $e->getMessage()]);
        }

        return [
            'failure_rate' => $failureRate,
            'last_hour_total' => $lastHourTotal,
            'last_hour_failed' => $lastHourFailed,
            'stuck_executing' => $stuckExecuting,
            'queue_pending' => $queuePending,
            'queue_failed_last_hour' => $queueFailedLastHour,
        ];
    }

    /**
     * Check Webhook Delivery component health.
     */
    private function checkWebhookDelivery(array $metrics): array
    {
        $component = SystemComponent::where('slug', 'webhook-delivery')->first();
        if (!$component) {
            return ['error' => 'Component not found'];
        }

        $thresholds = config('callmelater.health_monitor.thresholds');
        $failureRate = $metrics['failure_rate'];

        $result = ['previous_status' => $component->current_status];

        if ($failureRate >= $thresholds['failure_rate_critical']) {
            // Critical - set to outage and create incident
            $result['action'] = 'outage';
            $result['reason'] = "Failure rate {$failureRate}% exceeds critical threshold";

            $this->setComponentStatus($component, SystemComponent::STATUS_OUTAGE, $result['reason']);
            $this->createAutoIncident($component, $failureRate);

        } elseif ($failureRate >= $thresholds['failure_rate_degraded']) {
            // Degraded - set status and track for reminder
            $result['action'] = 'degraded';
            $result['reason'] = "Failure rate {$failureRate}% exceeds degraded threshold";

            $this->setComponentStatus($component, SystemComponent::STATUS_DEGRADED, $result['reason']);
            $this->trackDegradation($component);

        } else {
            // Healthy - clear any degradation tracking
            $result['action'] = 'operational';
            $this->setComponentStatus($component, SystemComponent::STATUS_OPERATIONAL);
            $this->clearDegradation($component);
        }

        $result['new_status'] = $component->fresh()->current_status;
        return $result;
    }

    /**
     * Check Scheduler component health.
     */
    private function checkScheduler(array $metrics): array
    {
        $component = SystemComponent::where('slug', 'scheduler')->first();
        if (!$component) {
            return ['error' => 'Component not found'];
        }

        $thresholds = config('callmelater.health_monitor.thresholds');
        $result = ['previous_status' => $component->current_status];

        // Check for critical issues
        if ($metrics['stuck_executing'] >= $thresholds['stuck_executing_critical']) {
            $result['action'] = 'outage';
            $result['reason'] = "{$metrics['stuck_executing']} actions stuck in EXECUTING state";

            $this->setComponentStatus($component, SystemComponent::STATUS_OUTAGE, $result['reason']);
            $this->createAutoIncident($component, null, $metrics['stuck_executing']);

        } elseif (
            $metrics['stuck_executing'] >= $thresholds['stuck_executing_degraded'] ||
            $metrics['queue_pending'] >= $thresholds['queue_pending_degraded'] ||
            $metrics['queue_failed_last_hour'] >= $thresholds['queue_failed_degraded']
        ) {
            $result['action'] = 'degraded';
            $reasons = [];
            if ($metrics['stuck_executing'] >= $thresholds['stuck_executing_degraded']) {
                $reasons[] = "{$metrics['stuck_executing']} stuck actions";
            }
            if ($metrics['queue_pending'] >= $thresholds['queue_pending_degraded']) {
                $reasons[] = "{$metrics['queue_pending']} pending queue jobs";
            }
            if ($metrics['queue_failed_last_hour'] >= $thresholds['queue_failed_degraded']) {
                $reasons[] = "{$metrics['queue_failed_last_hour']} failed jobs in last hour";
            }
            $result['reason'] = implode(', ', $reasons);

            $this->setComponentStatus($component, SystemComponent::STATUS_DEGRADED, $result['reason']);
            $this->trackDegradation($component);

        } else {
            $result['action'] = 'operational';
            $this->setComponentStatus($component, SystemComponent::STATUS_OPERATIONAL);
            $this->clearDegradation($component);
        }

        $result['new_status'] = $component->fresh()->current_status;
        return $result;
    }

    /**
     * Set component status (only if changed).
     */
    private function setComponentStatus(SystemComponent $component, string $status, ?string $message = null): void
    {
        if ($component->current_status === $status) {
            return;
        }

        $systemUser = $this->getSystemUser();

        $this->statusService->updateComponentStatus(
            $component,
            $status,
            $message ?? "Automated status update",
            $systemUser
        );

        Log::info("Health monitor: Component status changed", [
            'component' => $component->slug,
            'from' => $component->current_status,
            'to' => $status,
        ]);
    }

    /**
     * Track component degradation for reminder.
     */
    private function trackDegradation(SystemComponent $component): void
    {
        ComponentDegradationTracking::firstOrCreate(
            ['component_id' => $component->id],
            ['degraded_since' => now()]
        );
    }

    /**
     * Clear degradation tracking when component recovers.
     */
    private function clearDegradation(SystemComponent $component): void
    {
        ComponentDegradationTracking::where('component_id', $component->id)->delete();
    }

    /**
     * Process reminders for degraded components.
     */
    private function processReminders(): void
    {
        $reminderDelay = config('callmelater.health_monitor.reminder_delay', 15);

        $tracking = ComponentDegradationTracking::with('component')
            ->whereNull('notified_at')
            ->where('degraded_since', '<=', now()->subMinutes($reminderDelay))
            ->get();

        foreach ($tracking as $record) {
            $this->sendDegradationReminder($record);
        }
    }

    /**
     * Send a reminder about component degradation.
     */
    private function sendDegradationReminder(ComponentDegradationTracking $tracking): void
    {
        $recipients = AdminNotificationPreference::getHealthAlertRecipients();

        if (empty($recipients)) {
            Log::warning("Health monitor: No admins opted into health alerts, skipping reminder");
            return;
        }

        $component = $tracking->component;
        $systemUser = $this->getSystemUser();

        try {
            $action = $this->actionService->create($systemUser, [
                'type' => 'reminder',
                'name' => "Alert: {$component->name} degraded",
                'message' => $this->buildReminderMessage($component, $tracking),
                'intent' => ['delay' => '15m'], // Follow up in 15 min if still degraded
                'escalation_rules' => [
                    'recipients' => $recipients,
                    'channels' => ['email'],
                ],
            ]);

            $tracking->update([
                'reminder_action_id' => $action->id,
                'notified_at' => now(),
            ]);

            Log::info("Health monitor: Sent degradation reminder", [
                'component' => $component->slug,
                'action_id' => $action->id,
                'recipients' => count($recipients),
            ]);

        } catch (\Exception $e) {
            Log::error("Health monitor: Failed to create reminder", [
                'component' => $component->slug,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build reminder message.
     */
    private function buildReminderMessage(SystemComponent $component, ComponentDegradationTracking $tracking): string
    {
        $duration = $tracking->degraded_duration;

        return <<<MSG
The {$component->name} component has been in a degraded state for {$duration}.

Current Status: {$component->status_label}
Component: {$component->name}
Started: {$tracking->degraded_since->format('Y-m-d H:i:s')} UTC

Please investigate and take action if needed. If this alert is expected (e.g., during maintenance), you can dismiss it.

View status page: /admin/status
MSG;
    }

    /**
     * Auto-create incident for critical issues.
     */
    private function createAutoIncident(SystemComponent $component, ?float $failureRate = null, ?int $stuckCount = null): void
    {
        // Check if there's already an active incident for this component
        $existingIncident = $component->incidents()
            ->whereNull('resolved_at')
            ->exists();

        if ($existingIncident) {
            return; // Don't create duplicate incidents
        }

        $systemUser = $this->getSystemUser();

        $title = $failureRate !== null
            ? "Critical: High failure rate ({$failureRate}%)"
            : "Critical: {$stuckCount} actions stuck";

        $summary = $failureRate !== null
            ? "Automated incident: Failure rate exceeded {$failureRate}% (threshold: " . config('callmelater.health_monitor.thresholds.failure_rate_critical') . "%)"
            : "Automated incident: {$stuckCount} actions stuck in EXECUTING state (threshold: " . config('callmelater.health_monitor.thresholds.stuck_executing_critical') . ")";

        $this->statusService->createIncident(
            $title,
            Incident::IMPACT_CRITICAL,
            [$component->id],
            $summary,
            $systemUser
        );

        Log::warning("Health monitor: Auto-created incident", [
            'component' => $component->slug,
            'title' => $title,
        ]);
    }

    /**
     * Get the system user.
     */
    private function getSystemUser(): User
    {
        return SystemUserSeeder::getSystemUser();
    }
}

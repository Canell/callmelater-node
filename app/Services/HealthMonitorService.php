<?php

namespace App\Services;

use App\Mail\IncidentAlertMail;
use App\Models\AdminNotificationPreference;
use App\Models\ComponentDegradationTracking;
use App\Models\DeliveryAttempt;
use App\Models\Incident;
use App\Models\ScheduledAction;
use App\Models\SystemComponent;
use App\Models\User;
use Database\Seeders\SystemUserSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

        // Create heartbeat action to dogfood our own system
        $heartbeatResult = $this->createHeartbeat();
        if ($heartbeatResult) {
            $results['heartbeat'] = $heartbeatResult;
        }

        return [
            'enabled' => true,
            'metrics' => $metrics,
            'results' => $results,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Collect current health metrics.
     *
     * IMPORTANT: For webhook delivery health, we only count DELIVERY_ERROR (network/infrastructure issues).
     * Customer-side errors (4xx/5xx from their endpoints) are tracked separately and do NOT affect
     * platform health metrics. This prevents misconfigured customer webhooks from triggering incidents.
     */
    private function collectMetrics(): array
    {
        $now = now();
        $thresholds = $this->getThresholds();

        // Delivery metrics from delivery_attempts table (within configurable window)
        $deliveryWindow = $now->copy()->subMinutes($thresholds['delivery_window_minutes'] ?? 10);

        $deliveryAttempts = DeliveryAttempt::where('created_at', '>=', $deliveryWindow)
            ->selectRaw('failure_category, COUNT(*) as count')
            ->groupBy('failure_category')
            ->pluck('count', 'failure_category');

        $totalAttempts = $deliveryAttempts->sum();
        $deliveryErrors = $deliveryAttempts->get(DeliveryAttempt::CATEGORY_DELIVERY_ERROR, 0);

        // Only count DELIVERY_ERROR for platform health (not customer 4xx/5xx)
        $deliveryErrorRate = $totalAttempts > 0
            ? round(($deliveryErrors / $totalAttempts) * 100, 2)
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
                ->where('failed_at', '>=', $now->copy()->subHour())
                ->count();
        } catch (\Exception $e) {
            Log::warning('Failed to collect queue metrics', ['error' => $e->getMessage()]);
        }

        return [
            // Delivery metrics (only infrastructure errors affect health)
            'delivery_error_rate' => $deliveryErrorRate,
            'delivery_errors' => $deliveryErrors,
            'total_attempts' => $totalAttempts,
            // Customer-side metrics (for visibility, not health decisions)
            'customer_4xx_count' => $deliveryAttempts->get(DeliveryAttempt::CATEGORY_CUSTOMER_4XX, 0),
            'customer_5xx_count' => $deliveryAttempts->get(DeliveryAttempt::CATEGORY_CUSTOMER_5XX, 0),
            'success_count' => $deliveryAttempts->get(DeliveryAttempt::CATEGORY_SUCCESS, 0),
            // Scheduler metrics
            'stuck_executing' => $stuckExecuting,
            'queue_pending' => $queuePending,
            'queue_failed_last_hour' => $queueFailedLastHour,
        ];
    }

    /**
     * Check Webhook Delivery component health.
     *
     * Only counts DELIVERY_ERROR (infrastructure issues) for health decisions.
     * Additionally requires distribution guards: errors must be widespread
     * (across multiple accounts and domains) to trigger incidents.
     */
    private function checkWebhookDelivery(array $metrics): array
    {
        $component = SystemComponent::where('slug', 'webhook-delivery')->first();
        if (!$component) {
            return ['error' => 'Component not found'];
        }

        $thresholds = $this->getThresholds();
        $errorRate = $metrics['delivery_error_rate'];

        $result = ['previous_status' => $component->current_status];

        if ($errorRate >= $thresholds['failure_rate_critical']) {
            // Check distribution before creating incident
            $distribution = $this->checkDeliveryErrorDistribution();
            $result['distribution'] = $distribution;

            $minAccounts = $thresholds['min_affected_accounts'] ?? 5;
            $minDomains = $thresholds['min_affected_domains'] ?? 3;

            if ($distribution['affected_accounts'] >= $minAccounts
                && $distribution['affected_domains'] >= $minDomains) {
                // Widespread issue - create outage and incident
                $result['action'] = 'outage';
                $result['reason'] = "Delivery error rate {$errorRate}% across {$distribution['affected_accounts']} accounts and {$distribution['affected_domains']} domains";

                $this->setComponentStatus($component, SystemComponent::STATUS_OUTAGE, $result['reason']);
                $this->createAutoIncident($component, $errorRate, null, $distribution);
            } else {
                // Elevated errors but isolated (likely single customer issue) - degrade but no incident
                $result['action'] = 'degraded';
                $result['reason'] = "Delivery errors elevated ({$errorRate}%) but isolated (accounts: {$distribution['affected_accounts']}, domains: {$distribution['affected_domains']})";

                $this->setComponentStatus($component, SystemComponent::STATUS_DEGRADED, $result['reason']);
                $this->trackDegradation($component);
            }

        } elseif ($errorRate >= $thresholds['failure_rate_degraded']) {
            // Degraded - set status and track for reminder
            $result['action'] = 'degraded';
            $result['reason'] = "Delivery error rate {$errorRate}% exceeds degraded threshold";

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
     * Check the distribution of delivery errors.
     *
     * Returns how many distinct accounts and target domains are affected.
     * Used to determine if errors are widespread (platform issue) or
     * isolated (single customer misconfiguration).
     */
    private function checkDeliveryErrorDistribution(): array
    {
        $thresholds = $this->getThresholds();
        $window = now()->subMinutes($thresholds['delivery_window_minutes'] ?? 10);

        $distribution = DeliveryAttempt::where('delivery_attempts.created_at', '>=', $window)
            ->where('failure_category', DeliveryAttempt::CATEGORY_DELIVERY_ERROR)
            ->join('scheduled_actions', 'delivery_attempts.action_id', '=', 'scheduled_actions.id')
            ->selectRaw('COUNT(DISTINCT scheduled_actions.created_by_user_id) as affected_accounts')
            ->selectRaw('COUNT(DISTINCT delivery_attempts.target_domain) as affected_domains')
            ->first();

        return [
            'affected_accounts' => $distribution->affected_accounts ?? 0,
            'affected_domains' => $distribution->affected_domains ?? 0,
        ];
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

        $thresholds = $this->getThresholds();
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
     *
     * @param array<string, int>|null $distribution Distribution info (affected_accounts, affected_domains)
     */
    private function createAutoIncident(SystemComponent $component, ?float $failureRate = null, ?int $stuckCount = null, ?array $distribution = null): void
    {
        // Check if there's already an active incident for this component
        $existingIncident = $component->incidents()
            ->whereNull('resolved_at')
            ->exists();

        if ($existingIncident) {
            return; // Don't create duplicate incidents
        }

        $systemUser = $this->getSystemUser();

        if ($failureRate !== null) {
            $title = "Critical: High delivery error rate ({$failureRate}%)";
            $reason = "Delivery error rate exceeded {$failureRate}% (threshold: " . $this->getThresholds()['failure_rate_critical'] . "%)";
            if ($distribution) {
                $reason .= " across {$distribution['affected_accounts']} accounts and {$distribution['affected_domains']} domains";
            }
        } else {
            $title = "Critical: {$stuckCount} actions stuck";
            $reason = "{$stuckCount} actions stuck in EXECUTING state (threshold: " . $this->getThresholds()['stuck_executing_critical'] . ")";
        }

        $summary = "Automated incident: {$reason}";

        $incident = $this->statusService->createIncident(
            $title,
            Incident::IMPACT_CRITICAL,
            [$component->id],
            $summary,
            $systemUser
        );

        Log::warning("Health monitor: Auto-created incident", [
            'component' => $component->slug,
            'title' => $title,
            'distribution' => $distribution,
        ]);

        // Send notification to admins who opted into incident alerts
        $this->sendIncidentNotification($incident, $component, $reason);
    }

    /**
     * Send email notification for a new incident.
     */
    private function sendIncidentNotification(Incident $incident, SystemComponent $component, string $reason): void
    {
        $recipients = AdminNotificationPreference::getIncidentAlertRecipients();

        if (empty($recipients)) {
            Log::info("Health monitor: No admins opted into incident alerts, skipping notification");
            return;
        }

        try {
            foreach ($recipients as $email) {
                Mail::to($email)->queue(new IncidentAlertMail($incident, $component->name, $reason));
            }

            Log::info("Health monitor: Sent incident notifications", [
                'incident_id' => $incident->id,
                'recipients' => count($recipients),
            ]);
        } catch (\Exception $e) {
            Log::error("Health monitor: Failed to send incident notification", [
                'incident_id' => $incident->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get the system user.
     */
    private function getSystemUser(): User
    {
        return SystemUserSeeder::getSystemUser();
    }

    /**
     * Get thresholds with defaults to prevent null errors.
     */
    private function getThresholds(): array
    {
        return array_merge([
            // Delivery error thresholds (only counts infrastructure errors, not customer 4xx/5xx)
            'failure_rate_degraded' => 10,
            'failure_rate_critical' => 25,
            // Distribution guards - prevent single customer from triggering incidents
            'min_affected_accounts' => 5,
            'min_affected_domains' => 3,
            'delivery_window_minutes' => 10,
            // Scheduler thresholds
            'stuck_executing_degraded' => 5,
            'stuck_executing_critical' => 15,
            'queue_pending_degraded' => 100,
            'queue_failed_degraded' => 10,
        ], config('callmelater.health_monitor.thresholds', []));
    }

    /**
     * Create a heartbeat HTTP action to test our own delivery pipeline.
     */
    private function createHeartbeat(): ?array
    {
        if (!config('callmelater.health_monitor.heartbeat_enabled', true)) {
            return null;
        }

        $systemUser = $this->getSystemUser();
        $heartbeatId = uniqid('hb_');

        try {
            $action = $this->actionService->create($systemUser, [
                'type' => 'http',
                'name' => 'Health Monitor Heartbeat',
                'intent' => ['delay' => '1m'],
                'http_request' => [
                    'method' => 'POST',
                    'url' => $this->getHeartbeatUrl(),
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'X-Heartbeat-Id' => $heartbeatId,
                    ],
                    'body' => [
                        'heartbeat_id' => $heartbeatId,
                        'created_at' => now()->toIso8601String(),
                        'source' => 'health_monitor',
                    ],
                ],
                'max_attempts' => 3,
            ]);

            Log::info('Health monitor: Heartbeat action created', [
                'action_id' => $action->id,
                'heartbeat_id' => $heartbeatId,
                'execute_at' => $action->execute_at_utc?->toIso8601String(),
            ]);

            return [
                'action_id' => $action->id,
                'heartbeat_id' => $heartbeatId,
                'status' => 'created',
            ];

        } catch (\Exception $e) {
            Log::error('Health monitor: Failed to create heartbeat', [
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get the heartbeat URL.
     */
    private function getHeartbeatUrl(): string
    {
        $baseUrl = config('app.url');
        return rtrim($baseUrl, '/') . '/api/internal/heartbeat';
    }
}

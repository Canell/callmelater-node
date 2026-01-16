<?php

namespace Tests\Unit;

use App\Models\DeliveryAttempt;
use App\Models\ScheduledAction;
use App\Models\SystemComponent;
use App\Models\User;
use App\Services\ActionService;
use App\Services\HealthMonitorService;
use App\Services\StatusService;
use Database\Seeders\SystemUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class HealthMonitorServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private HealthMonitorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a regular user
        $this->user = User::factory()->create();

        // Create system user
        $this->artisan('db:seed', ['--class' => SystemUserSeeder::class]);

        // Create system components
        SystemComponent::create([
            'name' => 'Webhook Delivery',
            'slug' => 'webhook-delivery',
            'description' => 'HTTP webhook execution',
            'current_status' => SystemComponent::STATUS_OPERATIONAL,
            'display_order' => 1,
        ]);

        SystemComponent::create([
            'name' => 'Scheduler',
            'slug' => 'scheduler',
            'description' => 'Action scheduling and dispatch',
            'current_status' => SystemComponent::STATUS_OPERATIONAL,
            'display_order' => 2,
        ]);

        $this->service = app(HealthMonitorService::class);
    }

    public function test_check_returns_disabled_when_config_disabled(): void
    {
        config(['callmelater.health_monitor.enabled' => false]);

        $result = $this->service->check();

        $this->assertFalse($result['enabled']);
        $this->assertArrayNotHasKey('metrics', $result);
    }

    public function test_check_returns_metrics_when_enabled(): void
    {
        config(['callmelater.health_monitor.enabled' => true]);

        $result = $this->service->check();

        $this->assertTrue($result['enabled']);
        $this->assertArrayHasKey('metrics', $result);
        $this->assertArrayHasKey('delivery_error_rate', $result['metrics']);
        $this->assertArrayHasKey('stuck_executing', $result['metrics']);
        $this->assertArrayHasKey('queue_pending', $result['metrics']);
    }

    public function test_webhook_delivery_stays_operational_when_no_failures(): void
    {
        config(['callmelater.health_monitor.enabled' => true]);

        // Create some successful delivery attempts
        $action = $this->createAction(ScheduledAction::STATUS_EXECUTED);
        $this->createDeliveryAttempt($action, DeliveryAttempt::CATEGORY_SUCCESS);
        $this->createDeliveryAttempt($action, DeliveryAttempt::CATEGORY_SUCCESS);

        $result = $this->service->check();

        $this->assertEquals('operational', $result['results']['webhook_delivery']['action']);
    }

    public function test_webhook_delivery_becomes_degraded_on_high_failure_rate(): void
    {
        config(['callmelater.health_monitor.enabled' => true]);
        config(['callmelater.health_monitor.thresholds.failure_rate_degraded' => 10]);

        $action = $this->createAction(ScheduledAction::STATUS_EXECUTED);

        // Create 8 successful and 2 delivery errors (20% delivery error rate)
        for ($i = 0; $i < 8; $i++) {
            $this->createDeliveryAttempt($action, DeliveryAttempt::CATEGORY_SUCCESS);
        }
        for ($i = 0; $i < 2; $i++) {
            $this->createDeliveryAttempt($action, DeliveryAttempt::CATEGORY_DELIVERY_ERROR);
        }

        $result = $this->service->check();

        $this->assertEquals('degraded', $result['results']['webhook_delivery']['action']);
        $this->assertStringContainsString('error rate', $result['results']['webhook_delivery']['reason']);
    }

    public function test_webhook_delivery_becomes_outage_on_critical_failure_rate(): void
    {
        config(['callmelater.health_monitor.enabled' => true]);
        config(['callmelater.health_monitor.thresholds.failure_rate_critical' => 25]);
        // Lower distribution guards for testing
        config(['callmelater.health_monitor.thresholds.min_affected_accounts' => 1]);
        config(['callmelater.health_monitor.thresholds.min_affected_domains' => 1]);

        $action = $this->createAction(ScheduledAction::STATUS_EXECUTED);

        // Create 6 successful and 4 delivery errors (40% delivery error rate)
        for ($i = 0; $i < 6; $i++) {
            $this->createDeliveryAttempt($action, DeliveryAttempt::CATEGORY_SUCCESS);
        }
        for ($i = 0; $i < 4; $i++) {
            $this->createDeliveryAttempt($action, DeliveryAttempt::CATEGORY_DELIVERY_ERROR);
        }

        $result = $this->service->check();

        $this->assertEquals('outage', $result['results']['webhook_delivery']['action']);
    }

    public function test_scheduler_becomes_degraded_with_stuck_actions(): void
    {
        config(['callmelater.health_monitor.enabled' => true]);
        config(['callmelater.health_monitor.thresholds.stuck_executing_degraded' => 3]);
        config(['callmelater.health_monitor.thresholds.stuck_executing_critical' => 100]); // High value to avoid critical

        // Create stuck executing actions (updated more than 10 minutes ago)
        // Must use withoutTimestamps to preserve the old updated_at value
        for ($i = 0; $i < 5; $i++) {
            $action = $this->createAction(ScheduledAction::STATUS_EXECUTING);
            ScheduledAction::withoutTimestamps(function () use ($action) {
                $action->updated_at = now()->subMinutes(15);
                $action->save();
            });
        }

        $result = $this->service->check();

        $this->assertEquals('degraded', $result['results']['scheduler']['action']);
        $this->assertStringContainsString('stuck', $result['results']['scheduler']['reason']);
    }

    public function test_scheduler_stays_operational_with_recent_executing_actions(): void
    {
        config(['callmelater.health_monitor.enabled' => true]);

        // Create executing actions that are recent (not stuck)
        for ($i = 0; $i < 5; $i++) {
            $this->createAction(ScheduledAction::STATUS_EXECUTING);
        }

        $result = $this->service->check();

        $this->assertEquals('operational', $result['results']['scheduler']['action']);
    }

    public function test_metrics_include_correct_failure_rate_calculation(): void
    {
        config(['callmelater.health_monitor.enabled' => true]);

        $action = $this->createAction(ScheduledAction::STATUS_EXECUTED);

        // Create 7 successful and 3 delivery errors (30% delivery error rate)
        for ($i = 0; $i < 7; $i++) {
            $this->createDeliveryAttempt($action, DeliveryAttempt::CATEGORY_SUCCESS);
        }
        for ($i = 0; $i < 3; $i++) {
            $this->createDeliveryAttempt($action, DeliveryAttempt::CATEGORY_DELIVERY_ERROR);
        }

        $result = $this->service->check();

        $this->assertEquals(30, $result['metrics']['delivery_error_rate']);
        $this->assertEquals(10, $result['metrics']['total_attempts']);
        $this->assertEquals(3, $result['metrics']['delivery_errors']);
    }

    public function test_old_delivery_attempts_not_included_in_failure_rate(): void
    {
        config(['callmelater.health_monitor.enabled' => true]);
        config(['callmelater.health_monitor.thresholds.delivery_window_minutes' => 10]);

        $action = $this->createAction(ScheduledAction::STATUS_EXECUTED);

        // Create old delivery errors (outside the 10-minute window)
        for ($i = 0; $i < 5; $i++) {
            $attempt = $this->createDeliveryAttempt($action, DeliveryAttempt::CATEGORY_DELIVERY_ERROR);
            DeliveryAttempt::withoutTimestamps(function () use ($attempt) {
                $attempt->created_at = now()->subMinutes(20);
                $attempt->save();
            });
        }

        // Create recent successful attempt
        $this->createDeliveryAttempt($action, DeliveryAttempt::CATEGORY_SUCCESS);

        $result = $this->service->check();

        // Should be 0% delivery error rate (only the recent successful one counts)
        $this->assertEquals(0, $result['metrics']['delivery_error_rate']);
        $this->assertEquals(1, $result['metrics']['total_attempts']);
    }

    public function test_customer_errors_do_not_affect_platform_health(): void
    {
        config(['callmelater.health_monitor.enabled' => true]);
        config(['callmelater.health_monitor.thresholds.failure_rate_degraded' => 10]);

        $action = $this->createAction(ScheduledAction::STATUS_EXECUTED);

        // Create 5 successful and 5 customer 4xx errors (50% customer error rate)
        // But 0% delivery error rate - should stay operational
        for ($i = 0; $i < 5; $i++) {
            $this->createDeliveryAttempt($action, DeliveryAttempt::CATEGORY_SUCCESS);
        }
        for ($i = 0; $i < 5; $i++) {
            $this->createDeliveryAttempt($action, DeliveryAttempt::CATEGORY_CUSTOMER_4XX);
        }

        $result = $this->service->check();

        // Platform should stay operational because customer errors don't count
        $this->assertEquals('operational', $result['results']['webhook_delivery']['action']);
        $this->assertEquals(0, $result['metrics']['delivery_error_rate']);
        $this->assertEquals(5, $result['metrics']['customer_4xx_count']);
    }

    /**
     * Helper to create an action with given status.
     */
    private function createAction(string $status): ScheduledAction
    {
        return ScheduledAction::create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'name' => 'Test Action',
            'type' => ScheduledAction::TYPE_HTTP,
            'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
            'intent_payload' => ['execute_at' => now()->subMinute()->toIso8601String()],
            'resolution_status' => $status,
            'execute_at_utc' => now()->subMinute(),
            'http_request' => ['url' => 'https://example.com', 'method' => 'POST'],
        ]);
    }

    /**
     * Helper to create a delivery attempt with given failure category.
     */
    private function createDeliveryAttempt(ScheduledAction $action, string $failureCategory, ?string $targetDomain = null): DeliveryAttempt
    {
        static $attemptNumber = 0;
        $attemptNumber++;

        return DeliveryAttempt::create([
            'action_id' => $action->id,
            'attempt_number' => $attemptNumber,
            'status' => $failureCategory === DeliveryAttempt::CATEGORY_SUCCESS
                ? DeliveryAttempt::STATUS_SUCCESS
                : DeliveryAttempt::STATUS_FAILED,
            'failure_category' => $failureCategory,
            'target_domain' => $targetDomain ?? 'example.com',
            'response_code' => match ($failureCategory) {
                DeliveryAttempt::CATEGORY_SUCCESS => 200,
                DeliveryAttempt::CATEGORY_CUSTOMER_4XX => 403,
                DeliveryAttempt::CATEGORY_CUSTOMER_5XX => 500,
                default => null,
            },
            'duration_ms' => 100,
        ]);
    }
}

<?php

namespace Tests\Unit;

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
        $this->assertArrayHasKey('failure_rate', $result['metrics']);
        $this->assertArrayHasKey('stuck_executing', $result['metrics']);
        $this->assertArrayHasKey('queue_pending', $result['metrics']);
    }

    public function test_webhook_delivery_stays_operational_when_no_failures(): void
    {
        config(['callmelater.health_monitor.enabled' => true]);

        // Create some successful actions
        $this->createAction(ScheduledAction::STATUS_EXECUTED);
        $this->createAction(ScheduledAction::STATUS_EXECUTED);

        $result = $this->service->check();

        $this->assertEquals('operational', $result['results']['webhook_delivery']['action']);
    }

    public function test_webhook_delivery_becomes_degraded_on_high_failure_rate(): void
    {
        config(['callmelater.health_monitor.enabled' => true]);
        config(['callmelater.health_monitor.thresholds.failure_rate_degraded' => 10]);

        // Create 8 successful and 2 failed (20% failure rate)
        for ($i = 0; $i < 8; $i++) {
            $this->createAction(ScheduledAction::STATUS_EXECUTED);
        }
        for ($i = 0; $i < 2; $i++) {
            $this->createAction(ScheduledAction::STATUS_FAILED);
        }

        $result = $this->service->check();

        $this->assertEquals('degraded', $result['results']['webhook_delivery']['action']);
        $this->assertStringContainsString('Failure rate', $result['results']['webhook_delivery']['reason']);
    }

    public function test_webhook_delivery_becomes_outage_on_critical_failure_rate(): void
    {
        config(['callmelater.health_monitor.enabled' => true]);
        config(['callmelater.health_monitor.thresholds.failure_rate_critical' => 25]);

        // Create 6 successful and 4 failed (40% failure rate)
        for ($i = 0; $i < 6; $i++) {
            $this->createAction(ScheduledAction::STATUS_EXECUTED);
        }
        for ($i = 0; $i < 4; $i++) {
            $this->createAction(ScheduledAction::STATUS_FAILED);
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

        // Create 7 successful and 3 failed (30% failure rate)
        for ($i = 0; $i < 7; $i++) {
            $this->createAction(ScheduledAction::STATUS_EXECUTED);
        }
        for ($i = 0; $i < 3; $i++) {
            $this->createAction(ScheduledAction::STATUS_FAILED);
        }

        $result = $this->service->check();

        $this->assertEquals(30, $result['metrics']['failure_rate']);
        $this->assertEquals(10, $result['metrics']['last_hour_total']);
        $this->assertEquals(3, $result['metrics']['last_hour_failed']);
    }

    public function test_old_actions_not_included_in_failure_rate(): void
    {
        config(['callmelater.health_monitor.enabled' => true]);

        // Create old failed actions (more than 1 hour ago)
        // Use DB update to avoid timestamp auto-update
        for ($i = 0; $i < 5; $i++) {
            $action = $this->createAction(ScheduledAction::STATUS_FAILED);
            ScheduledAction::withoutTimestamps(function () use ($action) {
                $action->updated_at = now()->subHours(2);
                $action->save();
            });
        }

        // Create recent successful actions
        $this->createAction(ScheduledAction::STATUS_EXECUTED);

        $result = $this->service->check();

        // Should be 0% failure rate (only the recent successful one counts)
        $this->assertEquals(0, $result['metrics']['failure_rate']);
        $this->assertEquals(1, $result['metrics']['last_hour_total']);
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
}

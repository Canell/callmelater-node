<?php

namespace Tests\Feature;

use App\Models\ScheduledAction;
use App\Models\User;
use App\Services\HealthMonitorService;
use Database\Seeders\SystemComponentsSeeder;
use Database\Seeders\SystemUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeartbeatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed required data
        $this->artisan('db:seed', ['--class' => SystemUserSeeder::class]);
        $this->artisan('db:seed', ['--class' => SystemComponentsSeeder::class]);
    }

    public function test_heartbeat_endpoint_receives_ping(): void
    {
        $response = $this->postJson('/api/internal/heartbeat', [], [
            'X-Heartbeat-Id' => 'hb_test123',
            'X-Action-Id' => 'action_test123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'message' => 'Heartbeat acknowledged',
            ])
            ->assertJsonStructure(['received_at']);
    }

    public function test_heartbeat_status_returns_unknown_when_no_heartbeat(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($user)
            ->getJson('/api/admin/heartbeat');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'unknown',
                'last_heartbeat' => null,
            ]);
    }

    public function test_heartbeat_status_returns_healthy_after_recent_ping(): void
    {
        // Send a heartbeat first
        $this->postJson('/api/internal/heartbeat', [], [
            'X-Heartbeat-Id' => 'hb_test123',
            'X-Action-Id' => 'action_test123',
        ]);

        $user = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($user)
            ->getJson('/api/admin/heartbeat');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'healthy',
            ])
            ->assertJsonStructure([
                'last_heartbeat' => ['action_id', 'heartbeat_id', 'received_at'],
            ]);
    }

    public function test_health_monitor_creates_heartbeat_action(): void
    {
        config(['callmelater.health_monitor.enabled' => true]);
        config(['callmelater.health_monitor.heartbeat_enabled' => true]);

        $service = app(HealthMonitorService::class);
        $result = $service->check();

        $this->assertTrue($result['enabled']);
        $this->assertArrayHasKey('heartbeat', $result['results']);
        $this->assertEquals('created', $result['results']['heartbeat']['status']);
        $this->assertNotNull($result['results']['heartbeat']['action_id']);

        // Verify action was created in database
        $action = ScheduledAction::find($result['results']['heartbeat']['action_id']);
        $this->assertNotNull($action);
        $this->assertEquals('immediate', $action->mode);
        $this->assertEquals('Health Monitor Heartbeat', $action->name);
    }

    public function test_heartbeat_disabled_does_not_create_action(): void
    {
        config(['callmelater.health_monitor.enabled' => true]);
        config(['callmelater.health_monitor.heartbeat_enabled' => false]);

        $service = app(HealthMonitorService::class);
        $result = $service->check();

        $this->assertTrue($result['enabled']);
        $this->assertArrayNotHasKey('heartbeat', $result['results']);
    }

    public function test_heartbeat_action_has_correct_url(): void
    {
        config(['callmelater.health_monitor.enabled' => true]);
        config(['callmelater.health_monitor.heartbeat_enabled' => true]);
        config(['app.url' => 'https://callmelater.io']);

        $service = app(HealthMonitorService::class);
        $result = $service->check();

        $action = ScheduledAction::find($result['results']['heartbeat']['action_id']);
        $this->assertEquals('https://callmelater.io/api/internal/heartbeat', $action->request['url']);
    }
}

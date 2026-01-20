<?php

namespace Tests\Feature;

use App\Models\ScheduledAction;
use App\Models\UsageCounter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RetryApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
        Queue::fake();
    }

    public function test_can_retry_failed_action(): void
    {
        $action = $this->createAction(ScheduledAction::STATUS_FAILED);

        $response = $this->postJson("/api/v1/actions/{$action->id}/retry");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'execution_cycle_id',
                'action',
            ])
            ->assertJsonPath('message', 'Action retry initiated');
    }

    public function test_retry_creates_execution_cycle(): void
    {
        $action = $this->createAction(ScheduledAction::STATUS_FAILED);

        $response = $this->postJson("/api/v1/actions/{$action->id}/retry");

        $response->assertStatus(200);
        $this->assertDatabaseHas('execution_cycles', [
            'action_id' => $action->id,
            'triggered_by' => 'manual',
            'triggered_by_user_id' => $this->user->id,
        ]);
    }

    public function test_retry_changes_action_status_to_executing(): void
    {
        $action = $this->createAction(ScheduledAction::STATUS_FAILED);

        $this->postJson("/api/v1/actions/{$action->id}/retry");

        $action->refresh();
        // After retry, action is immediately dispatched and moves to executing status
        $this->assertEquals(ScheduledAction::STATUS_EXECUTING, $action->resolution_status);
    }

    public function test_cannot_retry_executed_action(): void
    {
        $action = $this->createAction(ScheduledAction::STATUS_EXECUTED);

        $response = $this->postJson("/api/v1/actions/{$action->id}/retry");

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'reasons']);
    }

    public function test_cannot_retry_pending_action(): void
    {
        $action = $this->createAction(ScheduledAction::STATUS_PENDING_RESOLUTION);

        $response = $this->postJson("/api/v1/actions/{$action->id}/retry");

        $response->assertStatus(422);
    }

    public function test_cannot_retry_cancelled_action(): void
    {
        $action = $this->createAction(ScheduledAction::STATUS_CANCELLED);

        $response = $this->postJson("/api/v1/actions/{$action->id}/retry");

        $response->assertStatus(422);
    }

    public function test_retry_respects_rate_limit(): void
    {
        $action = $this->createAction(ScheduledAction::STATUS_FAILED);
        $action->update([
            'manual_retry_count' => 3,
            'last_manual_retry_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/actions/{$action->id}/retry");

        $response->assertStatus(422);
        $this->assertStringContainsString('Maximum 3 manual retries', $response->json('reasons.0'));
    }

    public function test_retry_checks_quota(): void
    {
        $action = $this->createAction(ScheduledAction::STATUS_FAILED);

        // Use up all quota
        $limit = $this->user->account->getPlanLimit('max_actions_per_month');
        UsageCounter::forCurrentMonth($this->user->account_id)
            ->update(['actions_created' => $limit]);

        $response = $this->postJson("/api/v1/actions/{$action->id}/retry");

        $response->assertStatus(422);
        $this->assertTrue(
            collect($response->json('reasons'))->contains(fn ($r) => str_contains($r, 'quota exceeded'))
        );
    }

    public function test_cannot_retry_other_users_action(): void
    {
        $otherUser = User::factory()->create();
        $action = ScheduledAction::create([
            'account_id' => $otherUser->account_id,
            'created_by_user_id' => $otherUser->id,
            'name' => 'Other User Action',
            'type' => 'http',
            'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
            'intent_payload' => ['execute_at' => now()->subHour()->toIso8601String()],
            'resolution_status' => ScheduledAction::STATUS_FAILED,
            'http_request' => ['url' => 'https://example.com', 'method' => 'POST'],
        ]);

        $response = $this->postJson("/api/v1/actions/{$action->id}/retry");

        $response->assertStatus(404);
    }

    public function test_retry_returns_404_for_nonexistent_action(): void
    {
        $response = $this->postJson('/api/v1/actions/nonexistent-id/retry');

        $response->assertStatus(404);
    }

    public function test_unauthenticated_user_cannot_retry(): void
    {
        $action = $this->createAction(ScheduledAction::STATUS_FAILED);

        $this->app['auth']->forgetGuards();

        $response = $this->postJson("/api/v1/actions/{$action->id}/retry");

        $response->assertStatus(401);
    }

    public function test_retry_dispatches_job(): void
    {
        $action = $this->createAction(ScheduledAction::STATUS_FAILED);

        $this->postJson("/api/v1/actions/{$action->id}/retry");

        Queue::assertPushed(\App\Jobs\DeliverHttpAction::class);
    }

    public function test_retry_returns_updated_action(): void
    {
        $action = $this->createAction(ScheduledAction::STATUS_FAILED);

        $response = $this->postJson("/api/v1/actions/{$action->id}/retry");

        $response->assertStatus(200)
            ->assertJsonPath('action.status', 'executing');
    }

    private function createAction(string $status): ScheduledAction
    {
        return ScheduledAction::create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'name' => 'Test Action',
            'type' => 'http',
            'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
            'intent_payload' => ['execute_at' => now()->subHour()->toIso8601String()],
            'resolution_status' => $status,
            'execute_at_utc' => now()->subHour(),
            'http_request' => ['url' => 'https://example.com', 'method' => 'POST'],
        ]);
    }
}

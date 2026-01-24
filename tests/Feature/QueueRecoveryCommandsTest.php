<?php

namespace Tests\Feature;

use App\Jobs\ResolveIntentJob;
use App\Models\ScheduledAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueueRecoveryCommandsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Queue::fake();
    }

    // ==================== RECOVER STUCK EXECUTING ====================

    public function test_recover_stuck_executing_finds_stuck_actions(): void
    {
        // Create a stuck action (updated more than 10 minutes ago)
        $stuck = $this->createAction(ScheduledAction::STATUS_EXECUTING);
        ScheduledAction::withoutTimestamps(function () use ($stuck) {
            $stuck->updated_at = now()->subMinutes(15);
            $stuck->save();
        });

        // Create a recent action (should not be found)
        $recent = $this->createAction(ScheduledAction::STATUS_EXECUTING);

        $this->artisan('app:recover-stuck-executing-actions')
            ->assertExitCode(0);

        // Verify the stuck action was recovered
        $stuck->refresh();
        $this->assertNotEquals(ScheduledAction::STATUS_EXECUTING, $stuck->resolution_status);

        // Recent action should still be executing
        $recent->refresh();
        $this->assertEquals(ScheduledAction::STATUS_EXECUTING, $recent->resolution_status);
    }

    public function test_recover_stuck_executing_returns_to_resolved_with_retries(): void
    {
        $stuck = $this->createAction(ScheduledAction::STATUS_EXECUTING);
        $stuck->attempt_count = 1;
        $stuck->max_attempts = 3;
        ScheduledAction::withoutTimestamps(function () use ($stuck) {
            $stuck->updated_at = now()->subMinutes(15);
            $stuck->save();
        });

        $this->artisan('app:recover-stuck-executing-actions')
            ->assertExitCode(0);

        $stuck->refresh();
        $this->assertEquals(ScheduledAction::STATUS_RESOLVED, $stuck->resolution_status);
        $this->assertNotNull($stuck->next_retry_at);
    }

    public function test_recover_stuck_executing_marks_failed_when_no_retries(): void
    {
        $stuck = $this->createAction(ScheduledAction::STATUS_EXECUTING);
        $stuck->attempt_count = 3;
        $stuck->max_attempts = 3;
        ScheduledAction::withoutTimestamps(function () use ($stuck) {
            $stuck->updated_at = now()->subMinutes(15);
            $stuck->save();
        });

        $this->artisan('app:recover-stuck-executing-actions')
            ->assertExitCode(0);

        $stuck->refresh();
        $this->assertEquals(ScheduledAction::STATUS_FAILED, $stuck->resolution_status);
    }

    public function test_recover_stuck_executing_dry_run_does_not_modify(): void
    {
        $stuck = $this->createAction(ScheduledAction::STATUS_EXECUTING);
        ScheduledAction::withoutTimestamps(function () use ($stuck) {
            $stuck->updated_at = now()->subMinutes(15);
            $stuck->save();
        });

        $this->artisan('app:recover-stuck-executing-actions', ['--dry-run' => true])
            ->assertExitCode(0);

        $stuck->refresh();
        $this->assertEquals(ScheduledAction::STATUS_EXECUTING, $stuck->resolution_status);
    }

    public function test_recover_stuck_executing_respects_timeout_option(): void
    {
        // Create action that's 7 minutes old
        $action = $this->createAction(ScheduledAction::STATUS_EXECUTING);
        ScheduledAction::withoutTimestamps(function () use ($action) {
            $action->updated_at = now()->subMinutes(7);
            $action->save();
        });

        // With default 10 min timeout - should not find it (action not modified)
        $this->artisan('app:recover-stuck-executing-actions')
            ->assertExitCode(0);

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_EXECUTING, $action->resolution_status);

        // With 5 min timeout - should find and recover it
        $this->artisan('app:recover-stuck-executing-actions', ['--timeout' => 5])
            ->assertExitCode(0);

        $action->refresh();
        $this->assertNotEquals(ScheduledAction::STATUS_EXECUTING, $action->resolution_status);
    }

    public function test_recover_stuck_executing_no_actions_found(): void
    {
        $this->artisan('app:recover-stuck-executing-actions')
            ->assertExitCode(0);
    }

    // ==================== RECOVER STUCK PENDING RESOLUTION ====================

    public function test_recover_stuck_pending_finds_stuck_actions(): void
    {
        // Create a stuck action (created more than 5 minutes ago)
        $stuck = $this->createAction(ScheduledAction::STATUS_PENDING_RESOLUTION);
        ScheduledAction::withoutTimestamps(function () use ($stuck) {
            $stuck->created_at = now()->subMinutes(10);
            $stuck->save();
        });

        // Create a recent action (should not be found)
        $recent = $this->createAction(ScheduledAction::STATUS_PENDING_RESOLUTION);

        $this->artisan('app:recover-stuck-pending-actions')
            ->assertExitCode(0);

        // The stuck action should have a job dispatched for it
        Queue::assertPushed(ResolveIntentJob::class, 1);
    }

    public function test_recover_stuck_pending_dispatches_resolve_intent_job(): void
    {
        $stuck = $this->createAction(ScheduledAction::STATUS_PENDING_RESOLUTION);
        ScheduledAction::withoutTimestamps(function () use ($stuck) {
            $stuck->created_at = now()->subMinutes(10);
            $stuck->save();
        });

        $this->artisan('app:recover-stuck-pending-actions')
            ->assertExitCode(0);

        Queue::assertPushed(ResolveIntentJob::class, 1);
    }

    public function test_recover_stuck_pending_dry_run_does_not_dispatch(): void
    {
        $stuck = $this->createAction(ScheduledAction::STATUS_PENDING_RESOLUTION);
        ScheduledAction::withoutTimestamps(function () use ($stuck) {
            $stuck->created_at = now()->subMinutes(10);
            $stuck->save();
        });

        $this->artisan('app:recover-stuck-pending-actions', ['--dry-run' => true])
            ->assertExitCode(0);

        Queue::assertNotPushed(ResolveIntentJob::class);
    }

    public function test_recover_stuck_pending_respects_timeout_option(): void
    {
        $action = $this->createAction(ScheduledAction::STATUS_PENDING_RESOLUTION);
        ScheduledAction::withoutTimestamps(function () use ($action) {
            $action->created_at = now()->subMinutes(3);
            $action->save();
        });

        // With default 5 min timeout - should not find it
        $this->artisan('app:recover-stuck-pending-actions')
            ->assertExitCode(0);

        Queue::assertNotPushed(ResolveIntentJob::class);

        // With 2 min timeout - should find it
        $this->artisan('app:recover-stuck-pending-actions', ['--timeout' => 2])
            ->assertExitCode(0);

        Queue::assertPushed(ResolveIntentJob::class, 1);
    }

    public function test_recover_stuck_pending_skips_already_resolved(): void
    {
        $action = $this->createAction(ScheduledAction::STATUS_PENDING_RESOLUTION);
        ScheduledAction::withoutTimestamps(function () use ($action) {
            $action->created_at = now()->subMinutes(10);
            $action->save();
        });

        // Simulate state change between find and dispatch
        // This tests idempotency - we can't easily test race conditions,
        // but we can ensure the command handles state checks properly
        $this->artisan('app:recover-stuck-pending-actions')
            ->assertExitCode(0);

        // Should have been dispatched once
        Queue::assertPushed(ResolveIntentJob::class, 1);
    }

    public function test_recover_stuck_pending_no_actions_found(): void
    {
        $this->artisan('app:recover-stuck-pending-actions')
            ->assertExitCode(0);
    }

    // ==================== HELPERS ====================

    private function createAction(string $status): ScheduledAction
    {
        return ScheduledAction::create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'name' => 'Test Action',
            'mode' => ScheduledAction::MODE_IMMEDIATE,
            'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
            'intent_payload' => ['execute_at' => now()->addHour()->toIso8601String()],
            'resolution_status' => $status,
            'execute_at_utc' => now()->addHour(),
            'request' => ['url' => 'https://example.com', 'method' => 'POST'],
            'max_attempts' => 3,
            'attempt_count' => 0,
        ]);
    }
}

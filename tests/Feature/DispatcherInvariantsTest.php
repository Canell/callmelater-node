<?php

namespace Tests\Feature;

use App\Jobs\DeliverHttpAction;
use App\Jobs\DeliverReminder;
use App\Jobs\DispatcherJob;
use App\Models\ScheduledAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DispatcherInvariantsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Queue::fake();
    }

    /**
     * Test: Actions in RESOLVED state are dispatched and transitioned to EXECUTING
     */
    public function test_resolved_actions_are_dispatched_and_transitioned(): void
    {
        $action = $this->createHttpAction(ScheduledAction::STATUS_RESOLVED);

        (new DispatcherJob())->handle();

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_EXECUTING, $action->resolution_status);
        Queue::assertPushed(DeliverHttpAction::class);
    }

    /**
     * Test: Cancelled actions are never dispatched
     */
    public function test_cancelled_actions_are_not_dispatched(): void
    {
        $action = $this->createHttpAction(ScheduledAction::STATUS_CANCELLED);

        (new DispatcherJob())->handle();

        Queue::assertNotPushed(DeliverHttpAction::class);
    }

    /**
     * Test: Awaiting response actions are never dispatched
     */
    public function test_awaiting_response_actions_are_not_dispatched(): void
    {
        $action = $this->createReminderAction(ScheduledAction::STATUS_AWAITING_RESPONSE);

        (new DispatcherJob())->handle();

        Queue::assertNotPushed(DeliverReminder::class);
    }

    /**
     * Test: Already executing actions are not dispatched again
     */
    public function test_executing_actions_are_not_dispatched_again(): void
    {
        $action = $this->createHttpAction(ScheduledAction::STATUS_EXECUTING);

        (new DispatcherJob())->handle();

        Queue::assertNotPushed(DeliverHttpAction::class);
    }

    /**
     * Test: Already executed actions are never dispatched
     */
    public function test_executed_actions_are_not_dispatched(): void
    {
        $action = $this->createHttpAction(ScheduledAction::STATUS_EXECUTED);

        (new DispatcherJob())->handle();

        Queue::assertNotPushed(DeliverHttpAction::class);
    }

    /**
     * Test: Failed actions are not dispatched
     */
    public function test_failed_actions_are_not_dispatched(): void
    {
        $action = $this->createHttpAction(ScheduledAction::STATUS_FAILED);

        (new DispatcherJob())->handle();

        Queue::assertNotPushed(DeliverHttpAction::class);
    }

    /**
     * Test: Only due actions (execute_at_utc <= now) are dispatched
     */
    public function test_future_actions_are_not_dispatched(): void
    {
        $action = ScheduledAction::create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'name' => 'Future Action',
            'type' => ScheduledAction::TYPE_HTTP,
            'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
            'intent_payload' => ['execute_at' => now()->addHour()->toIso8601String()],
            'resolution_status' => ScheduledAction::STATUS_RESOLVED,
            'execute_at_utc' => now()->addHour(), // Future
            'http_request' => ['url' => 'https://example.com', 'method' => 'POST'],
        ]);

        (new DispatcherJob())->handle();

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_RESOLVED, $action->resolution_status);
        Queue::assertNotPushed(DeliverHttpAction::class);
    }

    /**
     * Test: Running dispatcher twice doesn't double-dispatch
     */
    public function test_no_double_dispatch(): void
    {
        $action = $this->createHttpAction(ScheduledAction::STATUS_RESOLVED);

        // First dispatch
        (new DispatcherJob())->handle();

        // Second dispatch (simulating overlapping workers)
        (new DispatcherJob())->handle();

        // Should only be pushed once
        Queue::assertPushed(DeliverHttpAction::class, 1);
    }

    /**
     * Test: Reminder actions dispatch DeliverReminder job
     */
    public function test_reminder_actions_dispatch_deliver_reminder(): void
    {
        $action = $this->createReminderAction(ScheduledAction::STATUS_RESOLVED);

        (new DispatcherJob())->handle();

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_EXECUTING, $action->resolution_status);
        Queue::assertPushed(DeliverReminder::class);
        Queue::assertNotPushed(DeliverHttpAction::class);
    }

    /**
     * Test: Actions with next_retry_at <= now are dispatched
     */
    public function test_retry_actions_are_dispatched(): void
    {
        $action = ScheduledAction::create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'name' => 'Retry Action',
            'type' => ScheduledAction::TYPE_HTTP,
            'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
            'intent_payload' => ['execute_at' => now()->subHour()->toIso8601String()],
            'resolution_status' => ScheduledAction::STATUS_RESOLVED,
            'execute_at_utc' => now()->subHour(), // Past
            'next_retry_at' => now()->subMinute(), // Retry due
            'attempt_count' => 1,
            'http_request' => ['url' => 'https://example.com', 'method' => 'POST'],
        ]);

        (new DispatcherJob())->handle();

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_EXECUTING, $action->resolution_status);
        $this->assertNull($action->next_retry_at); // Cleared after dispatch
        Queue::assertPushed(DeliverHttpAction::class);
    }

    /**
     * Helper to create an HTTP action with given status
     */
    private function createHttpAction(string $status): ScheduledAction
    {
        return ScheduledAction::create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'name' => 'Test HTTP Action',
            'type' => ScheduledAction::TYPE_HTTP,
            'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
            'intent_payload' => ['execute_at' => now()->subMinute()->toIso8601String()],
            'resolution_status' => $status,
            'execute_at_utc' => now()->subMinute(), // Due
            'http_request' => ['url' => 'https://example.com', 'method' => 'POST'],
        ]);
    }

    /**
     * Helper to create a reminder action with given status
     */
    private function createReminderAction(string $status): ScheduledAction
    {
        return ScheduledAction::create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'name' => 'Test Reminder',
            'type' => ScheduledAction::TYPE_REMINDER,
            'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
            'intent_payload' => ['execute_at' => now()->subMinute()->toIso8601String()],
            'resolution_status' => $status,
            'execute_at_utc' => now()->subMinute(), // Due
            'message' => 'Test reminder message',
            'escalation_rules' => ['recipients' => ['test@example.com']],
        ]);
    }
}

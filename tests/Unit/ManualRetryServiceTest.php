<?php

namespace Tests\Unit;

use App\Exceptions\RetryNotAllowedException;
use App\Models\ExecutionCycle;
use App\Models\ScheduledAction;
use App\Models\User;
use App\Services\ManualRetryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ManualRetryServiceTest extends TestCase
{
    use RefreshDatabase;

    private ManualRetryService $retryService;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->retryService = app(ManualRetryService::class);
        $this->user = User::factory()->create();
        Queue::fake();
    }

    public function test_can_retry_returns_true_for_failed_action(): void
    {
        $action = $this->createAction(ScheduledAction::STATUS_FAILED);

        $result = $this->retryService->canRetry($action, $this->user);

        $this->assertTrue($result['allowed']);
        $this->assertEmpty($result['reasons']);
    }

    public function test_can_retry_returns_false_for_non_failed_action(): void
    {
        $action = $this->createAction(ScheduledAction::STATUS_EXECUTED);

        $result = $this->retryService->canRetry($action, $this->user);

        $this->assertFalse($result['allowed']);
        $this->assertContains('Only failed actions can be retried', $result['reasons']);
    }

    public function test_can_retry_returns_false_for_pending_action(): void
    {
        $action = $this->createAction(ScheduledAction::STATUS_PENDING_RESOLUTION);

        $result = $this->retryService->canRetry($action, $this->user);

        $this->assertFalse($result['allowed']);
    }

    public function test_can_retry_respects_rate_limit(): void
    {
        $action = $this->createAction(ScheduledAction::STATUS_FAILED);
        $action->update([
            'manual_retry_count' => 3,
            'last_manual_retry_at' => now(),
        ]);

        $result = $this->retryService->canRetry($action, $this->user);

        $this->assertFalse($result['allowed']);
        $this->assertTrue(
            collect($result['reasons'])->contains(fn ($r) => str_contains($r, 'Maximum 3 manual retries'))
        );
    }

    public function test_rate_limit_resets_after_one_hour(): void
    {
        $action = $this->createAction(ScheduledAction::STATUS_FAILED);
        $action->update([
            'manual_retry_count' => 3,
            'last_manual_retry_at' => now()->subMinutes(61),
        ]);

        $result = $this->retryService->canRetry($action, $this->user);

        $this->assertTrue($result['allowed']);
    }

    public function test_can_retry_checks_quota(): void
    {
        $action = $this->createAction(ScheduledAction::STATUS_FAILED);

        // Use up all quota
        $limit = $this->user->account->getPlanLimit('max_actions_per_month');
        \App\Models\UsageCounter::forCurrentMonth($this->user->account_id)
            ->update(['actions_created' => $limit]);

        $result = $this->retryService->canRetry($action, $this->user);

        $this->assertFalse($result['allowed']);
        $this->assertTrue(
            collect($result['reasons'])->contains(fn ($r) => str_contains($r, 'quota exceeded'))
        );
    }

    public function test_retry_creates_execution_cycle(): void
    {
        $action = $this->createAction(ScheduledAction::STATUS_FAILED);

        $cycle = $this->retryService->retry($action, $this->user);

        $this->assertInstanceOf(ExecutionCycle::class, $cycle);
        $this->assertEquals($action->id, $cycle->action_id);
        $this->assertEquals(ExecutionCycle::TRIGGERED_MANUAL, $cycle->triggered_by);
        $this->assertEquals($this->user->id, $cycle->triggered_by_user_id);
    }

    public function test_retry_resets_action_state(): void
    {
        $action = $this->createAction(ScheduledAction::STATUS_FAILED);
        $action->update([
            'attempt_count' => 5,
            'failure_reason' => 'Previous failure',
        ]);

        $this->retryService->retry($action, $this->user);

        $action->refresh();
        // After retry, action is immediately dispatched and moves to executing status
        $this->assertEquals(ScheduledAction::STATUS_EXECUTING, $action->resolution_status);
        $this->assertEquals(0, $action->attempt_count);
        $this->assertNull($action->failure_reason);
    }

    public function test_retry_increments_manual_retry_count(): void
    {
        $action = $this->createAction(ScheduledAction::STATUS_FAILED);

        $this->retryService->retry($action, $this->user);

        $action->refresh();
        $this->assertEquals(1, $action->manual_retry_count);
        $this->assertNotNull($action->last_manual_retry_at);
    }

    public function test_retry_dispatches_http_job_for_http_action(): void
    {
        $action = $this->createAction(ScheduledAction::STATUS_FAILED, 'http');

        $this->retryService->retry($action, $this->user);

        Queue::assertPushed(\App\Jobs\DeliverHttpAction::class);
    }

    public function test_retry_dispatches_reminder_job_for_reminder_action(): void
    {
        $action = $this->createAction(ScheduledAction::STATUS_FAILED, 'reminder');

        $this->retryService->retry($action, $this->user);

        Queue::assertPushed(\App\Jobs\DeliverReminder::class);
    }

    public function test_retry_throws_exception_when_not_allowed(): void
    {
        $action = $this->createAction(ScheduledAction::STATUS_EXECUTED);

        $this->expectException(RetryNotAllowedException::class);

        $this->retryService->retry($action, $this->user);
    }

    public function test_execution_cycle_numbers_increment(): void
    {
        $action = $this->createAction(ScheduledAction::STATUS_FAILED);

        $cycle1 = $this->retryService->retry($action, $this->user);

        // Reset for second retry
        $action->update(['resolution_status' => ScheduledAction::STATUS_FAILED]);

        $cycle2 = $this->retryService->retry($action, $this->user);

        $this->assertEquals(1, $cycle1->cycle_number);
        $this->assertEquals(2, $cycle2->cycle_number);
    }

    private function createAction(string $status, string $type = 'http'): ScheduledAction
    {
        $data = [
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'name' => 'Test Action',
            'type' => $type,
            'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
            'intent_payload' => ['execute_at' => now()->subHour()->toIso8601String()],
            'resolution_status' => $status,
            'execute_at_utc' => now()->subHour(),
        ];

        if ($type === 'http') {
            $data['http_request'] = ['url' => 'https://example.com', 'method' => 'POST'];
        } else {
            $data['message'] = 'Test reminder';
            $data['escalation_rules'] = ['recipients' => ['test@example.com']];
        }

        return ScheduledAction::create($data);
    }
}

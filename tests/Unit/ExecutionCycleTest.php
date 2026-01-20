<?php

namespace Tests\Unit;

use App\Models\DeliveryAttempt;
use App\Models\ExecutionCycle;
use App\Models\ScheduledAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutionCycleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ScheduledAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->action = $this->createAction();
    }

    public function test_creates_with_uuid(): void
    {
        $cycle = ExecutionCycle::create([
            'action_id' => $this->action->id,
            'cycle_number' => 1,
            'triggered_by' => ExecutionCycle::TRIGGERED_SYSTEM,
            'started_at' => now(),
            'result' => ExecutionCycle::RESULT_IN_PROGRESS,
        ]);

        $this->assertNotNull($cycle->id);
        $this->assertIsString($cycle->id);
        $this->assertEquals(36, strlen($cycle->id)); // UUID length
    }

    public function test_belongs_to_action(): void
    {
        $cycle = ExecutionCycle::create([
            'action_id' => $this->action->id,
            'cycle_number' => 1,
            'triggered_by' => ExecutionCycle::TRIGGERED_SYSTEM,
            'started_at' => now(),
            'result' => ExecutionCycle::RESULT_IN_PROGRESS,
        ]);

        $this->assertEquals($this->action->id, $cycle->action->id);
    }

    public function test_belongs_to_triggered_by_user(): void
    {
        $cycle = ExecutionCycle::create([
            'action_id' => $this->action->id,
            'cycle_number' => 1,
            'triggered_by' => ExecutionCycle::TRIGGERED_MANUAL,
            'triggered_by_user_id' => $this->user->id,
            'started_at' => now(),
            'result' => ExecutionCycle::RESULT_IN_PROGRESS,
        ]);

        $this->assertEquals($this->user->id, $cycle->triggeredByUser->id);
    }

    public function test_triggered_by_user_is_null_for_system_cycles(): void
    {
        $cycle = ExecutionCycle::create([
            'action_id' => $this->action->id,
            'cycle_number' => 1,
            'triggered_by' => ExecutionCycle::TRIGGERED_SYSTEM,
            'started_at' => now(),
            'result' => ExecutionCycle::RESULT_IN_PROGRESS,
        ]);

        $this->assertNull($cycle->triggeredByUser);
    }

    public function test_is_manual_returns_true_for_manual_trigger(): void
    {
        $cycle = ExecutionCycle::create([
            'action_id' => $this->action->id,
            'cycle_number' => 1,
            'triggered_by' => ExecutionCycle::TRIGGERED_MANUAL,
            'triggered_by_user_id' => $this->user->id,
            'started_at' => now(),
            'result' => ExecutionCycle::RESULT_IN_PROGRESS,
        ]);

        $this->assertTrue($cycle->isManual());
    }

    public function test_is_manual_returns_false_for_system_trigger(): void
    {
        $cycle = ExecutionCycle::create([
            'action_id' => $this->action->id,
            'cycle_number' => 1,
            'triggered_by' => ExecutionCycle::TRIGGERED_SYSTEM,
            'started_at' => now(),
            'result' => ExecutionCycle::RESULT_IN_PROGRESS,
        ]);

        $this->assertFalse($cycle->isManual());
    }

    public function test_is_in_progress_returns_true(): void
    {
        $cycle = ExecutionCycle::create([
            'action_id' => $this->action->id,
            'cycle_number' => 1,
            'triggered_by' => ExecutionCycle::TRIGGERED_SYSTEM,
            'started_at' => now(),
            'result' => ExecutionCycle::RESULT_IN_PROGRESS,
        ]);

        $this->assertTrue($cycle->isInProgress());
    }

    public function test_is_in_progress_returns_false_for_completed(): void
    {
        $cycle = ExecutionCycle::create([
            'action_id' => $this->action->id,
            'cycle_number' => 1,
            'triggered_by' => ExecutionCycle::TRIGGERED_SYSTEM,
            'started_at' => now(),
            'result' => ExecutionCycle::RESULT_SUCCESS,
            'completed_at' => now(),
        ]);

        $this->assertFalse($cycle->isInProgress());
    }

    public function test_mark_as_success(): void
    {
        $cycle = ExecutionCycle::create([
            'action_id' => $this->action->id,
            'cycle_number' => 1,
            'triggered_by' => ExecutionCycle::TRIGGERED_SYSTEM,
            'started_at' => now(),
            'result' => ExecutionCycle::RESULT_IN_PROGRESS,
        ]);

        $cycle->markAsSuccess();

        $cycle->refresh();
        $this->assertEquals(ExecutionCycle::RESULT_SUCCESS, $cycle->result);
        $this->assertNotNull($cycle->completed_at);
    }

    public function test_mark_as_failed(): void
    {
        $cycle = ExecutionCycle::create([
            'action_id' => $this->action->id,
            'cycle_number' => 1,
            'triggered_by' => ExecutionCycle::TRIGGERED_SYSTEM,
            'started_at' => now(),
            'result' => ExecutionCycle::RESULT_IN_PROGRESS,
        ]);

        $cycle->markAsFailed('Connection timeout');

        $cycle->refresh();
        $this->assertEquals(ExecutionCycle::RESULT_FAILED, $cycle->result);
        $this->assertEquals('Connection timeout', $cycle->failure_reason);
        $this->assertNotNull($cycle->completed_at);
    }

    public function test_has_many_delivery_attempts(): void
    {
        $cycle = ExecutionCycle::create([
            'action_id' => $this->action->id,
            'cycle_number' => 1,
            'triggered_by' => ExecutionCycle::TRIGGERED_SYSTEM,
            'started_at' => now(),
            'result' => ExecutionCycle::RESULT_IN_PROGRESS,
        ]);

        DeliveryAttempt::create([
            'action_id' => $this->action->id,
            'execution_cycle_id' => $cycle->id,
            'attempt_number' => 1,
            'status' => 'failed',
            'http_response_code' => 500,
            'started_at' => now(),
        ]);

        DeliveryAttempt::create([
            'action_id' => $this->action->id,
            'execution_cycle_id' => $cycle->id,
            'attempt_number' => 2,
            'status' => 'success',
            'http_response_code' => 200,
            'started_at' => now(),
        ]);

        $this->assertEquals(2, $cycle->deliveryAttempts()->count());
    }

    public function test_cycle_numbers_can_increment(): void
    {
        $cycle1 = ExecutionCycle::create([
            'action_id' => $this->action->id,
            'cycle_number' => 1,
            'triggered_by' => ExecutionCycle::TRIGGERED_SYSTEM,
            'started_at' => now(),
            'result' => ExecutionCycle::RESULT_FAILED,
            'completed_at' => now(),
            'failure_reason' => 'First failure',
        ]);

        $cycle2 = ExecutionCycle::create([
            'action_id' => $this->action->id,
            'cycle_number' => 2,
            'triggered_by' => ExecutionCycle::TRIGGERED_MANUAL,
            'triggered_by_user_id' => $this->user->id,
            'started_at' => now(),
            'result' => ExecutionCycle::RESULT_SUCCESS,
            'completed_at' => now(),
        ]);

        $this->assertEquals(1, $cycle1->cycle_number);
        $this->assertEquals(2, $cycle2->cycle_number);
    }

    public function test_constants_are_defined(): void
    {
        $this->assertEquals('system', ExecutionCycle::TRIGGERED_SYSTEM);
        $this->assertEquals('manual', ExecutionCycle::TRIGGERED_MANUAL);
        $this->assertEquals('success', ExecutionCycle::RESULT_SUCCESS);
        $this->assertEquals('failed', ExecutionCycle::RESULT_FAILED);
        $this->assertEquals('in_progress', ExecutionCycle::RESULT_IN_PROGRESS);
    }

    public function test_dates_are_cast_correctly(): void
    {
        $startTime = now()->subMinutes(5);
        $completedTime = now();

        $cycle = ExecutionCycle::create([
            'action_id' => $this->action->id,
            'cycle_number' => 1,
            'triggered_by' => ExecutionCycle::TRIGGERED_SYSTEM,
            'started_at' => $startTime,
            'completed_at' => $completedTime,
            'result' => ExecutionCycle::RESULT_SUCCESS,
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $cycle->started_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $cycle->completed_at);
    }

    private function createAction(): ScheduledAction
    {
        return ScheduledAction::create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'name' => 'Test Action',
            'type' => 'http',
            'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
            'intent_payload' => ['execute_at' => now()->subHour()->toIso8601String()],
            'resolution_status' => ScheduledAction::STATUS_FAILED,
            'execute_at_utc' => now()->subHour(),
            'http_request' => ['url' => 'https://example.com', 'method' => 'POST'],
        ]);
    }
}

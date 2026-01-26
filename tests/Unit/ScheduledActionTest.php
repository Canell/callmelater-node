<?php

namespace Tests\Unit;

use App\Models\ScheduledAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduledActionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // ==================== MODE HELPERS ====================

    public function test_is_immediate_returns_true_for_immediate_mode(): void
    {
        $action = $this->createAction(['mode' => ScheduledAction::MODE_IMMEDIATE]);
        $this->assertTrue($action->isImmediate());
        $this->assertFalse($action->isGated());
    }

    public function test_is_gated_returns_true_for_gated_mode(): void
    {
        $action = $this->createAction(['mode' => ScheduledAction::MODE_GATED]);
        $this->assertTrue($action->isGated());
        $this->assertFalse($action->isImmediate());
    }

    public function test_has_request_returns_true_when_request_configured(): void
    {
        $action = $this->createAction(['request' => ['url' => 'https://example.com']]);
        $this->assertTrue($action->hasRequest());
    }

    public function test_has_request_returns_false_when_no_request(): void
    {
        $action = $this->createAction(['request' => null]);
        $this->assertFalse($action->hasRequest());
    }

    // ==================== GATE HELPERS ====================

    public function test_gate_passed_returns_false_initially(): void
    {
        $action = $this->createGatedAction();
        $this->assertFalse($action->gatePassed());
    }

    public function test_gate_passed_returns_true_after_set(): void
    {
        $action = $this->createGatedAction(['gate_passed_at' => now()]);
        $this->assertTrue($action->gatePassed());
    }

    public function test_get_gate_message_returns_message(): void
    {
        $action = $this->createGatedAction();
        $this->assertEquals('Test gate message', $action->getGateMessage());
    }

    public function test_get_gate_recipients_returns_recipients(): void
    {
        $action = $this->createGatedAction();
        $this->assertEquals(['test@example.com'], $action->getGateRecipients());
    }

    public function test_get_gate_channels_defaults_to_email(): void
    {
        $action = $this->createGatedAction(['gate' => ['message' => 'Test', 'recipients' => ['a@b.com']]]);
        $this->assertEquals(['email'], $action->getGateChannels());
    }

    public function test_get_gate_timeout_defaults_to_7d(): void
    {
        $action = $this->createGatedAction(['gate' => ['message' => 'Test', 'recipients' => ['a@b.com']]]);
        $this->assertEquals('7d', $action->getGateTimeout());
    }

    public function test_get_max_snoozes_defaults_to_5(): void
    {
        $action = $this->createGatedAction(['gate' => ['message' => 'Test', 'recipients' => ['a@b.com']]]);
        $this->assertEquals(5, $action->getMaxSnoozes());
    }

    public function test_get_confirmation_mode_defaults_to_first_response(): void
    {
        $action = $this->createGatedAction();
        $this->assertEquals(ScheduledAction::CONFIRMATION_FIRST_RESPONSE, $action->getConfirmationMode());
    }

    public function test_can_snooze_returns_true_when_under_limit(): void
    {
        $action = $this->createGatedAction(['snooze_count' => 2]);
        $this->assertTrue($action->canSnooze());
    }

    public function test_can_snooze_returns_false_when_at_limit(): void
    {
        $action = $this->createGatedAction(['snooze_count' => 5]);
        $this->assertFalse($action->canSnooze());
    }

    // ==================== STATE MACHINE - STATUS CHECKS ====================

    public function test_is_terminal_returns_true_for_terminal_states(): void
    {
        foreach (ScheduledAction::TERMINAL_STATUSES as $status) {
            $action = $this->createAction(['resolution_status' => $status]);
            $this->assertTrue($action->isTerminal(), "Status {$status} should be terminal");
        }
    }

    public function test_is_terminal_returns_false_for_non_terminal_states(): void
    {
        $nonTerminal = [
            ScheduledAction::STATUS_PENDING_RESOLUTION,
            ScheduledAction::STATUS_RESOLVED,
            ScheduledAction::STATUS_EXECUTING,
            ScheduledAction::STATUS_AWAITING_RESPONSE,
        ];

        foreach ($nonTerminal as $status) {
            $action = $this->createAction(['resolution_status' => $status]);
            $this->assertFalse($action->isTerminal(), "Status {$status} should not be terminal");
        }
    }

    public function test_is_executing_returns_true_when_executing(): void
    {
        $action = $this->createAction(['resolution_status' => ScheduledAction::STATUS_EXECUTING]);
        $this->assertTrue($action->isExecuting());
    }

    public function test_is_resolved_returns_true_when_resolved(): void
    {
        $action = $this->createAction(['resolution_status' => ScheduledAction::STATUS_RESOLVED]);
        $this->assertTrue($action->isResolved());
    }

    public function test_is_executed_returns_true_when_executed(): void
    {
        $action = $this->createAction(['resolution_status' => ScheduledAction::STATUS_EXECUTED]);
        $this->assertTrue($action->isExecuted());
    }

    public function test_can_be_executed_returns_true_only_for_resolved(): void
    {
        $resolved = $this->createAction(['resolution_status' => ScheduledAction::STATUS_RESOLVED]);
        $this->assertTrue($resolved->canBeExecuted());

        $pending = $this->createAction(['resolution_status' => ScheduledAction::STATUS_PENDING_RESOLUTION]);
        $this->assertFalse($pending->canBeExecuted());
    }

    public function test_can_be_manually_retried_returns_true_only_for_failed(): void
    {
        $failed = $this->createAction(['resolution_status' => ScheduledAction::STATUS_FAILED]);
        $this->assertTrue($failed->canBeManuallyRetried());

        $executed = $this->createAction(['resolution_status' => ScheduledAction::STATUS_EXECUTED]);
        $this->assertFalse($executed->canBeManuallyRetried());
    }

    // ==================== STATE MACHINE - VALID TRANSITIONS ====================

    public function test_can_transition_from_pending_to_resolved(): void
    {
        $action = $this->createAction(['resolution_status' => ScheduledAction::STATUS_PENDING_RESOLUTION]);
        $this->assertTrue($action->canTransitionTo(ScheduledAction::STATUS_RESOLVED));
    }

    public function test_can_transition_from_pending_to_cancelled(): void
    {
        $action = $this->createAction(['resolution_status' => ScheduledAction::STATUS_PENDING_RESOLUTION]);
        $this->assertTrue($action->canTransitionTo(ScheduledAction::STATUS_CANCELLED));
    }

    public function test_cannot_transition_from_pending_to_executed(): void
    {
        $action = $this->createAction(['resolution_status' => ScheduledAction::STATUS_PENDING_RESOLUTION]);
        $this->assertFalse($action->canTransitionTo(ScheduledAction::STATUS_EXECUTED));
    }

    public function test_can_transition_from_resolved_to_executing(): void
    {
        $action = $this->createAction(['resolution_status' => ScheduledAction::STATUS_RESOLVED]);
        $this->assertTrue($action->canTransitionTo(ScheduledAction::STATUS_EXECUTING));
    }

    public function test_can_transition_from_executing_to_executed(): void
    {
        $action = $this->createAction(['resolution_status' => ScheduledAction::STATUS_EXECUTING]);
        $this->assertTrue($action->canTransitionTo(ScheduledAction::STATUS_EXECUTED));
    }

    public function test_can_transition_from_executing_to_failed(): void
    {
        $action = $this->createAction(['resolution_status' => ScheduledAction::STATUS_EXECUTING]);
        $this->assertTrue($action->canTransitionTo(ScheduledAction::STATUS_FAILED));
    }

    public function test_can_transition_from_executing_to_resolved_for_retry(): void
    {
        $action = $this->createAction(['resolution_status' => ScheduledAction::STATUS_EXECUTING]);
        $this->assertTrue($action->canTransitionTo(ScheduledAction::STATUS_RESOLVED));
    }

    public function test_can_transition_from_failed_to_resolved_for_manual_retry(): void
    {
        $action = $this->createAction(['resolution_status' => ScheduledAction::STATUS_FAILED]);
        $this->assertTrue($action->canTransitionTo(ScheduledAction::STATUS_RESOLVED));
    }

    public function test_cannot_transition_from_executed(): void
    {
        $action = $this->createAction(['resolution_status' => ScheduledAction::STATUS_EXECUTED]);
        $this->assertFalse($action->canTransitionTo(ScheduledAction::STATUS_RESOLVED));
        $this->assertFalse($action->canTransitionTo(ScheduledAction::STATUS_CANCELLED));
    }

    // ==================== STATE MACHINE - TRANSITION METHODS ====================

    public function test_mark_as_executing_transitions_state(): void
    {
        $action = $this->createAction(['resolution_status' => ScheduledAction::STATUS_RESOLVED]);
        $action->markAsExecuting();

        $this->assertEquals(ScheduledAction::STATUS_EXECUTING, $action->resolution_status);
    }

    public function test_mark_as_executed_transitions_and_sets_timestamp(): void
    {
        $action = $this->createAction(['resolution_status' => ScheduledAction::STATUS_EXECUTING]);
        $action->markAsExecuted();

        $this->assertEquals(ScheduledAction::STATUS_EXECUTED, $action->resolution_status);
        $this->assertNotNull($action->executed_at_utc);
    }

    public function test_mark_as_failed_transitions_and_sets_reason(): void
    {
        $action = $this->createAction(['resolution_status' => ScheduledAction::STATUS_EXECUTING]);
        $action->markAsFailed('Test failure reason');

        $this->assertEquals(ScheduledAction::STATUS_FAILED, $action->resolution_status);
        $this->assertEquals('Test failure reason', $action->failure_reason);
    }

    public function test_mark_as_awaiting_response_sets_token_expiry(): void
    {
        $action = $this->createAction(['resolution_status' => ScheduledAction::STATUS_EXECUTING]);
        $action->markAsAwaitingResponse(5);

        $this->assertEquals(ScheduledAction::STATUS_AWAITING_RESPONSE, $action->resolution_status);
        $this->assertNotNull($action->token_expires_at);
        // Should expire in approximately 5 days
        $this->assertTrue($action->token_expires_at->greaterThan(now()->addDays(4)));
        $this->assertTrue($action->token_expires_at->lessThan(now()->addDays(6)));
    }

    public function test_cancel_throws_exception_for_invalid_state(): void
    {
        $action = $this->createAction(['resolution_status' => ScheduledAction::STATUS_EXECUTED]);

        $this->expectException(\InvalidArgumentException::class);
        $action->cancel();
    }

    public function test_cancel_succeeds_for_valid_state(): void
    {
        $action = $this->createAction(['resolution_status' => ScheduledAction::STATUS_RESOLVED]);
        $action->cancel();

        $this->assertEquals(ScheduledAction::STATUS_CANCELLED, $action->resolution_status);
    }

    public function test_cancel_and_replace_sets_replaced_by(): void
    {
        $action = $this->createAction(['resolution_status' => ScheduledAction::STATUS_RESOLVED]);
        $replacement = $this->createAction(['resolution_status' => ScheduledAction::STATUS_RESOLVED]);

        $action->cancelAndReplace($replacement->id);

        $this->assertEquals(ScheduledAction::STATUS_CANCELLED, $action->resolution_status);
        $this->assertEquals($replacement->id, $action->replaced_by_action_id);
    }

    // ==================== GATE PASSING ====================

    public function test_pass_gate_with_request_transitions_to_resolved(): void
    {
        $action = $this->createGatedAction([
            'resolution_status' => ScheduledAction::STATUS_AWAITING_RESPONSE,
            'request' => ['url' => 'https://example.com'],
        ]);

        $action->passGate();

        $this->assertEquals(ScheduledAction::STATUS_RESOLVED, $action->resolution_status);
        $this->assertNotNull($action->gate_passed_at);
        $this->assertNotNull($action->execute_at_utc);
    }

    public function test_pass_gate_without_request_transitions_to_executed(): void
    {
        $action = $this->createGatedAction([
            'resolution_status' => ScheduledAction::STATUS_AWAITING_RESPONSE,
            'request' => null,
        ]);

        $action->passGate();

        $this->assertEquals(ScheduledAction::STATUS_EXECUTED, $action->resolution_status);
        $this->assertNotNull($action->gate_passed_at);
        $this->assertNotNull($action->executed_at_utc);
    }

    // ==================== RETRY LOGIC ====================

    public function test_can_retry_returns_true_when_attempts_remain(): void
    {
        $action = $this->createAction(['attempt_count' => 2, 'max_attempts' => 5]);
        $this->assertTrue($action->canRetry());
    }

    public function test_can_retry_returns_false_when_max_reached(): void
    {
        $action = $this->createAction(['attempt_count' => 5, 'max_attempts' => 5]);
        $this->assertFalse($action->canRetry());
    }

    public function test_should_retry_checks_attempts(): void
    {
        $action = $this->createAction(['attempt_count' => 1, 'max_attempts' => 3]);
        $this->assertTrue($action->shouldRetry());

        $action->attempt_count = 3;
        $this->assertFalse($action->shouldRetry());
    }

    public function test_record_attempt_increments_count(): void
    {
        $action = $this->createAction(['attempt_count' => 1]);
        $action->recordAttempt();

        $this->assertEquals(2, $action->attempt_count);
        $this->assertNotNull($action->last_attempt_at);
    }

    public function test_schedule_next_retry_sets_next_retry_at(): void
    {
        $action = $this->createAction([
            'resolution_status' => ScheduledAction::STATUS_EXECUTING,
            'attempt_count' => 1,
            'max_attempts' => 5,
        ]);

        $action->scheduleNextRetry();

        $this->assertEquals(ScheduledAction::STATUS_RESOLVED, $action->resolution_status);
        $this->assertNotNull($action->next_retry_at);
    }

    public function test_schedule_next_retry_fails_when_max_attempts_reached(): void
    {
        $action = $this->createAction([
            'resolution_status' => ScheduledAction::STATUS_EXECUTING,
            'attempt_count' => 5,
            'max_attempts' => 5,
        ]);

        $action->scheduleNextRetry();

        $this->assertEquals(ScheduledAction::STATUS_FAILED, $action->resolution_status);
        $this->assertStringContainsString('Max retry', $action->failure_reason);
    }

    public function test_exponential_backoff_delay(): void
    {
        // First attempt: 1 min delay (60 seconds)
        $action = $this->createAction([
            'attempt_count' => 0,
            'retry_strategy' => 'exponential',
            'resolution_status' => ScheduledAction::STATUS_EXECUTING,
        ]);
        $before = now();
        $action->scheduleNextRetry();

        $this->assertNotNull($action->next_retry_at);
        $this->assertTrue($action->next_retry_at->greaterThan($before));
        // Should be ~60 seconds in the future (use abs to handle Carbon's signed diff)
        $delay1 = abs($before->diffInSeconds($action->next_retry_at));
        $this->assertGreaterThanOrEqual(55, $delay1);
        $this->assertLessThanOrEqual(65, $delay1);

        // Second attempt: 5 min delay (300 seconds)
        $action2 = $this->createAction([
            'attempt_count' => 1,
            'retry_strategy' => 'exponential',
            'resolution_status' => ScheduledAction::STATUS_EXECUTING,
        ]);
        $before2 = now();
        $action2->scheduleNextRetry();
        $delay2 = abs($before2->diffInSeconds($action2->next_retry_at));
        $this->assertGreaterThanOrEqual(295, $delay2);
        $this->assertLessThanOrEqual(305, $delay2);
    }

    public function test_linear_backoff_delay(): void
    {
        $action = $this->createAction([
            'attempt_count' => 2,
            'retry_strategy' => 'linear',
            'resolution_status' => ScheduledAction::STATUS_EXECUTING,
        ]);
        $before = now();
        $action->scheduleNextRetry();

        // Linear: 5 min * attempt_count = 10 min (600 sec)
        $delay = abs($before->diffInSeconds($action->next_retry_at));
        $this->assertGreaterThanOrEqual(595, $delay);
        $this->assertLessThanOrEqual(605, $delay);
    }

    // ==================== TIMEOUT PARSING ====================

    public function test_parse_timeout_hours(): void
    {
        $this->assertEquals(1, ScheduledAction::parseTimeoutToDays('4h'));
        $this->assertEquals(1, ScheduledAction::parseTimeoutToDays('12h'));
        $this->assertEquals(1, ScheduledAction::parseTimeoutToDays('24h'));
        $this->assertEquals(2, ScheduledAction::parseTimeoutToDays('48h'));
    }

    public function test_parse_timeout_days(): void
    {
        $this->assertEquals(1, ScheduledAction::parseTimeoutToDays('1d'));
        $this->assertEquals(7, ScheduledAction::parseTimeoutToDays('7d'));
        $this->assertEquals(30, ScheduledAction::parseTimeoutToDays('30d'));
    }

    public function test_parse_timeout_weeks(): void
    {
        $this->assertEquals(7, ScheduledAction::parseTimeoutToDays('1w'));
        $this->assertEquals(14, ScheduledAction::parseTimeoutToDays('2w'));
    }

    public function test_parse_timeout_invalid_returns_default(): void
    {
        $this->assertEquals(7, ScheduledAction::parseTimeoutToDays('invalid'));
        $this->assertEquals(7, ScheduledAction::parseTimeoutToDays(''));
    }

    // ==================== SCOPES ====================

    public function test_scope_resolved(): void
    {
        $this->createAction(['resolution_status' => ScheduledAction::STATUS_RESOLVED]);
        $this->createAction(['resolution_status' => ScheduledAction::STATUS_PENDING_RESOLUTION]);
        $this->createAction(['resolution_status' => ScheduledAction::STATUS_EXECUTED]);

        $resolved = ScheduledAction::resolved()->get();
        $this->assertCount(1, $resolved);
        $this->assertEquals(ScheduledAction::STATUS_RESOLVED, $resolved->first()->resolution_status);
    }

    public function test_scope_due(): void
    {
        $due = $this->createAction(['execute_at_utc' => now()->subMinute()]);
        $future = $this->createAction(['execute_at_utc' => now()->addHour()]);
        $retry = $this->createAction(['execute_at_utc' => now()->addHour(), 'next_retry_at' => now()->subMinute()]);

        $dueActions = ScheduledAction::due()->get();
        $this->assertCount(2, $dueActions);
        $this->assertTrue($dueActions->contains('id', $due->id));
        $this->assertTrue($dueActions->contains('id', $retry->id));
        $this->assertFalse($dueActions->contains('id', $future->id));
    }

    public function test_scope_for_account(): void
    {
        $otherUser = User::factory()->create();
        $myAction = $this->createAction();
        $otherAction = ScheduledAction::create([
            'account_id' => $otherUser->account_id,
            'created_by_user_id' => $otherUser->id,
            'name' => 'Other',
            'mode' => ScheduledAction::MODE_IMMEDIATE,
            'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
            'intent_payload' => [],
            'resolution_status' => ScheduledAction::STATUS_RESOLVED,
            'request' => ['url' => 'https://example.com'],
        ]);

        $myActions = ScheduledAction::forAccount($this->user->account_id)->get();
        $this->assertCount(1, $myActions);
        $this->assertEquals($myAction->id, $myActions->first()->id);
    }

    // ==================== CASTS ====================

    public function test_casts_arrays_correctly(): void
    {
        $action = $this->createAction([
            'intent_payload' => ['delay' => '1h'],
            'request' => ['url' => 'https://example.com'],
            'coordination_config' => ['on_create' => 'replace_existing'],
        ]);

        $action->refresh();

        $this->assertIsArray($action->intent_payload);
        $this->assertIsArray($action->request);
        $this->assertIsArray($action->coordination_config);
    }

    public function test_casts_datetimes_correctly(): void
    {
        $action = $this->createAction([
            'execute_at_utc' => now(),
            'executed_at_utc' => now(),
            'last_attempt_at' => now(),
            'next_retry_at' => now(),
            'token_expires_at' => now(),
        ]);

        $action->refresh();

        $this->assertInstanceOf(\Carbon\Carbon::class, $action->execute_at_utc);
        $this->assertInstanceOf(\Carbon\Carbon::class, $action->executed_at_utc);
        $this->assertInstanceOf(\Carbon\Carbon::class, $action->last_attempt_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $action->next_retry_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $action->token_expires_at);
    }

    // ==================== HELPERS ====================

    private function createAction(array $attributes = []): ScheduledAction
    {
        return ScheduledAction::create(array_merge([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'name' => 'Test Action',
            'mode' => ScheduledAction::MODE_IMMEDIATE,
            'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
            'intent_payload' => [],
            'resolution_status' => ScheduledAction::STATUS_RESOLVED,
            'execute_at_utc' => now()->addHour(),
            'request' => ['url' => 'https://example.com', 'method' => 'POST'],
            'max_attempts' => 5,
            'attempt_count' => 0,
        ], $attributes));
    }

    private function createGatedAction(array $attributes = []): ScheduledAction
    {
        return ScheduledAction::create(array_merge([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'name' => 'Test Gated Action',
            'mode' => ScheduledAction::MODE_GATED,
            'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
            'intent_payload' => [],
            'resolution_status' => ScheduledAction::STATUS_RESOLVED,
            'execute_at_utc' => now()->addHour(),
            'gate' => [
                'message' => 'Test gate message',
                'recipients' => ['test@example.com'],
                'channels' => ['email'],
                'timeout' => '7d',
                'on_timeout' => 'cancel',
                'max_snoozes' => 5,
            ],
        ], $attributes));
    }
}

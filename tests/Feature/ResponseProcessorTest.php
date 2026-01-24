<?php

namespace Tests\Feature;

use App\Jobs\DeliverReminderCallback;
use App\Models\ReminderEvent;
use App\Models\ReminderRecipient;
use App\Models\ScheduledAction;
use App\Models\User;
use App\Services\ResponseProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ResponseProcessorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ResponseProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->processor = app(ResponseProcessor::class);
        Queue::fake();
        Mail::fake();
    }

    // ==================== CONFIRM HANDLING ====================

    public function test_confirm_updates_recipient_status(): void
    {
        $action = $this->createAwaitingAction();
        $recipient = $this->createRecipient($action);

        $this->processor->handleConfirm($recipient, $action);

        $recipient->refresh();
        $this->assertEquals(ReminderRecipient::STATUS_CONFIRMED, $recipient->status);
        $this->assertNotNull($recipient->responded_at);
    }

    public function test_confirm_creates_confirmed_event(): void
    {
        $action = $this->createAwaitingAction();
        $recipient = $this->createRecipient($action);

        $this->processor->handleConfirm($recipient, $action);

        $event = ReminderEvent::where('reminder_id', $action->id)
            ->where('event_type', ReminderEvent::TYPE_CONFIRMED)
            ->first();

        $this->assertNotNull($event);
        $this->assertEquals($recipient->email, $event->actor_email);
    }

    public function test_confirm_first_response_marks_action_executed(): void
    {
        $action = $this->createAwaitingAction([
            'confirmation_mode' => ScheduledAction::CONFIRMATION_FIRST_RESPONSE,
        ]);
        $recipient = $this->createRecipient($action);

        $this->processor->handleConfirm($recipient, $action);

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_EXECUTED, $action->resolution_status);
        $this->assertNotNull($action->executed_at_utc);
    }

    public function test_confirm_all_required_waits_for_all_recipients(): void
    {
        $action = $this->createAwaitingAction([
            'confirmation_mode' => ScheduledAction::CONFIRMATION_ALL_REQUIRED,
        ]);
        $recipient1 = $this->createRecipient($action, 'user1@example.com');
        $recipient2 = $this->createRecipient($action, 'user2@example.com');

        // First confirmation
        $this->processor->handleConfirm($recipient1, $action);

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_AWAITING_RESPONSE, $action->resolution_status);

        // Second confirmation
        $this->processor->handleConfirm($recipient2, $action);

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_EXECUTED, $action->resolution_status);
    }

    public function test_confirm_dispatches_callback_when_configured(): void
    {
        $action = $this->createAwaitingAction([
            'callback_url' => 'https://example.com/callback',
        ]);
        $recipient = $this->createRecipient($action);

        $this->processor->handleConfirm($recipient, $action);

        Queue::assertPushed(DeliverReminderCallback::class, function ($job) {
            return $job->response === 'confirm';
        });
    }

    // ==================== DECLINE HANDLING ====================

    public function test_decline_updates_recipient_status(): void
    {
        $action = $this->createAwaitingAction();
        $recipient = $this->createRecipient($action);

        $this->processor->handleDecline($recipient, $action);

        $recipient->refresh();
        $this->assertEquals(ReminderRecipient::STATUS_DECLINED, $recipient->status);
        $this->assertNotNull($recipient->responded_at);
    }

    public function test_decline_creates_declined_event(): void
    {
        $action = $this->createAwaitingAction();
        $recipient = $this->createRecipient($action);

        $this->processor->handleDecline($recipient, $action);

        $event = ReminderEvent::where('reminder_id', $action->id)
            ->where('event_type', ReminderEvent::TYPE_DECLINED)
            ->first();

        $this->assertNotNull($event);
    }

    public function test_decline_first_response_marks_action_executed(): void
    {
        $action = $this->createAwaitingAction([
            'confirmation_mode' => ScheduledAction::CONFIRMATION_FIRST_RESPONSE,
        ]);
        $recipient = $this->createRecipient($action);

        $this->processor->handleDecline($recipient, $action);

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_EXECUTED, $action->resolution_status);
    }

    public function test_decline_sends_notification_to_owner(): void
    {
        $action = $this->createAwaitingAction();
        $recipient = $this->createRecipient($action);

        $this->processor->handleDecline($recipient, $action);

        Mail::assertQueued(\App\Mail\ReminderDeclinedMail::class, function ($mail) use ($action) {
            return $mail->action->id === $action->id;
        });
    }

    public function test_decline_dispatches_callback_when_configured(): void
    {
        $action = $this->createAwaitingAction([
            'callback_url' => 'https://example.com/callback',
        ]);
        $recipient = $this->createRecipient($action);

        $this->processor->handleDecline($recipient, $action);

        Queue::assertPushed(DeliverReminderCallback::class, function ($job) {
            return $job->response === 'decline';
        });
    }

    // ==================== SNOOZE HANDLING ====================

    public function test_snooze_updates_recipient_status(): void
    {
        $action = $this->createAwaitingAction(['max_snoozes' => 5]);
        $recipient = $this->createRecipient($action);

        $this->processor->handleSnooze($recipient, $action, '1h');

        $recipient->refresh();
        $this->assertEquals(ReminderRecipient::STATUS_PENDING, $recipient->status);
    }

    public function test_snooze_creates_snoozed_event(): void
    {
        $action = $this->createAwaitingAction(['max_snoozes' => 5]);
        $recipient = $this->createRecipient($action);

        $this->processor->handleSnooze($recipient, $action, '1h');

        $event = ReminderEvent::where('reminder_id', $action->id)
            ->where('event_type', ReminderEvent::TYPE_SNOOZED)
            ->first();

        $this->assertNotNull($event);
        $this->assertStringContainsString('1h', $event->notes);
    }

    public function test_snooze_resets_all_recipient_statuses(): void
    {
        $action = $this->createAwaitingAction(['max_snoozes' => 5]);
        $recipient1 = $this->createRecipient($action, 'user1@example.com');
        $recipient2 = $this->createRecipient($action, 'user2@example.com');

        // Mark one as sent
        $recipient2->update(['status' => ReminderRecipient::STATUS_SENT]);

        $this->processor->handleSnooze($recipient1, $action, '1h');

        $recipient1->refresh();
        $recipient2->refresh();

        $this->assertEquals(ReminderRecipient::STATUS_PENDING, $recipient1->status);
        $this->assertEquals(ReminderRecipient::STATUS_PENDING, $recipient2->status);
    }

    public function test_snooze_increments_snooze_count(): void
    {
        $action = $this->createAwaitingAction(['max_snoozes' => 5, 'snooze_count' => 0]);
        $recipient = $this->createRecipient($action);

        $this->processor->handleSnooze($recipient, $action, '1h');

        $action->refresh();
        $this->assertEquals(1, $action->snooze_count);
    }

    public function test_snooze_throws_when_max_snoozes_reached(): void
    {
        $action = $this->createAwaitingAction(['max_snoozes' => 3, 'snooze_count' => 3]);
        $recipient = $this->createRecipient($action);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Maximum snoozes reached');

        $this->processor->handleSnooze($recipient, $action, '1h');
    }

    public function test_snooze_dispatches_callback_with_preset(): void
    {
        $action = $this->createAwaitingAction([
            'callback_url' => 'https://example.com/callback',
            'max_snoozes' => 5,
        ]);
        $recipient = $this->createRecipient($action);

        $this->processor->handleSnooze($recipient, $action, '2h');

        Queue::assertPushed(DeliverReminderCallback::class, function ($job) {
            return $job->response === 'snooze' && $job->snoozePreset === '2h';
        });
    }

    // ==================== PROCESS METHOD ====================

    public function test_process_routes_to_confirm(): void
    {
        $action = $this->createAwaitingAction();
        $recipient = $this->createRecipient($action);

        $this->processor->process($recipient, $action, 'confirm');

        $recipient->refresh();
        $this->assertEquals(ReminderRecipient::STATUS_CONFIRMED, $recipient->status);
    }

    public function test_process_routes_to_decline(): void
    {
        $action = $this->createAwaitingAction();
        $recipient = $this->createRecipient($action);

        $this->processor->process($recipient, $action, 'decline');

        $recipient->refresh();
        $this->assertEquals(ReminderRecipient::STATUS_DECLINED, $recipient->status);
    }

    public function test_process_routes_to_snooze(): void
    {
        $action = $this->createAwaitingAction(['max_snoozes' => 5]);
        $recipient = $this->createRecipient($action);

        $this->processor->process($recipient, $action, 'snooze', '4h');

        $event = ReminderEvent::where('reminder_id', $action->id)
            ->where('event_type', ReminderEvent::TYPE_SNOOZED)
            ->first();

        $this->assertNotNull($event);
        $this->assertStringContainsString('4h', $event->notes);
    }

    public function test_process_throws_for_invalid_response(): void
    {
        $action = $this->createAwaitingAction();
        $recipient = $this->createRecipient($action);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid response type');

        $this->processor->process($recipient, $action, 'invalid');
    }

    // ==================== SUCCESS MESSAGES ====================

    public function test_get_success_message_for_confirm(): void
    {
        $message = $this->processor->getSuccessMessage('confirm');

        $this->assertStringContainsString('confirmation', strtolower($message));
    }

    public function test_get_success_message_for_decline(): void
    {
        $message = $this->processor->getSuccessMessage('decline');

        $this->assertStringContainsString('recorded', strtolower($message));
    }

    public function test_get_success_message_for_snooze(): void
    {
        $message = $this->processor->getSuccessMessage('snooze');

        $this->assertStringContainsString('snoozed', strtolower($message));
    }

    // ==================== HELPERS ====================

    private function createAwaitingAction(array $attributes = []): ScheduledAction
    {
        // Extract gate-specific attributes
        $maxSnoozes = $attributes['max_snoozes'] ?? 5;
        $confirmationMode = $attributes['confirmation_mode'] ?? ScheduledAction::CONFIRMATION_FIRST_RESPONSE;
        unset($attributes['max_snoozes'], $attributes['confirmation_mode']);

        return ScheduledAction::create(array_merge([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'name' => 'Test Gated Action',
            'mode' => ScheduledAction::MODE_GATED,
            'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
            'intent_payload' => ['execute_at' => now()->subHour()->toIso8601String()],
            'resolution_status' => ScheduledAction::STATUS_AWAITING_RESPONSE,
            'execute_at_utc' => now()->subHour(),
            'gate' => [
                'message' => 'Please confirm this action',
                'recipients' => ['test@example.com'],
                'channels' => ['email'],
                'timeout' => '7d',
                'on_timeout' => 'cancel',
                'max_snoozes' => $maxSnoozes,
                'confirmation_mode' => $confirmationMode,
            ],
            'snooze_count' => 0,
            'token_expires_at' => now()->addDays(7),
        ], $attributes));
    }

    private function createRecipient(ScheduledAction $action, string $email = 'test@example.com'): ReminderRecipient
    {
        return ReminderRecipient::create([
            'action_id' => $action->id,
            'email' => $email,
            'status' => ReminderRecipient::STATUS_SENT,
            'response_token' => 'test_token_'.uniqid(),
        ]);
    }
}

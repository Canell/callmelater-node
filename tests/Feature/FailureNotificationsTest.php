<?php

namespace Tests\Feature;

use App\Mail\ActionFailedMail;
use App\Mail\ReminderDeclinedMail;
use App\Mail\ReminderExpiredMail;
use App\Models\ReminderRecipient;
use App\Models\ScheduledAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FailureNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Mail::fake();
    }

    // ==================== ACTION FAILED MAIL ====================

    public function test_action_failed_mail_sent_on_mark_as_failed(): void
    {
        $action = $this->createImmediateAction(ScheduledAction::STATUS_EXECUTING);

        $action->markAsFailed('Test failure reason');

        Mail::assertQueued(ActionFailedMail::class, function ($mail) use ($action) {
            return $mail->action->id === $action->id;
        });
    }

    public function test_action_failed_mail_sent_to_owner_email(): void
    {
        $action = $this->createImmediateAction(ScheduledAction::STATUS_EXECUTING);

        $action->markAsFailed('Test failure reason');

        Mail::assertQueued(ActionFailedMail::class, function ($mail) {
            return $mail->hasTo($this->user->email);
        });
    }

    public function test_action_failed_mail_contains_action_name(): void
    {
        $action = $this->createImmediateAction(ScheduledAction::STATUS_EXECUTING);
        $action->update(['name' => 'My Important Webhook']);

        $mail = new ActionFailedMail($action);

        $this->assertStringContainsString('My Important Webhook', $mail->envelope()->subject);
    }

    public function test_action_failed_mail_contains_action_url(): void
    {
        $action = $this->createImmediateAction(ScheduledAction::STATUS_EXECUTING);

        $mail = new ActionFailedMail($action);

        $this->assertStringContainsString($action->id, $mail->actionUrl);
    }

    // ==================== REMINDER DECLINED MAIL ====================

    public function test_reminder_declined_mail_sent_on_decline(): void
    {
        $action = $this->createGatedAction(ScheduledAction::STATUS_AWAITING_RESPONSE);
        $recipient = $this->createRecipient($action);

        $processor = app(\App\Services\ResponseProcessor::class);
        $processor->handleDecline($recipient, $action);

        Mail::assertQueued(ReminderDeclinedMail::class, function ($mail) use ($action) {
            return $mail->action->id === $action->id;
        });
    }

    public function test_reminder_declined_mail_contains_decliner_info(): void
    {
        $action = $this->createGatedAction(ScheduledAction::STATUS_AWAITING_RESPONSE);
        $recipient = $this->createRecipient($action, 'decliner@example.com');

        $mail = new ReminderDeclinedMail($action, $recipient);

        $this->assertEquals('decliner@example.com', $mail->recipient->email);
    }

    public function test_reminder_declined_mail_subject_contains_action_name(): void
    {
        $action = $this->createGatedAction(ScheduledAction::STATUS_AWAITING_RESPONSE);
        $action->update(['name' => 'Approve Production Deploy']);
        $recipient = $this->createRecipient($action);

        $mail = new ReminderDeclinedMail($action, $recipient);

        $this->assertStringContainsString('Approve Production Deploy', $mail->envelope()->subject);
        $this->assertStringContainsString('declined', strtolower($mail->envelope()->subject));
    }

    // ==================== REMINDER EXPIRED MAIL ====================

    public function test_reminder_expired_mail_sent_on_expiry(): void
    {
        $action = $this->createGatedAction(ScheduledAction::STATUS_AWAITING_RESPONSE);
        $action->update(['token_expires_at' => now()->subHour()]);

        $this->artisan('app:check-expired-reminders');

        Mail::assertQueued(ReminderExpiredMail::class, function ($mail) use ($action) {
            return $mail->action->id === $action->id;
        });
    }

    public function test_reminder_expired_mail_sent_to_owner(): void
    {
        $action = $this->createGatedAction(ScheduledAction::STATUS_AWAITING_RESPONSE);
        $action->update(['token_expires_at' => now()->subHour()]);

        $this->artisan('app:check-expired-reminders');

        Mail::assertQueued(ReminderExpiredMail::class, function ($mail) {
            return $mail->hasTo($this->user->email);
        });
    }

    public function test_reminder_expired_mail_subject_contains_action_name(): void
    {
        $action = $this->createGatedAction(ScheduledAction::STATUS_AWAITING_RESPONSE);
        $action->update(['name' => 'Weekly Report Approval']);

        $mail = new ReminderExpiredMail($action);

        $this->assertStringContainsString('Weekly Report Approval', $mail->envelope()->subject);
        $this->assertStringContainsString('expired', strtolower($mail->envelope()->subject));
    }

    public function test_reminder_expired_mail_contains_action_url(): void
    {
        $action = $this->createGatedAction(ScheduledAction::STATUS_AWAITING_RESPONSE);

        $mail = new ReminderExpiredMail($action);

        $this->assertStringContainsString($action->id, $mail->actionUrl);
    }

    // ==================== NO NOTIFICATION WHEN NO OWNER ====================

    public function test_no_notification_when_action_owner_has_no_email(): void
    {
        // Create user without email (edge case)
        $userWithoutEmail = User::factory()->create(['email' => '']);
        $action = $this->createImmediateAction(ScheduledAction::STATUS_EXECUTING);
        $action->update(['created_by_user_id' => $userWithoutEmail->id]);
        $action->refresh();

        Mail::fake(); // Reset mail fake

        $action->markAsFailed('Test failure');

        // Should still send because email check is just `if ($owner && $owner->email)`
        // and empty string is falsy. Let's verify no mail sent.
        Mail::assertNotQueued(ActionFailedMail::class);
    }

    // ==================== MAIL CONTENT RENDERING ====================

    public function test_action_failed_mail_renders_without_error(): void
    {
        $action = $this->createImmediateAction(ScheduledAction::STATUS_FAILED);
        $action->update([
            'failure_reason' => 'HTTP 500: Server Error',
            'attempt_count' => 3,
            'max_attempts' => 3,
        ]);

        $mail = new ActionFailedMail($action);

        // Should not throw exception
        $rendered = $mail->render();

        $this->assertNotEmpty($rendered);
    }

    public function test_reminder_declined_mail_renders_without_error(): void
    {
        $action = $this->createGatedAction(ScheduledAction::STATUS_AWAITING_RESPONSE);
        $recipient = $this->createRecipient($action);
        $recipient->update(['responded_at' => now()]);

        $mail = new ReminderDeclinedMail($action, $recipient);

        $rendered = $mail->render();

        $this->assertNotEmpty($rendered);
    }

    public function test_reminder_expired_mail_renders_without_error(): void
    {
        $action = $this->createGatedAction(ScheduledAction::STATUS_EXPIRED);

        $mail = new ReminderExpiredMail($action);

        $rendered = $mail->render();

        $this->assertNotEmpty($rendered);
    }

    // ==================== HELPERS ====================

    private function createImmediateAction(string $status): ScheduledAction
    {
        return ScheduledAction::create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'name' => 'Test HTTP Action',
            'mode' => ScheduledAction::MODE_IMMEDIATE,
            'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
            'intent_payload' => [],
            'resolution_status' => $status,
            'execute_at_utc' => now()->subHour(),
            'request' => ['url' => 'https://example.com/webhook', 'method' => 'POST'],
            'max_attempts' => 3,
            'attempt_count' => 0,
        ]);
    }

    private function createGatedAction(string $status): ScheduledAction
    {
        return ScheduledAction::create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'name' => 'Test Gated Action',
            'mode' => ScheduledAction::MODE_GATED,
            'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
            'intent_payload' => [],
            'resolution_status' => $status,
            'execute_at_utc' => now()->subHour(),
            'gate' => [
                'message' => 'Please confirm this action',
                'recipients' => ['test@example.com'],
                'channels' => ['email'],
                'confirmation_mode' => ScheduledAction::CONFIRMATION_FIRST_RESPONSE,
                'max_snoozes' => 5,
            ],
            'token_expires_at' => now()->addDays(7),
        ]);
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

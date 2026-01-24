<?php

namespace Tests\Feature;

use App\Jobs\DeliverReminder;
use App\Mail\ReminderMail;
use App\Models\BlockedRecipient;
use App\Models\ReminderEvent;
use App\Models\ReminderRecipient;
use App\Models\ScheduledAction;
use App\Models\User;
use App\Services\BrevoService;
use App\Services\QuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class DeliverReminderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private QuotaService $quotaService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();

        // Create a mock QuotaService that allows SMS (no quota checks in tests)
        $this->quotaService = Mockery::mock(QuotaService::class);
        $this->quotaService->shouldReceive('canSendSms')->andReturn(true);
        $this->quotaService->shouldReceive('recordSmsSent')->andReturn(null);

        Mail::fake();
    }

    // ==================== EMAIL DELIVERY ====================

    public function test_sends_email_to_email_recipient(): void
    {
        $action = $this->createGatedAction(
            ScheduledAction::STATUS_EXECUTING,
            ['recipients' => ['test@example.com']]
        );

        $brevoService = Mockery::mock(BrevoService::class);

        $job = new DeliverReminder($action);
        $job->handle($brevoService, $this->quotaService);

        Mail::assertSent(ReminderMail::class, function ($mail) {
            return $mail->hasTo('test@example.com');
        });
    }

    public function test_creates_recipient_record_with_token(): void
    {
        $action = $this->createGatedAction(
            ScheduledAction::STATUS_EXECUTING,
            ['recipients' => ['test@example.com']]
        );

        $brevoService = Mockery::mock(BrevoService::class);

        $job = new DeliverReminder($action);
        $job->handle($brevoService, $this->quotaService);

        $recipient = ReminderRecipient::where('action_id', $action->id)->first();
        $this->assertNotNull($recipient);
        $this->assertEquals('test@example.com', $recipient->email);
        $this->assertNotNull($recipient->response_token);
        $this->assertEquals(ReminderRecipient::STATUS_SENT, $recipient->status);
    }

    public function test_sends_to_multiple_email_recipients(): void
    {
        $action = $this->createGatedAction(
            ScheduledAction::STATUS_EXECUTING,
            ['recipients' => ['user1@example.com', 'user2@example.com', 'user3@example.com']]
        );

        $brevoService = Mockery::mock(BrevoService::class);

        $job = new DeliverReminder($action);
        $job->handle($brevoService, $this->quotaService);

        Mail::assertSent(ReminderMail::class, 3);

        $this->assertEquals(3, ReminderRecipient::where('action_id', $action->id)->count());
    }

    // ==================== SMS DELIVERY ====================

    public function test_sends_sms_to_phone_recipient(): void
    {
        $action = $this->createGatedAction(
            ScheduledAction::STATUS_EXECUTING,
            ['recipients' => ['+15551234567']]
        );

        $brevoService = Mockery::mock(BrevoService::class);
        $brevoService->shouldReceive('sendReminderSms')
            ->once()
            ->with('+15551234567', $action->name, $action->getGateMessage(), Mockery::type('string'))
            ->andReturn('msg_123');

        $job = new DeliverReminder($action);
        $job->handle($brevoService, $this->quotaService);

        // No email should be sent for phone numbers
        Mail::assertNotSent(ReminderMail::class);
    }

    public function test_sends_mixed_email_and_sms(): void
    {
        $action = $this->createGatedAction(
            ScheduledAction::STATUS_EXECUTING,
            ['recipients' => ['test@example.com', '+15551234567']]
        );

        $brevoService = Mockery::mock(BrevoService::class);
        $brevoService->shouldReceive('sendReminderSms')
            ->once()
            ->andReturn('msg_123');

        $job = new DeliverReminder($action);
        $job->handle($brevoService, $this->quotaService);

        Mail::assertSent(ReminderMail::class, 1);

        $recipients = ReminderRecipient::where('action_id', $action->id)->get();
        $this->assertEquals(2, $recipients->count());
    }

    // ==================== BLOCKED RECIPIENTS ====================

    public function test_skips_blocked_email_recipient(): void
    {
        BlockedRecipient::create([
            'recipient' => 'blocked@example.com',
            'reason' => 'Abuse',
            'blocked_by' => 'admin',
        ]);

        $action = $this->createGatedAction(
            ScheduledAction::STATUS_EXECUTING,
            ['recipients' => ['blocked@example.com', 'allowed@example.com']]
        );

        $brevoService = Mockery::mock(BrevoService::class);

        $job = new DeliverReminder($action);
        $job->handle($brevoService, $this->quotaService);

        // Only allowed recipient should receive email
        Mail::assertSent(ReminderMail::class, 1);
        Mail::assertSent(ReminderMail::class, function ($mail) {
            return $mail->hasTo('allowed@example.com');
        });
    }

    public function test_skips_blocked_phone_recipient(): void
    {
        BlockedRecipient::create([
            'recipient' => '+15551234567',
            'reason' => 'Abuse',
            'blocked_by' => 'admin',
        ]);

        $action = $this->createGatedAction(
            ScheduledAction::STATUS_EXECUTING,
            ['recipients' => ['+15551234567']]
        );

        $brevoService = Mockery::mock(BrevoService::class);
        // No SMS should be sent - sendReminderSms should never be called
        $brevoService->shouldNotReceive('sendReminderSms');

        $job = new DeliverReminder($action);
        $job->handle($brevoService, $this->quotaService);

        // Verify no recipient record was marked as sent
        $recipient = ReminderRecipient::where('action_id', $action->id)->first();
        $this->assertNull($recipient);
    }

    public function test_blocked_recipient_case_insensitive(): void
    {
        BlockedRecipient::create([
            'recipient' => 'BLOCKED@EXAMPLE.COM',
            'reason' => 'Abuse',
            'blocked_by' => 'admin',
        ]);

        $action = $this->createGatedAction(
            ScheduledAction::STATUS_EXECUTING,
            ['recipients' => ['blocked@example.com']]
        );

        $brevoService = Mockery::mock(BrevoService::class);

        $job = new DeliverReminder($action);
        $job->handle($brevoService, $this->quotaService);

        Mail::assertNotSent(ReminderMail::class);
    }

    // ==================== STATE TRANSITIONS ====================

    public function test_marks_action_as_awaiting_response(): void
    {
        $action = $this->createGatedAction(
            ScheduledAction::STATUS_EXECUTING,
            ['recipients' => ['test@example.com']]
        );

        $brevoService = Mockery::mock(BrevoService::class);

        $job = new DeliverReminder($action);
        $job->handle($brevoService, $this->quotaService);

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_AWAITING_RESPONSE, $action->resolution_status);
        $this->assertNotNull($action->token_expires_at);
    }

    public function test_uses_custom_token_expiry_from_gate_timeout(): void
    {
        $action = $this->createGatedAction(
            ScheduledAction::STATUS_EXECUTING,
            ['recipients' => ['test@example.com'], 'timeout' => '14d']
        );

        $brevoService = Mockery::mock(BrevoService::class);

        $job = new DeliverReminder($action);
        $job->handle($brevoService, $this->quotaService);

        $action->refresh();
        $expectedExpiry = now()->addDays(14);
        $this->assertTrue($action->token_expires_at->isSameDay($expectedExpiry));
    }

    public function test_creates_sent_event(): void
    {
        $action = $this->createGatedAction(
            ScheduledAction::STATUS_EXECUTING,
            ['recipients' => ['test@example.com']]
        );

        $brevoService = Mockery::mock(BrevoService::class);

        $job = new DeliverReminder($action);
        $job->handle($brevoService, $this->quotaService);

        $event = ReminderEvent::where('reminder_id', $action->id)
            ->where('event_type', ReminderEvent::TYPE_SENT)
            ->first();

        $this->assertNotNull($event);
        $this->assertStringContainsString('1 recipient(s)', $event->notes);
        $this->assertStringContainsString('email', $event->notes);
    }

    // ==================== STATE GUARDS ====================

    public function test_skips_action_no_longer_in_executing_state(): void
    {
        $action = $this->createGatedAction(
            ScheduledAction::STATUS_CANCELLED,
            ['recipients' => ['test@example.com']]
        );

        $brevoService = Mockery::mock(BrevoService::class);

        $job = new DeliverReminder($action);
        $job->handle($brevoService, $this->quotaService);

        Mail::assertNotSent(ReminderMail::class);
        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_CANCELLED, $action->resolution_status);
    }

    // ==================== ERROR HANDLING ====================

    public function test_fails_when_no_recipients_configured(): void
    {
        $action = $this->createGatedAction(
            ScheduledAction::STATUS_EXECUTING,
            ['recipients' => []]
        );

        $brevoService = Mockery::mock(BrevoService::class);

        $job = new DeliverReminder($action);
        $job->handle($brevoService, $this->quotaService);

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_FAILED, $action->resolution_status);
        $this->assertStringContainsString('No recipients configured', $action->failure_reason);
    }

    // ==================== IDEMPOTENCY ====================

    public function test_skips_already_sent_recipients_on_retry(): void
    {
        $action = $this->createGatedAction(
            ScheduledAction::STATUS_EXECUTING,
            ['recipients' => ['test@example.com']]
        );

        // Pre-create recipient as already sent
        ReminderRecipient::create([
            'action_id' => $action->id,
            'email' => 'test@example.com',
            'status' => ReminderRecipient::STATUS_SENT,
            'response_token' => 'existing_token',
        ]);

        $brevoService = Mockery::mock(BrevoService::class);

        $job = new DeliverReminder($action);
        $job->handle($brevoService, $this->quotaService);

        // Should not send again
        Mail::assertNotSent(ReminderMail::class);
    }

    // ==================== PHONE NUMBER DETECTION ====================

    public function test_detects_various_phone_formats(): void
    {
        $action = $this->createGatedAction(
            ScheduledAction::STATUS_EXECUTING,
            ['recipients' => ['+1 (555) 123-4567']]
        );

        $brevoService = Mockery::mock(BrevoService::class);
        $brevoService->shouldReceive('sendReminderSms')
            ->once()
            ->andReturn('msg_123');

        $job = new DeliverReminder($action);
        $job->handle($brevoService, $this->quotaService);

        Mail::assertNotSent(ReminderMail::class);
    }

    public function test_detects_international_phone_format(): void
    {
        $action = $this->createGatedAction(
            ScheduledAction::STATUS_EXECUTING,
            ['recipients' => ['+33612345678']] // French mobile
        );

        $brevoService = Mockery::mock(BrevoService::class);
        $brevoService->shouldReceive('sendReminderSms')
            ->once()
            ->andReturn('msg_123');

        $job = new DeliverReminder($action);
        $job->handle($brevoService, $this->quotaService);

        Mail::assertNotSent(ReminderMail::class);
    }

    // ==================== HELPERS ====================

    private function createGatedAction(string $status, array $gateConfig): ScheduledAction
    {
        $gate = array_merge([
            'message' => 'Please confirm this action',
            'recipients' => [],
            'channels' => ['email'],
            'timeout' => '7d',
            'on_timeout' => 'cancel',
            'max_snoozes' => 5,
        ], $gateConfig);

        return ScheduledAction::create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'name' => 'Test Gated Action',
            'mode' => ScheduledAction::MODE_GATED,
            'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
            'intent_payload' => ['execute_at' => now()->subMinute()->toIso8601String()],
            'resolution_status' => $status,
            'execute_at_utc' => now()->subMinute(),
            'gate' => $gate,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

<?php

namespace Tests\Feature;

use App\Jobs\CheckEscalationsJob;
use App\Mail\EscalationMail;
use App\Models\ReminderEvent;
use App\Models\ReminderRecipient;
use App\Models\ScheduledAction;
use App\Models\User;
use App\Services\BrevoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class CheckEscalationsJobTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Mail::fake();
    }

    public function test_escalates_when_time_elapsed(): void
    {
        $action = $this->createAwaitingReminder([
            'escalation_rules' => [
                'recipients' => ['original@example.com'],
                'escalate_after_hours' => 1,
                'escalation_contacts' => ['manager@example.com'],
            ],
        ]);

        // Create sent event and manually set created_at to 2 hours ago
        $event = ReminderEvent::create([
            'reminder_id' => $action->id,
            'event_type' => ReminderEvent::TYPE_SENT,
        ]);
        $event->created_at = now()->subHours(2);
        $event->save();

        $brevoService = Mockery::mock(BrevoService::class);

        $job = new CheckEscalationsJob();
        $job->handle($brevoService);

        Mail::assertSent(EscalationMail::class, function ($mail) {
            return $mail->hasTo('manager@example.com');
        });
    }

    public function test_creates_escalated_event(): void
    {
        $action = $this->createAwaitingReminder([
            'escalation_rules' => [
                'recipients' => ['original@example.com'],
                'escalate_after_hours' => 1,
                'escalation_contacts' => ['manager@example.com'],
            ],
        ]);

        $event = ReminderEvent::create([
            'reminder_id' => $action->id,
            'event_type' => ReminderEvent::TYPE_SENT,
        ]);
        $event->created_at = now()->subHours(2);
        $event->save();

        $brevoService = Mockery::mock(BrevoService::class);

        $job = new CheckEscalationsJob();
        $job->handle($brevoService);

        $escalatedEvent = ReminderEvent::where('reminder_id', $action->id)
            ->where('event_type', ReminderEvent::TYPE_ESCALATED)
            ->first();

        $this->assertNotNull($escalatedEvent);
        $this->assertStringContainsString('manager@example.com', $escalatedEvent->notes);
    }

    public function test_does_not_escalate_before_time(): void
    {
        $action = $this->createAwaitingReminder([
            'escalation_rules' => [
                'recipients' => ['original@example.com'],
                'escalate_after_hours' => 4,
                'escalation_contacts' => ['manager@example.com'],
            ],
        ]);

        // Sent only 1 hour ago (needs 4 hours)
        $event = ReminderEvent::create([
            'reminder_id' => $action->id,
            'event_type' => ReminderEvent::TYPE_SENT,
        ]);
        $event->created_at = now()->subHour();
        $event->save();

        $brevoService = Mockery::mock(BrevoService::class);

        $job = new CheckEscalationsJob();
        $job->handle($brevoService);

        Mail::assertNotSent(EscalationMail::class);
    }

    public function test_does_not_escalate_twice(): void
    {
        $action = $this->createAwaitingReminder([
            'escalation_rules' => [
                'recipients' => ['original@example.com'],
                'escalate_after_hours' => 1,
                'escalation_contacts' => ['manager@example.com'],
            ],
        ]);

        $sentEvent = ReminderEvent::create([
            'reminder_id' => $action->id,
            'event_type' => ReminderEvent::TYPE_SENT,
        ]);
        $sentEvent->created_at = now()->subHours(2);
        $sentEvent->save();

        // Already escalated
        ReminderEvent::create([
            'reminder_id' => $action->id,
            'event_type' => ReminderEvent::TYPE_ESCALATED,
        ]);

        $brevoService = Mockery::mock(BrevoService::class);

        $job = new CheckEscalationsJob();
        $job->handle($brevoService);

        Mail::assertNotSent(EscalationMail::class);
    }

    public function test_does_not_escalate_without_escalation_config(): void
    {
        $action = $this->createAwaitingReminder([
            'escalation_rules' => [
                'recipients' => ['original@example.com'],
                // No escalate_after_hours or escalation_contacts
            ],
        ]);

        $event = ReminderEvent::create([
            'reminder_id' => $action->id,
            'event_type' => ReminderEvent::TYPE_SENT,
        ]);
        $event->created_at = now()->subHours(10);
        $event->save();

        $brevoService = Mockery::mock(BrevoService::class);

        $job = new CheckEscalationsJob();
        $job->handle($brevoService);

        Mail::assertNotSent(EscalationMail::class);
    }

    public function test_does_not_escalate_executed_reminder(): void
    {
        $action = ScheduledAction::create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'name' => 'Test Reminder',
            'type' => ScheduledAction::TYPE_REMINDER,
            'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
            'intent_payload' => [],
            'resolution_status' => ScheduledAction::STATUS_EXECUTED, // Already executed
            'execute_at_utc' => now()->subDay(),
            'escalation_rules' => [
                'recipients' => ['original@example.com'],
                'escalate_after_hours' => 1,
                'escalation_contacts' => ['manager@example.com'],
            ],
        ]);

        $event = ReminderEvent::create([
            'reminder_id' => $action->id,
            'event_type' => ReminderEvent::TYPE_SENT,
        ]);
        $event->created_at = now()->subHours(2);
        $event->save();

        $brevoService = Mockery::mock(BrevoService::class);

        $job = new CheckEscalationsJob();
        $job->handle($brevoService);

        Mail::assertNotSent(EscalationMail::class);
    }

    public function test_escalates_to_multiple_contacts(): void
    {
        $action = $this->createAwaitingReminder([
            'escalation_rules' => [
                'recipients' => ['original@example.com'],
                'escalate_after_hours' => 1,
                'escalation_contacts' => ['manager1@example.com', 'manager2@example.com'],
            ],
        ]);

        $event = ReminderEvent::create([
            'reminder_id' => $action->id,
            'event_type' => ReminderEvent::TYPE_SENT,
        ]);
        $event->created_at = now()->subHours(2);
        $event->save();

        $brevoService = Mockery::mock(BrevoService::class);

        $job = new CheckEscalationsJob();
        $job->handle($brevoService);

        Mail::assertSent(EscalationMail::class, 2);
    }

    public function test_escalates_to_phone_via_sms(): void
    {
        $action = $this->createAwaitingReminder([
            'escalation_rules' => [
                'recipients' => ['original@example.com'],
                'escalate_after_hours' => 1,
                'escalation_contacts' => ['+15551234567'],
            ],
        ]);

        $event = ReminderEvent::create([
            'reminder_id' => $action->id,
            'event_type' => ReminderEvent::TYPE_SENT,
        ]);
        $event->created_at = now()->subHours(2);
        $event->save();

        $brevoService = Mockery::mock(BrevoService::class);
        $brevoService->shouldReceive('sendSms')
            ->once()
            ->with('+15551234567', Mockery::type('string'))
            ->andReturn('msg_123');

        $job = new CheckEscalationsJob();
        $job->handle($brevoService);

        Mail::assertNotSent(EscalationMail::class);
    }

    public function test_creates_recipient_records_for_escalation_contacts(): void
    {
        $action = $this->createAwaitingReminder([
            'escalation_rules' => [
                'recipients' => ['original@example.com'],
                'escalate_after_hours' => 1,
                'escalation_contacts' => ['manager@example.com'],
            ],
        ]);

        $event = ReminderEvent::create([
            'reminder_id' => $action->id,
            'event_type' => ReminderEvent::TYPE_SENT,
        ]);
        $event->created_at = now()->subHours(2);
        $event->save();

        $brevoService = Mockery::mock(BrevoService::class);

        $job = new CheckEscalationsJob();
        $job->handle($brevoService);

        $recipient = ReminderRecipient::where('action_id', $action->id)
            ->where('email', 'manager@example.com')
            ->first();

        $this->assertNotNull($recipient);
        $this->assertNotNull($recipient->response_token);
    }

    public function test_does_not_escalate_without_sent_event(): void
    {
        $action = $this->createAwaitingReminder([
            'escalation_rules' => [
                'recipients' => ['original@example.com'],
                'escalate_after_hours' => 1,
                'escalation_contacts' => ['manager@example.com'],
            ],
        ]);

        // No sent event created

        $brevoService = Mockery::mock(BrevoService::class);

        $job = new CheckEscalationsJob();
        $job->handle($brevoService);

        Mail::assertNotSent(EscalationMail::class);
    }

    private function createAwaitingReminder(array $attributes = []): ScheduledAction
    {
        return ScheduledAction::create(array_merge([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'name' => 'Test Reminder',
            'type' => ScheduledAction::TYPE_REMINDER,
            'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
            'intent_payload' => [],
            'resolution_status' => ScheduledAction::STATUS_AWAITING_RESPONSE,
            'execute_at_utc' => now()->subDay(),
            'token_expires_at' => now()->addDays(7),
            'escalation_rules' => ['recipients' => ['test@example.com']],
        ], $attributes));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

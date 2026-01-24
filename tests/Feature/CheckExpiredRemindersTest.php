<?php

namespace Tests\Feature;

use App\Models\ReminderEvent;
use App\Models\ScheduledAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CheckExpiredRemindersTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Mail::fake();
    }

    public function test_marks_expired_reminder_as_expired(): void
    {
        $action = $this->createAwaitingGatedAction([
            'token_expires_at' => now()->subHour(),
        ]);

        $this->artisan('app:check-expired-reminders')
            ->assertSuccessful();

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_EXPIRED, $action->resolution_status);
    }

    public function test_creates_expired_event(): void
    {
        $action = $this->createAwaitingGatedAction([
            'token_expires_at' => now()->subHour(),
        ]);

        $this->artisan('app:check-expired-reminders');

        $event = ReminderEvent::where('reminder_id', $action->id)
            ->where('event_type', ReminderEvent::TYPE_EXPIRED)
            ->first();

        $this->assertNotNull($event);
        $this->assertStringContainsString('Token expired', $event->notes);
    }

    public function test_sends_expiry_notification_email(): void
    {
        $action = $this->createAwaitingGatedAction([
            'token_expires_at' => now()->subHour(),
        ]);

        $this->artisan('app:check-expired-reminders');

        Mail::assertQueued(\App\Mail\ReminderExpiredMail::class, function ($mail) use ($action) {
            return $mail->action->id === $action->id;
        });
    }

    public function test_does_not_expire_non_expired_reminder(): void
    {
        $action = $this->createAwaitingGatedAction([
            'token_expires_at' => now()->addHour(),
        ]);

        $this->artisan('app:check-expired-reminders');

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_AWAITING_RESPONSE, $action->resolution_status);
    }

    public function test_does_not_expire_executed_action(): void
    {
        $action = ScheduledAction::create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'name' => 'Test Gated Action',
            'mode' => ScheduledAction::MODE_GATED,
            'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
            'intent_payload' => [],
            'resolution_status' => ScheduledAction::STATUS_EXECUTED,
            'execute_at_utc' => now()->subHour(),
            'token_expires_at' => now()->subHour(), // Expired but already executed
            'gate' => [
                'message' => 'Test',
                'recipients' => ['test@example.com'],
            ],
        ]);

        $this->artisan('app:check-expired-reminders');

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_EXECUTED, $action->resolution_status);
    }

    public function test_does_not_expire_immediate_actions(): void
    {
        $action = ScheduledAction::create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'name' => 'HTTP Action',
            'mode' => ScheduledAction::MODE_IMMEDIATE,
            'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
            'intent_payload' => [],
            'resolution_status' => ScheduledAction::STATUS_AWAITING_RESPONSE, // Wrong state for HTTP
            'execute_at_utc' => now()->subHour(),
            'request' => ['url' => 'https://example.com'],
            'token_expires_at' => now()->subHour(),
        ]);

        $this->artisan('app:check-expired-reminders');

        $action->refresh();
        // Should remain unchanged (not processed)
        $this->assertEquals(ScheduledAction::STATUS_AWAITING_RESPONSE, $action->resolution_status);
    }

    public function test_expires_multiple_actions(): void
    {
        $action1 = $this->createAwaitingGatedAction(['token_expires_at' => now()->subHour()]);
        $action2 = $this->createAwaitingGatedAction(['token_expires_at' => now()->subMinutes(30)]);
        $action3 = $this->createAwaitingGatedAction(['token_expires_at' => now()->addHour()]); // Not expired

        $this->artisan('app:check-expired-reminders')
            ->expectsOutputToContain('2 reminder(s)');

        $action1->refresh();
        $action2->refresh();
        $action3->refresh();

        $this->assertEquals(ScheduledAction::STATUS_EXPIRED, $action1->resolution_status);
        $this->assertEquals(ScheduledAction::STATUS_EXPIRED, $action2->resolution_status);
        $this->assertEquals(ScheduledAction::STATUS_AWAITING_RESPONSE, $action3->resolution_status);
    }

    public function test_outputs_nothing_when_no_expired_reminders(): void
    {
        $action = $this->createAwaitingGatedAction(['token_expires_at' => now()->addHour()]);

        $this->artisan('app:check-expired-reminders')
            ->assertSuccessful();

        // No mail should be sent
        Mail::assertNotQueued(\App\Mail\ReminderExpiredMail::class);
    }

    public function test_boundary_exactly_at_expiry(): void
    {
        $action = $this->createAwaitingGatedAction([
            'token_expires_at' => now(),
        ]);

        $this->artisan('app:check-expired-reminders');

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_EXPIRED, $action->resolution_status);
    }

    private function createAwaitingGatedAction(array $attributes = []): ScheduledAction
    {
        return ScheduledAction::create(array_merge([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'name' => 'Test Gated Action',
            'mode' => ScheduledAction::MODE_GATED,
            'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
            'intent_payload' => [],
            'resolution_status' => ScheduledAction::STATUS_AWAITING_RESPONSE,
            'execute_at_utc' => now()->subDay(),
            'token_expires_at' => now()->addDays(7),
            'gate' => [
                'message' => 'Please confirm',
                'recipients' => ['test@example.com'],
                'channels' => ['email'],
            ],
        ], $attributes));
    }
}

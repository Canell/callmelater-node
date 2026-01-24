<?php

namespace Tests\Feature;

use App\Models\ReminderRecipient;
use App\Models\ScheduledAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ResponsePageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Queue::fake();
        Mail::fake();
    }

    // ==================== SHORT URL FORMAT ====================

    public function test_short_url_shows_choice_page(): void
    {
        $action = $this->createAwaitingReminder();
        $recipient = $this->createRecipient($action);

        $response = $this->get("/r/{$recipient->response_token}");

        $response->assertStatus(200);
        $response->assertViewIs('response-choice');
        $response->assertSee($action->name);
    }

    public function test_short_url_shows_confirm_button(): void
    {
        $action = $this->createAwaitingReminder();
        $recipient = $this->createRecipient($action);

        $response = $this->get("/r/{$recipient->response_token}");

        $response->assertSee('Confirm');
    }

    public function test_short_url_shows_decline_button(): void
    {
        $action = $this->createAwaitingReminder();
        $recipient = $this->createRecipient($action);

        $response = $this->get("/r/{$recipient->response_token}");

        $response->assertSee('Decline');
    }

    public function test_short_url_shows_snooze_button_when_available(): void
    {
        $action = $this->createAwaitingReminder(['max_snoozes' => 5, 'snooze_count' => 0]);
        $recipient = $this->createRecipient($action);

        $response = $this->get("/r/{$recipient->response_token}");

        $response->assertSee('Snooze');
    }

    public function test_short_url_hides_snooze_when_max_reached(): void
    {
        $action = $this->createAwaitingReminder(['max_snoozes' => 3, 'snooze_count' => 3]);
        $recipient = $this->createRecipient($action);

        $response = $this->get("/r/{$recipient->response_token}");

        $response->assertViewIs('response-choice');
        $response->assertViewHas('canSnooze', false);
    }

    // ==================== RESPOND ENDPOINT ====================

    public function test_respond_with_token_only_shows_choice_page(): void
    {
        $action = $this->createAwaitingReminder();
        $recipient = $this->createRecipient($action);

        $response = $this->get("/respond?token={$recipient->response_token}");

        $response->assertStatus(200);
        $response->assertViewIs('response-choice');
    }

    public function test_respond_confirm_processes_response(): void
    {
        $action = $this->createAwaitingReminder();
        $recipient = $this->createRecipient($action);

        $response = $this->get("/respond?token={$recipient->response_token}&response=confirm");

        $response->assertStatus(200);
        $response->assertViewIs('response');
        $response->assertViewHas('success');

        $recipient->refresh();
        $this->assertEquals(ReminderRecipient::STATUS_CONFIRMED, $recipient->status);
    }

    public function test_respond_decline_processes_response(): void
    {
        $action = $this->createAwaitingReminder();
        $recipient = $this->createRecipient($action);

        $response = $this->get("/respond?token={$recipient->response_token}&response=decline");

        $response->assertStatus(200);
        $response->assertViewHas('success');

        $recipient->refresh();
        $this->assertEquals(ReminderRecipient::STATUS_DECLINED, $recipient->status);
    }

    public function test_respond_snooze_processes_response(): void
    {
        $action = $this->createAwaitingReminder(['max_snoozes' => 5]);
        $recipient = $this->createRecipient($action);

        $response = $this->get("/respond?token={$recipient->response_token}&response=snooze&preset=1h");

        $response->assertStatus(200);
        $response->assertViewHas('success');
    }

    // ==================== ERROR HANDLING ====================

    public function test_invalid_token_shows_error(): void
    {
        $response = $this->get('/r/invalid_token');

        $response->assertStatus(200);
        $response->assertViewIs('response');
        $response->assertViewHas('error');
        $response->assertSee('Invalid or expired response token');
    }

    public function test_already_responded_shows_error(): void
    {
        $action = $this->createAwaitingReminder();
        $recipient = $this->createRecipient($action);
        $recipient->update([
            'status' => ReminderRecipient::STATUS_CONFIRMED,
            'responded_at' => now(),
        ]);

        $response = $this->get("/r/{$recipient->response_token}");

        $response->assertStatus(200);
        $response->assertViewIs('response');
        $response->assertSee('already responded');
    }

    public function test_expired_reminder_shows_error(): void
    {
        $action = $this->createAwaitingReminder([
            'token_expires_at' => now()->subHour(),
        ]);
        $recipient = $this->createRecipient($action);

        $response = $this->get("/r/{$recipient->response_token}");

        $response->assertStatus(200);
        $response->assertViewIs('response');
        $response->assertSee('expired');
    }

    public function test_cancelled_reminder_shows_error(): void
    {
        $action = $this->createAwaitingReminder();
        $action->update(['resolution_status' => ScheduledAction::STATUS_CANCELLED]);
        $recipient = $this->createRecipient($action);

        $response = $this->get("/r/{$recipient->response_token}");

        $response->assertStatus(200);
        $response->assertViewIs('response');
        $response->assertSee('no longer active');
    }

    public function test_missing_token_shows_error(): void
    {
        $response = $this->get('/respond');

        $response->assertStatus(200);
        $response->assertViewIs('response');
        $response->assertViewHas('error');
    }

    public function test_missing_response_type_shows_choice_page(): void
    {
        $action = $this->createAwaitingReminder();
        $recipient = $this->createRecipient($action);

        // Token without response type should show choice page
        $response = $this->get("/respond?token={$recipient->response_token}");

        $response->assertStatus(200);
        $response->assertViewIs('response-choice');
    }

    // ==================== DOUBLE RESPONSE PREVENTION ====================

    public function test_cannot_respond_twice(): void
    {
        $action = $this->createAwaitingReminder();
        $recipient = $this->createRecipient($action);

        // First response
        $response1 = $this->get("/respond?token={$recipient->response_token}&response=confirm");
        $response1->assertViewHas('success');

        // Second response
        $response2 = $this->get("/respond?token={$recipient->response_token}&response=decline");
        $response2->assertViewHas('error');
        $response2->assertSee('already responded');
    }

    // ==================== EXECUTED REMINDER ====================

    public function test_executed_reminder_shows_error(): void
    {
        $action = $this->createAwaitingReminder();
        $action->update(['resolution_status' => ScheduledAction::STATUS_EXECUTED]);
        $recipient = $this->createRecipient($action);

        $response = $this->get("/r/{$recipient->response_token}");

        $response->assertStatus(200);
        $response->assertSee('no longer active');
    }

    // ==================== HELPERS ====================

    private function createAwaitingReminder(array $attributes = []): ScheduledAction
    {
        $maxSnoozes = $attributes['max_snoozes'] ?? 5;
        $snoozeCount = $attributes['snooze_count'] ?? 0;
        unset($attributes['max_snoozes'], $attributes['snooze_count']);

        return ScheduledAction::create(array_merge([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'name' => 'Test Reminder',
            'mode' => ScheduledAction::MODE_GATED,
            'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
            'intent_payload' => [],
            'resolution_status' => ScheduledAction::STATUS_AWAITING_RESPONSE,
            'execute_at_utc' => now()->subHour(),
            'gate' => [
                'message' => 'Please confirm this action',
                'recipients' => ['test@example.com'],
                'channels' => ['email'],
                'confirmation_mode' => ScheduledAction::CONFIRMATION_FIRST_RESPONSE,
                'max_snoozes' => $maxSnoozes,
            ],
            'snooze_count' => $snoozeCount,
            'token_expires_at' => now()->addDays(7),
        ], $attributes));
    }

    private function createRecipient(ScheduledAction $action): ReminderRecipient
    {
        return ReminderRecipient::create([
            'action_id' => $action->id,
            'email' => 'test@example.com',
            'status' => ReminderRecipient::STATUS_SENT,
            'response_token' => 'test_token_'.uniqid(),
        ]);
    }
}

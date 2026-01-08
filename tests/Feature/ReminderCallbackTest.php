<?php

namespace Tests\Feature;

use App\Jobs\DeliverReminderCallback;
use App\Models\CallbackAttempt;
use App\Models\ReminderRecipient;
use App\Models\ScheduledAction;
use App\Models\User;
use App\Services\ResponseProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReminderCallbackTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create([
            'webhook_secret' => 'test-webhook-secret',
        ]);
    }

    public function test_confirm_response_dispatches_callback(): void
    {
        Queue::fake();

        $action = $this->createReminderWithCallback();
        $recipient = $this->createRecipient($action);

        $processor = app(ResponseProcessor::class);
        $processor->handleConfirm($recipient, $action);

        Queue::assertPushed(DeliverReminderCallback::class, function ($job) use ($action) {
            return $job->action->id === $action->id
                && $job->response === 'confirm'
                && $job->responderEmail === 'recipient@example.com';
        });
    }

    public function test_decline_response_dispatches_callback(): void
    {
        Queue::fake();

        $action = $this->createReminderWithCallback();
        $recipient = $this->createRecipient($action);

        $processor = app(ResponseProcessor::class);
        $processor->handleDecline($recipient, $action);

        Queue::assertPushed(DeliverReminderCallback::class, function ($job) {
            return $job->response === 'decline';
        });
    }

    public function test_snooze_response_dispatches_callback_with_preset(): void
    {
        Queue::fake();

        $action = $this->createReminderWithCallback();
        $recipient = $this->createRecipient($action);

        $processor = app(ResponseProcessor::class);
        $processor->handleSnooze($recipient, $action, '1h');

        Queue::assertPushed(DeliverReminderCallback::class, function ($job) {
            return $job->response === 'snooze'
                && $job->snoozePreset === '1h';
        });
    }

    public function test_no_callback_when_url_not_set(): void
    {
        Queue::fake();

        $action = $this->createReminderWithoutCallback();
        $recipient = $this->createRecipient($action);

        $processor = app(ResponseProcessor::class);
        $processor->handleConfirm($recipient, $action);

        Queue::assertNotPushed(DeliverReminderCallback::class);
    }

    public function test_callback_delivery_success(): void
    {
        Http::fake([
            'https://example.com/webhook' => Http::response(['received' => true], 200),
        ]);

        $action = $this->createReminderWithCallback();

        $job = new DeliverReminderCallback(
            $action,
            'confirm',
            'recipient@example.com',
            1
        );
        $job->handle(
            app(\App\Services\UrlValidator::class),
            app(\App\Services\HttpRequestService::class)
        );

        // Verify callback attempt was logged
        $this->assertDatabaseHas('callback_attempts', [
            'action_id' => $action->id,
            'attempt_number' => 1,
            'status' => CallbackAttempt::STATUS_SUCCESS,
            'response_code' => 200,
        ]);
    }

    public function test_callback_4xx_does_not_retry(): void
    {
        Queue::fake();
        Http::fake([
            'https://example.com/webhook' => Http::response('Bad Request', 400),
        ]);

        $action = $this->createReminderWithCallback();

        $job = new DeliverReminderCallback(
            $action,
            'confirm',
            'recipient@example.com',
            1
        );
        $job->handle(
            app(\App\Services\UrlValidator::class),
            app(\App\Services\HttpRequestService::class)
        );

        // Should NOT schedule a retry for 4xx
        Queue::assertNotPushed(DeliverReminderCallback::class);

        // But should log the failure
        $this->assertDatabaseHas('callback_attempts', [
            'action_id' => $action->id,
            'status' => CallbackAttempt::STATUS_FAILED,
            'response_code' => 400,
        ]);
    }

    public function test_callback_5xx_schedules_retry(): void
    {
        Queue::fake();
        Http::fake([
            'https://example.com/webhook' => Http::response('Server Error', 500),
        ]);

        $action = $this->createReminderWithCallback();

        $job = new DeliverReminderCallback(
            $action,
            'confirm',
            'recipient@example.com',
            1 // First attempt
        );
        $job->handle(
            app(\App\Services\UrlValidator::class),
            app(\App\Services\HttpRequestService::class)
        );

        // Should schedule a retry for 5xx
        Queue::assertPushed(DeliverReminderCallback::class, function ($job) {
            return $job->attemptNumber === 2;
        });
    }

    public function test_callback_respects_max_attempts(): void
    {
        Queue::fake();
        Http::fake([
            'https://example.com/webhook' => Http::response('Server Error', 500),
        ]);

        $action = $this->createReminderWithCallback();

        // Third attempt (max)
        $job = new DeliverReminderCallback(
            $action,
            'confirm',
            'recipient@example.com',
            3
        );
        $job->handle(
            app(\App\Services\UrlValidator::class),
            app(\App\Services\HttpRequestService::class)
        );

        // Should NOT schedule another retry after max attempts
        Queue::assertNotPushed(DeliverReminderCallback::class);
    }

    public function test_callback_payload_includes_required_fields(): void
    {
        Http::fake([
            'https://example.com/webhook' => Http::response(['ok' => true], 200),
        ]);

        $action = $this->createReminderWithCallback();

        $job = new DeliverReminderCallback(
            $action,
            'confirm',
            'recipient@example.com',
            1
        );
        $job->handle(
            app(\App\Services\UrlValidator::class),
            app(\App\Services\HttpRequestService::class)
        );

        Http::assertSent(function ($request) use ($action) {
            $body = $request->data();

            return $body['event'] === 'reminder.responded'
                && $body['action_id'] === $action->id
                && $body['action_name'] === $action->name
                && $body['response'] === 'confirm'
                && $body['responder_email'] === 'recipient@example.com'
                && isset($body['responded_at'])
                && isset($body['timestamp']);
        });
    }

    public function test_create_reminder_with_callback_url(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/actions', [
            'name' => 'Test Reminder',
            'type' => 'reminder',
            'message' => 'Please confirm',
            'intent' => ['delay' => '1h'],
            'escalation_rules' => [
                'recipients' => ['test@example.com'],
            ],
            'callback_url' => 'https://example.com/webhook',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('scheduled_actions', [
            'name' => 'Test Reminder',
            'callback_url' => 'https://example.com/webhook',
        ]);
    }

    public function test_callback_url_validation(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/actions', [
            'name' => 'Test Reminder',
            'type' => 'reminder',
            'message' => 'Please confirm',
            'intent' => ['delay' => '1h'],
            'escalation_rules' => [
                'recipients' => ['test@example.com'],
            ],
            'callback_url' => 'not-a-valid-url',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['callback_url']);
    }

    public function test_callback_url_is_optional(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/actions', [
            'name' => 'Test Reminder',
            'type' => 'reminder',
            'message' => 'Please confirm',
            'intent' => ['delay' => '1h'],
            'escalation_rules' => [
                'recipients' => ['test@example.com'],
            ],
            // No callback_url
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('scheduled_actions', [
            'name' => 'Test Reminder',
            'callback_url' => null,
        ]);
    }

    private function createReminderWithCallback(): ScheduledAction
    {
        return ScheduledAction::create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'name' => 'Test Reminder',
            'type' => ScheduledAction::TYPE_REMINDER,
            'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
            'intent_payload' => ['execute_at' => now()->subMinute()->toIso8601String()],
            'resolution_status' => ScheduledAction::STATUS_AWAITING_RESPONSE,
            'execute_at_utc' => now()->subMinute(),
            'message' => 'Please confirm this action',
            'confirmation_mode' => ScheduledAction::CONFIRMATION_FIRST_RESPONSE,
            'escalation_rules' => ['recipients' => ['recipient@example.com']],
            'callback_url' => 'https://example.com/webhook',
            'max_snoozes' => 5,
        ]);
    }

    private function createReminderWithoutCallback(): ScheduledAction
    {
        return ScheduledAction::create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'name' => 'Test Reminder No Callback',
            'type' => ScheduledAction::TYPE_REMINDER,
            'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
            'intent_payload' => ['execute_at' => now()->subMinute()->toIso8601String()],
            'resolution_status' => ScheduledAction::STATUS_AWAITING_RESPONSE,
            'execute_at_utc' => now()->subMinute(),
            'message' => 'Please confirm this action',
            'confirmation_mode' => ScheduledAction::CONFIRMATION_FIRST_RESPONSE,
            'escalation_rules' => ['recipients' => ['recipient@example.com']],
            'callback_url' => null,
            'max_snoozes' => 5,
        ]);
    }

    private function createRecipient(ScheduledAction $action): ReminderRecipient
    {
        return ReminderRecipient::create([
            'action_id' => $action->id,
            'email' => 'recipient@example.com',
            'token' => 'test-token-123',
            'status' => ReminderRecipient::STATUS_PENDING,
        ]);
    }
}

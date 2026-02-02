<?php

namespace Tests\Feature;

use App\Jobs\DeliverActionCallback;
use App\Jobs\DeliverHttpAction;
use App\Models\CallbackAttempt;
use App\Models\ScheduledAction;
use App\Models\User;
use App\Services\UrlValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class ActionCallbackTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();

        // Mock UrlValidator to bypass DNS resolution in tests
        $urlValidator = Mockery::mock(UrlValidator::class);
        $urlValidator->shouldReceive('validate')->andReturnNull();
        $this->app->instance(UrlValidator::class, $urlValidator);
    }

    // ==================== CALLBACK DISPATCH TESTS ====================

    public function test_callback_dispatched_on_action_executed(): void
    {
        // Use Bus::fake() to intercept dispatched jobs while allowing HTTP fake to work
        \Illuminate\Support\Facades\Bus::fake([DeliverActionCallback::class]);

        $action = ScheduledAction::factory()->create([
            'account_id' => $this->user->account_id,
            'mode' => ScheduledAction::MODE_IMMEDIATE,
            'resolution_status' => ScheduledAction::STATUS_EXECUTING,
            'callback_url' => 'https://example.com/webhook',
            'request' => [
                'url' => 'https://api.example.com/test',
                'method' => 'POST',
            ],
        ]);

        // Mock successful HTTP response
        Http::fake([
            'api.example.com/*' => Http::response(['success' => true], 200),
        ]);

        // Execute the job
        $job = new DeliverHttpAction($action);
        $job->handle(app(\App\Services\UrlValidator::class), app(\App\Services\HttpRequestService::class));

        // Verify callback was dispatched
        \Illuminate\Support\Facades\Bus::assertDispatched(DeliverActionCallback::class, function ($job) use ($action) {
            return $job->action->id === $action->id
                && $job->event === DeliverActionCallback::EVENT_EXECUTED;
        });
    }

    public function test_callback_dispatched_on_action_failed_client_error(): void
    {
        \Illuminate\Support\Facades\Bus::fake([DeliverActionCallback::class]);

        $action = ScheduledAction::factory()->create([
            'account_id' => $this->user->account_id,
            'mode' => ScheduledAction::MODE_IMMEDIATE,
            'resolution_status' => ScheduledAction::STATUS_EXECUTING,
            'callback_url' => 'https://example.com/webhook',
            'request' => [
                'url' => 'https://api.example.com/test',
                'method' => 'POST',
            ],
        ]);

        // Mock 4xx HTTP response (immediate failure, no retry)
        Http::fake([
            'api.example.com/*' => Http::response(['error' => 'Bad Request'], 400),
        ]);

        $job = new DeliverHttpAction($action);
        $job->handle(app(\App\Services\UrlValidator::class), app(\App\Services\HttpRequestService::class));

        // Verify callback was dispatched for failure
        \Illuminate\Support\Facades\Bus::assertDispatched(DeliverActionCallback::class, function ($job) use ($action) {
            return $job->action->id === $action->id
                && $job->event === DeliverActionCallback::EVENT_FAILED;
        });
    }

    public function test_callback_dispatched_on_action_failed_after_max_retries(): void
    {
        \Illuminate\Support\Facades\Bus::fake([DeliverActionCallback::class]);

        $action = ScheduledAction::factory()->create([
            'account_id' => $this->user->account_id,
            'mode' => ScheduledAction::MODE_IMMEDIATE,
            'resolution_status' => ScheduledAction::STATUS_EXECUTING,
            'callback_url' => 'https://example.com/webhook',
            'max_attempts' => 3,
            'attempt_count' => 3, // Already at max attempts
            'request' => [
                'url' => 'https://api.example.com/test',
                'method' => 'POST',
            ],
        ]);

        // Mock 5xx HTTP response
        Http::fake([
            'api.example.com/*' => Http::response(['error' => 'Server Error'], 500),
        ]);

        $job = new DeliverHttpAction($action);
        $job->handle(app(\App\Services\UrlValidator::class), app(\App\Services\HttpRequestService::class));

        // Verify callback was dispatched for failure (max retries reached)
        \Illuminate\Support\Facades\Bus::assertDispatched(DeliverActionCallback::class, function ($job) use ($action) {
            return $job->action->id === $action->id
                && $job->event === DeliverActionCallback::EVENT_FAILED;
        });
    }

    public function test_no_callback_dispatched_without_callback_url(): void
    {
        \Illuminate\Support\Facades\Bus::fake([DeliverActionCallback::class]);

        $action = ScheduledAction::factory()->create([
            'account_id' => $this->user->account_id,
            'mode' => ScheduledAction::MODE_IMMEDIATE,
            'resolution_status' => ScheduledAction::STATUS_EXECUTING,
            'callback_url' => null, // No callback URL
            'request' => [
                'url' => 'https://api.example.com/test',
                'method' => 'POST',
            ],
        ]);

        Http::fake([
            'api.example.com/*' => Http::response(['success' => true], 200),
        ]);

        $job = new DeliverHttpAction($action);
        $job->handle(app(\App\Services\UrlValidator::class), app(\App\Services\HttpRequestService::class));

        // Verify no callback was dispatched
        \Illuminate\Support\Facades\Bus::assertNotDispatched(DeliverActionCallback::class);
    }

    // ==================== CALLBACK DELIVERY TESTS ====================

    public function test_callback_delivered_successfully(): void
    {
        Http::fake([
            'example.com/*' => Http::response(['received' => true], 200),
        ]);

        $action = ScheduledAction::factory()->create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'callback_url' => 'https://example.com/webhook',
        ]);

        $job = new DeliverActionCallback($action, DeliverActionCallback::EVENT_EXECUTED, [
            'status_code' => 200,
            'duration_ms' => 150,
            'attempt_number' => 1,
        ]);

        $job->handle(app(\App\Services\UrlValidator::class), app(\App\Services\HttpRequestService::class));

        // Verify callback attempt was logged
        $this->assertDatabaseHas('callback_attempts', [
            'action_id' => $action->id,
            'event_type' => 'action.executed',
            'status' => CallbackAttempt::STATUS_SUCCESS,
            'response_code' => 200,
        ]);
    }

    public function test_callback_payload_contains_correct_event_data(): void
    {
        Http::fake(function ($request) {
            $body = json_decode($request->body(), true);

            // Verify payload structure
            $this->assertEquals('action.executed', $body['event']);
            $this->assertArrayHasKey('action_id', $body);
            $this->assertArrayHasKey('action_name', $body);
            $this->assertArrayHasKey('timestamp', $body);
            $this->assertArrayHasKey('execution', $body);
            $this->assertEquals(200, $body['execution']['status_code']);

            return Http::response(['received' => true], 200);
        });

        $action = ScheduledAction::factory()->create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'callback_url' => 'https://example.com/webhook',
        ]);

        $job = new DeliverActionCallback($action, DeliverActionCallback::EVENT_EXECUTED, [
            'status_code' => 200,
            'duration_ms' => 150,
            'attempt_number' => 1,
        ]);

        $job->handle(app(\App\Services\UrlValidator::class), app(\App\Services\HttpRequestService::class));
    }

    public function test_callback_failure_payload_contains_error_details(): void
    {
        Http::fake(function ($request) {
            $body = json_decode($request->body(), true);

            $this->assertEquals('action.failed', $body['event']);
            $this->assertArrayHasKey('failure', $body);
            $this->assertEquals('Connection timeout', $body['failure']['reason']);
            $this->assertEquals(3, $body['failure']['total_attempts']);

            return Http::response(['received' => true], 200);
        });

        $action = ScheduledAction::factory()->create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'callback_url' => 'https://example.com/webhook',
        ]);

        $job = new DeliverActionCallback($action, DeliverActionCallback::EVENT_FAILED, [
            'reason' => 'Connection timeout',
            'status_code' => null,
            'total_attempts' => 3,
            'error_message' => 'Connection timed out after 30 seconds',
        ]);

        $job->handle(app(\App\Services\UrlValidator::class), app(\App\Services\HttpRequestService::class));
    }

    public function test_callback_expired_payload_contains_expiration_details(): void
    {
        Http::fake(function ($request) {
            $body = json_decode($request->body(), true);

            $this->assertEquals('action.expired', $body['event']);
            $this->assertArrayHasKey('expiration', $body);
            $this->assertArrayHasKey('expired_at', $body['expiration']);

            return Http::response(['received' => true], 200);
        });

        $action = ScheduledAction::factory()->create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'mode' => ScheduledAction::MODE_GATED,
            'callback_url' => 'https://example.com/webhook',
            'gate' => [
                'message' => 'Test',
                'recipients' => ['test@example.com'],
                'timeout' => '7d',
            ],
        ]);

        $job = new DeliverActionCallback($action, DeliverActionCallback::EVENT_EXPIRED, [
            'expired_at' => now()->toIso8601String(),
        ]);

        $job->handle(app(\App\Services\UrlValidator::class), app(\App\Services\HttpRequestService::class));
    }

    public function test_callback_retries_on_server_error(): void
    {
        Queue::fake();

        Http::fake([
            'example.com/*' => Http::response(['error' => 'Server Error'], 500),
        ]);

        $action = ScheduledAction::factory()->create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'callback_url' => 'https://example.com/webhook',
        ]);

        $job = new DeliverActionCallback($action, DeliverActionCallback::EVENT_EXECUTED, [], 1);
        $job->handle(app(\App\Services\UrlValidator::class), app(\App\Services\HttpRequestService::class));

        // Verify retry was scheduled
        Queue::assertPushed(DeliverActionCallback::class, function ($job) {
            return $job->attemptNumber === 2;
        });
    }

    public function test_callback_no_retry_on_client_error(): void
    {
        Queue::fake();

        Http::fake([
            'example.com/*' => Http::response(['error' => 'Not Found'], 404),
        ]);

        $action = ScheduledAction::factory()->create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'callback_url' => 'https://example.com/webhook',
        ]);

        $job = new DeliverActionCallback($action, DeliverActionCallback::EVENT_EXECUTED, [], 1);
        $job->handle(app(\App\Services\UrlValidator::class), app(\App\Services\HttpRequestService::class));

        // Verify no retry was scheduled (4xx errors are permanent failures)
        Queue::assertNotPushed(DeliverActionCallback::class);

        // But the attempt was logged as failed
        $this->assertDatabaseHas('callback_attempts', [
            'action_id' => $action->id,
            'status' => CallbackAttempt::STATUS_FAILED,
            'response_code' => 404,
        ]);
    }

    public function test_callback_abandoned_after_max_retries(): void
    {
        Queue::fake();

        Http::fake([
            'example.com/*' => Http::response(['error' => 'Server Error'], 500),
        ]);

        $action = ScheduledAction::factory()->create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'callback_url' => 'https://example.com/webhook',
        ]);

        // Attempt number 3 is the max
        $job = new DeliverActionCallback($action, DeliverActionCallback::EVENT_EXECUTED, [], 3);
        $job->handle(app(\App\Services\UrlValidator::class), app(\App\Services\HttpRequestService::class));

        // Verify no retry was scheduled (max attempts reached)
        Queue::assertNotPushed(DeliverActionCallback::class);
    }

    public function test_callback_skipped_for_blocked_url(): void
    {
        // Use real UrlValidator for this test (override the mock)
        $realValidator = new UrlValidator();
        $this->app->instance(UrlValidator::class, $realValidator);

        $action = ScheduledAction::factory()->create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'callback_url' => 'http://localhost/internal', // Blocked URL
        ]);

        $job = new DeliverActionCallback($action, DeliverActionCallback::EVENT_EXECUTED, []);
        $job->handle(app(UrlValidator::class), app(\App\Services\HttpRequestService::class));

        // Verify attempt was logged as failed due to security block
        $this->assertDatabaseHas('callback_attempts', [
            'action_id' => $action->id,
            'status' => CallbackAttempt::STATUS_FAILED,
        ]);
    }
}

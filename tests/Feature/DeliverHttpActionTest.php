<?php

namespace Tests\Feature;

use App\Jobs\DeliverHttpAction;
use App\Models\DeliveryAttempt;
use App\Models\ScheduledAction;
use App\Models\User;
use App\Services\HttpRequestService;
use App\Services\UrlValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class DeliverHttpActionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Mail::fake();
    }

    // ==================== SUCCESS SCENARIOS ====================

    public function test_successful_http_delivery_marks_action_executed(): void
    {
        $action = $this->createImmediateAction(ScheduledAction::STATUS_EXECUTING);

        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('status')->andReturn(200);
        $mockResponse->shouldReceive('successful')->andReturn(true);
        $mockResponse->shouldReceive('body')->andReturn('{"success": true}');

        $httpService = Mockery::mock(HttpRequestService::class);
        $httpService->shouldReceive('makeRequest')->once()->andReturn($mockResponse);

        $urlValidator = Mockery::mock(UrlValidator::class);
        $urlValidator->shouldReceive('validate')->once();

        $job = new DeliverHttpAction($action);
        $job->handle($urlValidator, $httpService);

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_EXECUTED, $action->resolution_status);
        $this->assertNotNull($action->executed_at_utc);
    }

    public function test_successful_delivery_creates_delivery_attempt_record(): void
    {
        $action = $this->createImmediateAction(ScheduledAction::STATUS_EXECUTING);

        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('status')->andReturn(200);
        $mockResponse->shouldReceive('successful')->andReturn(true);
        $mockResponse->shouldReceive('body')->andReturn('OK');

        $httpService = Mockery::mock(HttpRequestService::class);
        $httpService->shouldReceive('makeRequest')->once()->andReturn($mockResponse);

        $urlValidator = Mockery::mock(UrlValidator::class);
        $urlValidator->shouldReceive('validate')->once();

        $job = new DeliverHttpAction($action);
        $job->handle($urlValidator, $httpService);

        $this->assertDatabaseHas('delivery_attempts', [
            'action_id' => $action->id,
            'attempt_number' => 1,
            'response_code' => 200,
            'status' => DeliveryAttempt::STATUS_SUCCESS,
            'failure_category' => DeliveryAttempt::CATEGORY_SUCCESS,
        ]);
    }

    // ==================== 4XX CLIENT ERRORS (NON-RETRYABLE) ====================

    public function test_4xx_error_marks_action_failed_immediately(): void
    {
        $action = $this->createImmediateAction(ScheduledAction::STATUS_EXECUTING);
        $action->update(['max_attempts' => 5]); // Even with retries available

        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('status')->andReturn(404);
        $mockResponse->shouldReceive('successful')->andReturn(false);
        $mockResponse->shouldReceive('clientError')->andReturn(true);
        $mockResponse->shouldReceive('body')->andReturn('Not Found');

        $httpService = Mockery::mock(HttpRequestService::class);
        $httpService->shouldReceive('makeRequest')->once()->andReturn($mockResponse);

        $urlValidator = Mockery::mock(UrlValidator::class);
        $urlValidator->shouldReceive('validate')->once();

        $job = new DeliverHttpAction($action);
        $job->handle($urlValidator, $httpService);

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_FAILED, $action->resolution_status);
        $this->assertStringContainsString('404', $action->failure_reason);
        $this->assertStringContainsString('Client error', $action->failure_reason);
    }

    public function test_401_unauthorized_does_not_retry(): void
    {
        $action = $this->createImmediateAction(ScheduledAction::STATUS_EXECUTING);
        $action->update(['max_attempts' => 5]);

        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('status')->andReturn(401);
        $mockResponse->shouldReceive('successful')->andReturn(false);
        $mockResponse->shouldReceive('clientError')->andReturn(true);
        $mockResponse->shouldReceive('body')->andReturn('Unauthorized');

        $httpService = Mockery::mock(HttpRequestService::class);
        $httpService->shouldReceive('makeRequest')->once()->andReturn($mockResponse);

        $urlValidator = Mockery::mock(UrlValidator::class);
        $urlValidator->shouldReceive('validate')->once();

        $job = new DeliverHttpAction($action);
        $job->handle($urlValidator, $httpService);

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_FAILED, $action->resolution_status);
        $this->assertEquals(1, $action->attempt_count); // Only 1 attempt, no retry
    }

    // ==================== 5XX SERVER ERRORS (RETRYABLE) ====================

    public function test_5xx_error_schedules_retry_when_attempts_remain(): void
    {
        $action = $this->createImmediateAction(ScheduledAction::STATUS_EXECUTING);
        $action->update(['max_attempts' => 3, 'attempt_count' => 0]);

        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('status')->andReturn(500);
        $mockResponse->shouldReceive('successful')->andReturn(false);
        $mockResponse->shouldReceive('clientError')->andReturn(false);
        $mockResponse->shouldReceive('body')->andReturn('Internal Server Error');

        $httpService = Mockery::mock(HttpRequestService::class);
        $httpService->shouldReceive('makeRequest')->once()->andReturn($mockResponse);

        $urlValidator = Mockery::mock(UrlValidator::class);
        $urlValidator->shouldReceive('validate')->once();

        $job = new DeliverHttpAction($action);
        $job->handle($urlValidator, $httpService);

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_RESOLVED, $action->resolution_status);
        $this->assertNotNull($action->next_retry_at);
        $this->assertEquals(1, $action->attempt_count);
    }

    public function test_5xx_error_marks_failed_when_max_attempts_reached(): void
    {
        $action = $this->createImmediateAction(ScheduledAction::STATUS_EXECUTING);
        $action->update(['max_attempts' => 3, 'attempt_count' => 2]); // Last attempt

        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('status')->andReturn(503);
        $mockResponse->shouldReceive('successful')->andReturn(false);
        $mockResponse->shouldReceive('clientError')->andReturn(false);
        $mockResponse->shouldReceive('body')->andReturn('Service Unavailable');

        $httpService = Mockery::mock(HttpRequestService::class);
        $httpService->shouldReceive('makeRequest')->once()->andReturn($mockResponse);

        $urlValidator = Mockery::mock(UrlValidator::class);
        $urlValidator->shouldReceive('validate')->once();

        $job = new DeliverHttpAction($action);
        $job->handle($urlValidator, $httpService);

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_FAILED, $action->resolution_status);
        $this->assertStringContainsString('503', $action->failure_reason);
        $this->assertStringContainsString('3 attempts', $action->failure_reason);
    }

    // ==================== NETWORK/SYSTEM ERRORS ====================

    public function test_connection_exception_schedules_retry(): void
    {
        $action = $this->createImmediateAction(ScheduledAction::STATUS_EXECUTING);
        $action->update(['max_attempts' => 3, 'attempt_count' => 0]);

        $httpService = Mockery::mock(HttpRequestService::class);
        $httpService->shouldReceive('makeRequest')
            ->once()
            ->andThrow(new \Illuminate\Http\Client\ConnectionException('Connection timed out'));

        $urlValidator = Mockery::mock(UrlValidator::class);
        $urlValidator->shouldReceive('validate')->once();

        $job = new DeliverHttpAction($action);
        $job->handle($urlValidator, $httpService);

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_RESOLVED, $action->resolution_status);
        $this->assertNotNull($action->next_retry_at);
    }

    public function test_system_exception_marks_failed_after_max_attempts(): void
    {
        $action = $this->createImmediateAction(ScheduledAction::STATUS_EXECUTING);
        $action->update(['max_attempts' => 1, 'attempt_count' => 0]); // Single attempt

        $httpService = Mockery::mock(HttpRequestService::class);
        $httpService->shouldReceive('makeRequest')
            ->once()
            ->andThrow(new \Exception('System error'));

        $urlValidator = Mockery::mock(UrlValidator::class);
        $urlValidator->shouldReceive('validate')->once();

        $job = new DeliverHttpAction($action);
        $job->handle($urlValidator, $httpService);

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_FAILED, $action->resolution_status);
        $this->assertStringContainsString('System error', $action->failure_reason);
    }

    // ==================== VALIDATION/SECURITY ====================

    public function test_invalid_config_marks_action_failed(): void
    {
        $action = $this->createImmediateAction(ScheduledAction::STATUS_EXECUTING);
        $action->update(['request' => null]); // Invalid config

        $urlValidator = Mockery::mock(UrlValidator::class);
        $httpService = Mockery::mock(HttpRequestService::class);

        $job = new DeliverHttpAction($action);
        $job->handle($urlValidator, $httpService);

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_FAILED, $action->resolution_status);
        $this->assertStringContainsString('Invalid HTTP request configuration', $action->failure_reason);
    }

    public function test_ssrf_blocked_url_marks_action_failed(): void
    {
        $action = $this->createImmediateAction(ScheduledAction::STATUS_EXECUTING);

        $urlValidator = Mockery::mock(UrlValidator::class);
        $urlValidator->shouldReceive('validate')
            ->once()
            ->andThrow(new \InvalidArgumentException('Private IP addresses are not allowed'));

        $httpService = Mockery::mock(HttpRequestService::class);

        $job = new DeliverHttpAction($action);
        $job->handle($urlValidator, $httpService);

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_FAILED, $action->resolution_status);
        $this->assertStringContainsString('Security', $action->failure_reason);
        $this->assertStringContainsString('Private IP', $action->failure_reason);
    }

    // ==================== STATE GUARDS ====================

    public function test_skips_action_no_longer_in_executing_state(): void
    {
        $action = $this->createImmediateAction(ScheduledAction::STATUS_CANCELLED);

        $urlValidator = Mockery::mock(UrlValidator::class);
        $httpService = Mockery::mock(HttpRequestService::class);
        // No expectations set - makeRequest should not be called

        $job = new DeliverHttpAction($action);
        $job->handle($urlValidator, $httpService);

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_CANCELLED, $action->resolution_status);
    }

    public function test_skips_already_executed_action(): void
    {
        $action = $this->createImmediateAction(ScheduledAction::STATUS_EXECUTED);

        $urlValidator = Mockery::mock(UrlValidator::class);
        $httpService = Mockery::mock(HttpRequestService::class);

        $job = new DeliverHttpAction($action);
        $job->handle($urlValidator, $httpService);

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_EXECUTED, $action->resolution_status);
    }

    // ==================== DELIVERY ATTEMPT TRACKING ====================

    public function test_delivery_attempt_records_duration(): void
    {
        $action = $this->createImmediateAction(ScheduledAction::STATUS_EXECUTING);

        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('status')->andReturn(200);
        $mockResponse->shouldReceive('successful')->andReturn(true);
        $mockResponse->shouldReceive('body')->andReturn('OK');

        $httpService = Mockery::mock(HttpRequestService::class);
        $httpService->shouldReceive('makeRequest')->once()->andReturn($mockResponse);

        $urlValidator = Mockery::mock(UrlValidator::class);
        $urlValidator->shouldReceive('validate')->once();

        $job = new DeliverHttpAction($action);
        $job->handle($urlValidator, $httpService);

        $attempt = DeliveryAttempt::where('action_id', $action->id)->first();
        $this->assertNotNull($attempt);
        $this->assertNotNull($attempt->duration_ms);
        $this->assertGreaterThanOrEqual(0, $attempt->duration_ms);
    }

    public function test_delivery_attempt_records_target_domain(): void
    {
        $action = $this->createImmediateAction(ScheduledAction::STATUS_EXECUTING);
        $action->update(['request' => ['url' => 'https://api.example.com/webhook', 'method' => 'POST']]);

        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('status')->andReturn(200);
        $mockResponse->shouldReceive('successful')->andReturn(true);
        $mockResponse->shouldReceive('body')->andReturn('OK');

        $httpService = Mockery::mock(HttpRequestService::class);
        $httpService->shouldReceive('makeRequest')->once()->andReturn($mockResponse);

        $urlValidator = Mockery::mock(UrlValidator::class);
        $urlValidator->shouldReceive('validate')->once();

        $job = new DeliverHttpAction($action);
        $job->handle($urlValidator, $httpService);

        $attempt = DeliveryAttempt::where('action_id', $action->id)->first();
        $this->assertEquals('api.example.com', $attempt->target_domain);
    }

    // ==================== FAILURE NOTIFICATION ====================

    public function test_failure_sends_notification_email(): void
    {
        $action = $this->createImmediateAction(ScheduledAction::STATUS_EXECUTING);
        $action->update(['max_attempts' => 1]);

        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('status')->andReturn(500);
        $mockResponse->shouldReceive('successful')->andReturn(false);
        $mockResponse->shouldReceive('clientError')->andReturn(false);
        $mockResponse->shouldReceive('body')->andReturn('Error');

        $httpService = Mockery::mock(HttpRequestService::class);
        $httpService->shouldReceive('makeRequest')->once()->andReturn($mockResponse);

        $urlValidator = Mockery::mock(UrlValidator::class);
        $urlValidator->shouldReceive('validate')->once();

        $job = new DeliverHttpAction($action);
        $job->handle($urlValidator, $httpService);

        Mail::assertQueued(\App\Mail\ActionFailedMail::class, function ($mail) use ($action) {
            return $mail->action->id === $action->id;
        });
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
            'intent_payload' => ['execute_at' => now()->subMinute()->toIso8601String()],
            'resolution_status' => $status,
            'execute_at_utc' => now()->subMinute(),
            'request' => ['url' => 'https://example.com/webhook', 'method' => 'POST'],
            'max_attempts' => 3,
            'attempt_count' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

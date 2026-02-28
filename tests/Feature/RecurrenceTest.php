<?php

namespace Tests\Feature;

use App\Jobs\DeliverHttpAction;
use App\Models\ScheduledAction;
use App\Models\User;
use App\Services\HttpRequestService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecurrenceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // ==================== MODEL: isRecurring ====================

    public function test_is_recurring_returns_true_when_config_set(): void
    {
        $action = $this->createAction([
            'recurrence_config' => ['frequency' => 1, 'unit' => 'h', 'end_type' => 'never'],
        ]);
        $this->assertTrue($action->isRecurring());
    }

    public function test_is_recurring_returns_false_when_no_config(): void
    {
        $action = $this->createAction();
        $this->assertFalse($action->isRecurring());
    }

    // ==================== MODEL: shouldRecur ====================

    public function test_should_recur_count_based_returns_true_when_below_limit(): void
    {
        $action = $this->createAction([
            'recurrence_config' => ['frequency' => 1, 'unit' => 'h', 'end_type' => 'count', 'max_occurrences' => 3],
            'recurrence_count' => 1,
        ]);
        $this->assertTrue($action->shouldRecur());
    }

    public function test_should_recur_count_based_returns_false_on_last_occurrence(): void
    {
        $action = $this->createAction([
            'recurrence_config' => ['frequency' => 1, 'unit' => 'h', 'end_type' => 'count', 'max_occurrences' => 3],
            'recurrence_count' => 2,
        ]);
        $this->assertFalse($action->shouldRecur());
    }

    public function test_should_recur_never_always_returns_true(): void
    {
        $action = $this->createAction([
            'recurrence_config' => ['frequency' => 1, 'unit' => 'h', 'end_type' => 'never'],
            'recurrence_count' => 100,
        ]);
        $this->assertTrue($action->shouldRecur());
    }

    public function test_should_recur_date_based_returns_false_past_end_date(): void
    {
        $action = $this->createAction([
            'recurrence_config' => [
                'frequency' => 1,
                'unit' => 'h',
                'end_type' => 'date',
                'end_date' => now()->subDay()->toIso8601String(),
            ],
        ]);
        $this->assertFalse($action->shouldRecur());
    }

    public function test_should_recur_date_based_returns_true_before_end_date(): void
    {
        $action = $this->createAction([
            'recurrence_config' => [
                'frequency' => 1,
                'unit' => 'h',
                'end_type' => 'date',
                'end_date' => now()->addMonth()->toIso8601String(),
            ],
        ]);
        $this->assertTrue($action->shouldRecur());
    }

    // ==================== MODEL: calculateNextRecurrenceTime ====================

    public function test_calculate_next_recurrence_time_minutes(): void
    {
        Carbon::setTestNow('2026-03-01 12:00:00');
        $action = $this->createAction([
            'recurrence_config' => ['frequency' => 30, 'unit' => 'm', 'end_type' => 'never'],
            'timezone' => 'UTC',
        ]);

        $next = $action->calculateNextRecurrenceTime();
        $this->assertEquals('2026-03-01 12:30:00', $next->format('Y-m-d H:i:s'));
        Carbon::setTestNow();
    }

    public function test_calculate_next_recurrence_time_hours(): void
    {
        Carbon::setTestNow('2026-03-01 12:00:00');
        $action = $this->createAction([
            'recurrence_config' => ['frequency' => 2, 'unit' => 'h', 'end_type' => 'never'],
            'timezone' => 'UTC',
        ]);

        $next = $action->calculateNextRecurrenceTime();
        $this->assertEquals('2026-03-01 14:00:00', $next->format('Y-m-d H:i:s'));
        Carbon::setTestNow();
    }

    public function test_calculate_next_recurrence_time_days(): void
    {
        Carbon::setTestNow('2026-03-01 12:00:00');
        $action = $this->createAction([
            'recurrence_config' => ['frequency' => 1, 'unit' => 'd', 'end_type' => 'never'],
            'timezone' => 'UTC',
        ]);

        $next = $action->calculateNextRecurrenceTime();
        $this->assertEquals('2026-03-02 12:00:00', $next->format('Y-m-d H:i:s'));
        Carbon::setTestNow();
    }

    public function test_calculate_next_recurrence_time_weeks(): void
    {
        Carbon::setTestNow('2026-03-01 12:00:00');
        $action = $this->createAction([
            'recurrence_config' => ['frequency' => 1, 'unit' => 'w', 'end_type' => 'never'],
            'timezone' => 'UTC',
        ]);

        $next = $action->calculateNextRecurrenceTime();
        $this->assertEquals('2026-03-08 12:00:00', $next->format('Y-m-d H:i:s'));
        Carbon::setTestNow();
    }

    public function test_calculate_next_recurrence_time_months(): void
    {
        Carbon::setTestNow('2026-03-01 12:00:00');
        $action = $this->createAction([
            'recurrence_config' => ['frequency' => 1, 'unit' => 'M', 'end_type' => 'never'],
            'timezone' => 'UTC',
        ]);

        $next = $action->calculateNextRecurrenceTime();
        $this->assertEquals('2026-04-01 12:00:00', $next->format('Y-m-d H:i:s'));
        Carbon::setTestNow();
    }

    // ==================== MODEL: scheduleNextRecurrence ====================

    public function test_schedule_next_recurrence_increments_count_and_resets_state(): void
    {
        Carbon::setTestNow('2026-03-01 12:00:00');
        $action = $this->createAction([
            'resolution_status' => ScheduledAction::STATUS_EXECUTING,
            'recurrence_config' => ['frequency' => 1, 'unit' => 'h', 'end_type' => 'never'],
            'recurrence_count' => 0,
            'attempt_count' => 3,
            'next_retry_at' => now(),
            'failure_reason' => 'previous failure',
        ]);

        $action->scheduleNextRecurrence();

        $this->assertEquals(1, $action->recurrence_count);
        $this->assertEquals(0, $action->attempt_count);
        $this->assertNull($action->next_retry_at);
        $this->assertNull($action->failure_reason);
        $this->assertNotNull($action->last_executed_at);
        $this->assertEquals(ScheduledAction::STATUS_RESOLVED, $action->resolution_status);
        $this->assertNotNull($action->execute_at_utc);
        Carbon::setTestNow();
    }

    public function test_schedule_next_recurrence_resets_gate_for_gated_actions(): void
    {
        Carbon::setTestNow('2026-03-01 12:00:00');
        $action = $this->createGatedAction([
            'resolution_status' => ScheduledAction::STATUS_EXECUTING,
            'recurrence_config' => ['frequency' => 1, 'unit' => 'h', 'end_type' => 'never'],
            'recurrence_count' => 0,
            'gate_passed_at' => now(),
            'snooze_count' => 2,
            'token_expires_at' => now()->addDays(7),
        ]);

        $action->scheduleNextRecurrence();

        $this->assertNull($action->gate_passed_at);
        $this->assertEquals(0, $action->snooze_count);
        $this->assertNull($action->token_expires_at);
        $this->assertEquals(ScheduledAction::STATUS_RESOLVED, $action->resolution_status);
        Carbon::setTestNow();
    }

    // ==================== EXECUTION FLOW: DeliverHttpAction ====================

    public function test_successful_recurring_action_reschedules_instead_of_marking_executed(): void
    {
        $action = $this->createAction([
            'resolution_status' => ScheduledAction::STATUS_EXECUTING,
            'recurrence_config' => ['frequency' => 1, 'unit' => 'h', 'end_type' => 'count', 'max_occurrences' => 3],
            'recurrence_count' => 0,
            'request' => ['url' => 'https://example.com/webhook', 'method' => 'POST'],
        ]);

        // Mock HTTP service to return success
        $mockResponse = $this->createMock(Response::class);
        $mockResponse->method('successful')->willReturn(true);
        $mockResponse->method('clientError')->willReturn(false);
        $mockResponse->method('status')->willReturn(200);
        $mockResponse->method('body')->willReturn('{"ok":true}');

        $httpService = $this->createMock(HttpRequestService::class);
        $httpService->method('makeRequest')->willReturn($mockResponse);

        $this->app->instance(HttpRequestService::class, $httpService);

        $job = new DeliverHttpAction($action);
        $job->handle(app(\App\Services\UrlValidator::class), $httpService);

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_RESOLVED, $action->resolution_status);
        $this->assertEquals(1, $action->recurrence_count);
        $this->assertNotNull($action->last_executed_at);
    }

    public function test_final_occurrence_marks_as_executed(): void
    {
        $action = $this->createAction([
            'resolution_status' => ScheduledAction::STATUS_EXECUTING,
            'recurrence_config' => ['frequency' => 1, 'unit' => 'h', 'end_type' => 'count', 'max_occurrences' => 2],
            'recurrence_count' => 1,
            'request' => ['url' => 'https://example.com/webhook', 'method' => 'POST'],
        ]);

        $mockResponse = $this->createMock(Response::class);
        $mockResponse->method('successful')->willReturn(true);
        $mockResponse->method('clientError')->willReturn(false);
        $mockResponse->method('status')->willReturn(200);
        $mockResponse->method('body')->willReturn('{"ok":true}');

        $httpService = $this->createMock(HttpRequestService::class);
        $httpService->method('makeRequest')->willReturn($mockResponse);

        $this->app->instance(HttpRequestService::class, $httpService);

        $job = new DeliverHttpAction($action);
        $job->handle(app(\App\Services\UrlValidator::class), $httpService);

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_EXECUTED, $action->resolution_status);
        $this->assertNotNull($action->executed_at_utc);
    }

    // ==================== EXECUTION FLOW: passGate ====================

    public function test_pass_gate_without_request_reschedules_recurring_action(): void
    {
        $action = $this->createGatedAction([
            'resolution_status' => ScheduledAction::STATUS_AWAITING_RESPONSE,
            'request' => null,
            'recurrence_config' => ['frequency' => 1, 'unit' => 'd', 'end_type' => 'count', 'max_occurrences' => 3],
            'recurrence_count' => 0,
        ]);

        $action->passGate();

        $this->assertEquals(ScheduledAction::STATUS_RESOLVED, $action->resolution_status);
        $this->assertEquals(1, $action->recurrence_count);
    }

    public function test_pass_gate_without_request_marks_executed_on_final_occurrence(): void
    {
        $action = $this->createGatedAction([
            'resolution_status' => ScheduledAction::STATUS_AWAITING_RESPONSE,
            'request' => null,
            'recurrence_config' => ['frequency' => 1, 'unit' => 'd', 'end_type' => 'count', 'max_occurrences' => 2],
            'recurrence_count' => 1,
        ]);

        $action->passGate();

        $this->assertEquals(ScheduledAction::STATUS_EXECUTED, $action->resolution_status);
    }

    // ==================== CANCELLATION ====================

    public function test_cancellation_stops_future_recurrences(): void
    {
        $action = $this->createAction([
            'resolution_status' => ScheduledAction::STATUS_RESOLVED,
            'recurrence_config' => ['frequency' => 1, 'unit' => 'h', 'end_type' => 'never'],
            'recurrence_count' => 2,
        ]);

        $action->cancel();

        $this->assertEquals(ScheduledAction::STATUS_CANCELLED, $action->resolution_status);
    }

    // ==================== API: CREATE WITH RECURRENCE ====================

    public function test_can_create_action_with_recurrence(): void
    {
        Sanctum::actingAs($this->user);
        Queue::fake();

        $response = $this->postJson('/api/v1/actions', [
            'name' => 'Recurring Action',
            'mode' => 'immediate',
            'execute_at' => now()->addHour()->toIso8601String(),
            'request' => [
                'url' => 'https://example.com/webhook',
                'method' => 'POST',
            ],
            'recurrence' => [
                'frequency' => 2,
                'unit' => 'h',
                'end_type' => 'count',
                'max_occurrences' => 5,
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.is_recurring', true);
        $response->assertJsonPath('data.recurrence.frequency', 2);
        $response->assertJsonPath('data.recurrence.unit', 'h');
        $response->assertJsonPath('data.recurrence.end_type', 'count');
        $response->assertJsonPath('data.recurrence.max_occurrences', 5);
        $this->assertArrayHasKey('recurrence_count', $response->json('data'));
        $this->assertEquals(0, $response->json('data.recurrence_count'));
    }

    public function test_can_create_action_with_repeat_alias(): void
    {
        Sanctum::actingAs($this->user);
        Queue::fake();

        $response = $this->postJson('/api/v1/actions', [
            'name' => 'Repeating Action',
            'mode' => 'immediate',
            'execute_at' => now()->addHour()->toIso8601String(),
            'request' => [
                'url' => 'https://example.com/webhook',
                'method' => 'POST',
            ],
            'repeat' => [
                'frequency' => 1,
                'unit' => 'd',
                'end_type' => 'never',
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.is_recurring', true);
    }

    public function test_recurrence_validation_rejects_short_interval(): void
    {
        Sanctum::actingAs($this->user);
        Queue::fake();

        $response = $this->postJson('/api/v1/actions', [
            'name' => 'Too Fast',
            'mode' => 'immediate',
            'execute_at' => now()->addHour()->toIso8601String(),
            'request' => [
                'url' => 'https://example.com/webhook',
                'method' => 'POST',
            ],
            'recurrence' => [
                'frequency' => 3,
                'unit' => 'm',
                'end_type' => 'never',
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['recurrence.frequency']);
    }

    public function test_recurrence_validation_requires_max_occurrences_for_count(): void
    {
        Sanctum::actingAs($this->user);
        Queue::fake();

        $response = $this->postJson('/api/v1/actions', [
            'name' => 'Missing Max',
            'mode' => 'immediate',
            'execute_at' => now()->addHour()->toIso8601String(),
            'request' => [
                'url' => 'https://example.com/webhook',
                'method' => 'POST',
            ],
            'recurrence' => [
                'frequency' => 1,
                'unit' => 'h',
                'end_type' => 'count',
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['recurrence.max_occurrences']);
    }

    public function test_non_recurring_action_has_is_recurring_false(): void
    {
        Sanctum::actingAs($this->user);
        Queue::fake();

        $response = $this->postJson('/api/v1/actions', [
            'name' => 'One-time',
            'mode' => 'immediate',
            'execute_at' => now()->addHour()->toIso8601String(),
            'request' => [
                'url' => 'https://example.com/webhook',
                'method' => 'POST',
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.is_recurring', false);
    }

    // ==================== API: RECURRING FILTER ====================

    public function test_can_filter_recurring_actions(): void
    {
        Sanctum::actingAs($this->user);

        $this->createAction(['name' => 'Recurring', 'recurrence_config' => ['frequency' => 1, 'unit' => 'h', 'end_type' => 'never']]);
        $this->createAction(['name' => 'One-time', 'recurrence_config' => null]);

        $response = $this->getJson('/api/v1/actions?recurring=recurring');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Recurring', $response->json('data.0.name'));

        $response = $this->getJson('/api/v1/actions?recurring=one-time');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('One-time', $response->json('data.0.name'));
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

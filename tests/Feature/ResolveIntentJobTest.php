<?php

namespace Tests\Feature;

use App\Jobs\ResolveIntentJob;
use App\Models\ScheduledAction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolveIntentJobTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_resolves_absolute_intent(): void
    {
        $executeAt = now()->addHours(2);

        $action = $this->createPendingAction([
            'intent_payload' => ['execute_at' => $executeAt->toIso8601String()],
        ]);

        $job = new ResolveIntentJob($action);
        $job->handle(app(\App\Services\IntentResolver::class));

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_RESOLVED, $action->resolution_status);
        $this->assertEquals($executeAt->utc()->format('Y-m-d H:i'), $action->execute_at_utc->format('Y-m-d H:i'));
    }

    public function test_resolves_delay_intent(): void
    {
        Carbon::setTestNow('2025-06-15 10:00:00');

        $action = $this->createPendingAction([
            'intent_payload' => ['delay' => '2h'],
        ]);

        $job = new ResolveIntentJob($action);
        $job->handle(app(\App\Services\IntentResolver::class));

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_RESOLVED, $action->resolution_status);
        $this->assertEquals('2025-06-15 12:00:00', $action->execute_at_utc->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_resolves_preset_intent(): void
    {
        Carbon::setTestNow('2025-06-15 10:00:00');

        $action = $this->createPendingAction([
            'intent_payload' => ['preset' => 'tomorrow'],
        ]);

        $job = new ResolveIntentJob($action);
        $job->handle(app(\App\Services\IntentResolver::class));

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_RESOLVED, $action->resolution_status);
        $this->assertEquals('2025-06-16', $action->execute_at_utc->format('Y-m-d'));

        Carbon::setTestNow();
    }

    public function test_uses_action_timezone(): void
    {
        Carbon::setTestNow('2025-06-15 10:00:00', 'UTC');

        $action = $this->createPendingAction([
            'intent_payload' => ['delay' => '1h'],
            'timezone' => 'America/New_York',
        ]);

        $job = new ResolveIntentJob($action);
        $job->handle(app(\App\Services\IntentResolver::class));

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_RESOLVED, $action->resolution_status);

        Carbon::setTestNow();
    }

    public function test_skips_non_pending_action(): void
    {
        $action = ScheduledAction::create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'name' => 'Test Action',
            'mode' => ScheduledAction::MODE_IMMEDIATE,
            'intent_type' => ScheduledAction::INTENT_WALL_CLOCK,
            'intent_payload' => ['delay' => '1h'],
            'resolution_status' => ScheduledAction::STATUS_RESOLVED, // Already resolved
            'execute_at_utc' => now()->addHour(),
            'request' => ['url' => 'https://example.com'],
        ]);

        $originalExecuteAt = $action->execute_at_utc->copy();

        $job = new ResolveIntentJob($action);
        $job->handle(app(\App\Services\IntentResolver::class));

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_RESOLVED, $action->resolution_status);
        $this->assertEquals($originalExecuteAt->format('Y-m-d H:i:s'), $action->execute_at_utc->format('Y-m-d H:i:s'));
    }

    public function test_marks_failed_on_invalid_intent(): void
    {
        $action = $this->createPendingAction([
            'intent_payload' => ['invalid_key' => 'value'],
        ]);

        $job = new ResolveIntentJob($action);
        $job->handle(app(\App\Services\IntentResolver::class));

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_FAILED, $action->resolution_status);
        $this->assertStringContainsString('Intent resolution failed', $action->failure_reason);
    }

    public function test_marks_failed_on_invalid_delay_format(): void
    {
        $action = $this->createPendingAction([
            'intent_payload' => ['delay' => 'invalid'],
        ]);

        $job = new ResolveIntentJob($action);
        $job->handle(app(\App\Services\IntentResolver::class));

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_FAILED, $action->resolution_status);
        $this->assertStringContainsString('Invalid delay format', $action->failure_reason);
    }

    public function test_marks_failed_on_unknown_preset(): void
    {
        $action = $this->createPendingAction([
            'intent_payload' => ['preset' => 'unknown_preset'],
        ]);

        $job = new ResolveIntentJob($action);
        $job->handle(app(\App\Services\IntentResolver::class));

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_FAILED, $action->resolution_status);
        $this->assertStringContainsString('Unknown preset', $action->failure_reason);
    }

    public function test_handles_empty_intent_payload(): void
    {
        $action = $this->createPendingAction([
            'intent_payload' => [],
        ]);

        $job = new ResolveIntentJob($action);
        $job->handle(app(\App\Services\IntentResolver::class));

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_FAILED, $action->resolution_status);
    }

    public function test_handles_missing_required_fields_in_intent(): void
    {
        $action = $this->createPendingAction([
            'intent_payload' => ['some_invalid_key' => 'value'],
        ]);

        $job = new ResolveIntentJob($action);
        $job->handle(app(\App\Services\IntentResolver::class));

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_FAILED, $action->resolution_status);
    }

    public function test_defaults_to_utc_when_no_timezone(): void
    {
        Carbon::setTestNow('2025-06-15 10:00:00');

        $action = $this->createPendingAction([
            'intent_payload' => ['delay' => '1h'],
            'timezone' => null,
        ]);

        $job = new ResolveIntentJob($action);
        $job->handle(app(\App\Services\IntentResolver::class));

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_RESOLVED, $action->resolution_status);
        $this->assertEquals('2025-06-15 11:00:00', $action->execute_at_utc->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    private function createPendingAction(array $attributes = []): ScheduledAction
    {
        return ScheduledAction::create(array_merge([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'name' => 'Test Action',
            'mode' => ScheduledAction::MODE_IMMEDIATE,
            'intent_type' => ScheduledAction::INTENT_WALL_CLOCK,
            'intent_payload' => ['delay' => '1h'],
            'resolution_status' => ScheduledAction::STATUS_PENDING_RESOLUTION,
            'request' => ['url' => 'https://example.com', 'method' => 'POST'],
        ], $attributes));
    }
}

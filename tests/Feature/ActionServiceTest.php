<?php

namespace Tests\Feature;

use App\Exceptions\DomainVerificationRequiredException;
use App\Jobs\ResolveIntentJob;
use App\Models\ActionCoordinationKey;
use App\Models\ScheduledAction;
use App\Models\User;
use App\Models\VerifiedDomain;
use App\Services\ActionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ActionServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ActionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->service = app(ActionService::class);
        Queue::fake();
    }

    // ==================== CREATE IMMEDIATE ACTION ====================

    public function test_create_immediate_action(): void
    {
        $result = $this->service->create($this->user, [
            'name' => 'Test Action',
            'mode' => ScheduledAction::MODE_IMMEDIATE,
            'intent' => ['delay' => '1h'],
            'request' => ['url' => 'https://example.com/webhook', 'method' => 'POST'],
        ]);

        $action = $result['action'];

        $this->assertInstanceOf(ScheduledAction::class, $action);
        $this->assertEquals('Test Action', $action->name);
        $this->assertEquals(ScheduledAction::MODE_IMMEDIATE, $action->mode);
        $this->assertEquals(ScheduledAction::STATUS_PENDING_RESOLUTION, $action->resolution_status);
        $this->assertEquals($this->user->account_id, $action->account_id);
        $this->assertNotNull($action->request);
    }

    public function test_create_action_dispatches_resolve_intent_job(): void
    {
        $result = $this->service->create($this->user, [
            'name' => 'Test Action',
            'intent' => ['delay' => '1h'],
            'request' => ['url' => 'https://example.com/webhook'],
        ]);

        Queue::assertPushed(ResolveIntentJob::class, function ($job) use ($result) {
            return $job->action->id === $result['action']->id;
        });
    }

    public function test_create_action_with_absolute_intent(): void
    {
        $executeAt = now()->addDays(5);

        $result = $this->service->create($this->user, [
            'name' => 'Absolute Intent Action',
            'execute_at' => $executeAt->toIso8601String(),
            'request' => ['url' => 'https://example.com/webhook'],
        ]);

        $action = $result['action'];

        $this->assertEquals(ScheduledAction::INTENT_ABSOLUTE, $action->intent_type);
        $this->assertArrayHasKey('execute_at', $action->intent_payload);
    }

    public function test_create_action_with_wall_clock_intent(): void
    {
        $result = $this->service->create($this->user, [
            'name' => 'Wall Clock Intent Action',
            'intent' => ['preset' => 'tomorrow'],
            'request' => ['url' => 'https://example.com/webhook'],
        ]);

        $action = $result['action'];

        $this->assertEquals(ScheduledAction::INTENT_WALL_CLOCK, $action->intent_type);
        $this->assertArrayHasKey('preset', $action->intent_payload);
    }

    public function test_create_action_with_timezone(): void
    {
        $result = $this->service->create($this->user, [
            'name' => 'Timezone Action',
            'timezone' => 'America/New_York',
            'intent' => ['delay' => '1h'],
            'request' => ['url' => 'https://example.com/webhook'],
        ]);

        $this->assertEquals('America/New_York', $result['action']->timezone);
    }

    public function test_create_action_with_idempotency_key(): void
    {
        $result = $this->service->create($this->user, [
            'name' => 'Idempotent Action',
            'idempotency_key' => 'unique-key-123',
            'intent' => ['delay' => '1h'],
            'request' => ['url' => 'https://example.com/webhook'],
        ]);

        $this->assertEquals('unique-key-123', $result['action']->idempotency_key);
    }

    public function test_create_action_with_callback_url(): void
    {
        $result = $this->service->create($this->user, [
            'name' => 'Callback Action',
            'callback_url' => 'https://example.com/callback',
            'intent' => ['delay' => '1h'],
            'request' => ['url' => 'https://example.com/webhook'],
        ]);

        $this->assertEquals('https://example.com/callback', $result['action']->callback_url);
    }

    public function test_create_action_with_retry_settings(): void
    {
        $result = $this->service->create($this->user, [
            'name' => 'Retry Action',
            'max_attempts' => 3,
            'retry_strategy' => 'linear',
            'intent' => ['delay' => '1h'],
            'request' => ['url' => 'https://example.com/webhook'],
        ]);

        $action = $result['action'];

        $this->assertEquals(3, $action->max_attempts);
        $this->assertEquals('linear', $action->retry_strategy);
    }

    public function test_create_action_uses_user_webhook_secret(): void
    {
        $this->user->update(['webhook_secret' => 'user-default-secret']);

        $result = $this->service->create($this->user, [
            'name' => 'Default Secret Action',
            'intent' => ['delay' => '1h'],
            'request' => ['url' => 'https://example.com/webhook'],
        ]);

        $this->assertEquals('user-default-secret', $result['action']->webhook_secret);
    }

    public function test_create_action_can_override_webhook_secret(): void
    {
        $this->user->update(['webhook_secret' => 'user-default-secret']);

        $result = $this->service->create($this->user, [
            'name' => 'Custom Secret Action',
            'webhook_secret' => 'custom-secret',
            'intent' => ['delay' => '1h'],
            'request' => ['url' => 'https://example.com/webhook'],
        ]);

        $this->assertEquals('custom-secret', $result['action']->webhook_secret);
    }

    // ==================== CREATE GATED ACTION ====================

    public function test_create_gated_action(): void
    {
        $result = $this->service->create($this->user, [
            'name' => 'Gated Action',
            'mode' => ScheduledAction::MODE_GATED,
            'intent' => ['delay' => '1h'],
            'gate' => [
                'message' => 'Approve deployment?',
                'recipients' => ['admin@example.com'],
                'channels' => ['email'],
            ],
        ]);

        $action = $result['action'];

        $this->assertEquals(ScheduledAction::MODE_GATED, $action->mode);
        $this->assertEquals('Approve deployment?', $action->getGateMessage());
        $this->assertEquals(['admin@example.com'], $action->getGateRecipients());
    }

    public function test_create_gated_action_with_request(): void
    {
        $result = $this->service->create($this->user, [
            'name' => 'Gated with Request',
            'mode' => ScheduledAction::MODE_GATED,
            'intent' => ['delay' => '1h'],
            'gate' => [
                'message' => 'Approve?',
                'recipients' => ['admin@example.com'],
            ],
            'request' => ['url' => 'https://example.com/webhook', 'method' => 'POST'],
        ]);

        $action = $result['action'];

        $this->assertTrue($action->isGated());
        $this->assertTrue($action->hasRequest());
    }

    // ==================== COORDINATION KEYS ====================

    public function test_create_action_with_coordination_keys(): void
    {
        $result = $this->service->create($this->user, [
            'name' => 'Coordinated Action',
            'coordination_keys' => ['deploy:prod', 'service:api'],
            'intent' => ['delay' => '1h'],
            'request' => ['url' => 'https://example.com/webhook'],
        ]);

        $action = $result['action'];
        $action->refresh();

        $this->assertCount(2, $action->coordination_keys);
        $this->assertContains('deploy:prod', $action->coordination_keys);
        $this->assertContains('service:api', $action->coordination_keys);
    }

    public function test_create_action_stores_coordination_config(): void
    {
        $result = $this->service->create($this->user, [
            'name' => 'Coordinated Action',
            'coordination_keys' => ['deploy:prod'],
            'coordination' => [
                'on_create' => 'replace_existing',
                'on_execute' => [
                    'condition' => 'wait_for_previous',
                    'on_condition_not_met' => 'reschedule',
                ],
            ],
            'intent' => ['delay' => '1h'],
            'request' => ['url' => 'https://example.com/webhook'],
        ]);

        $action = $result['action'];

        $this->assertEquals('replace_existing', $action->coordination_config['on_create']);
        $this->assertEquals('wait_for_previous', $action->coordination_config['on_execute']['condition']);
    }

    // ==================== CANCEL ====================

    public function test_cancel_action(): void
    {
        $action = $this->createAction(ScheduledAction::STATUS_RESOLVED);

        $this->service->cancel($action);

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_CANCELLED, $action->resolution_status);
    }

    public function test_cancel_throws_for_executed_action(): void
    {
        $action = $this->createAction(ScheduledAction::STATUS_EXECUTED);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->cancel($action);
    }

    // ==================== RESCHEDULE ====================

    public function test_reschedule_action(): void
    {
        $action = $this->createAction(ScheduledAction::STATUS_RESOLVED);
        $originalExecuteAt = $action->execute_at_utc;

        $this->service->reschedule($action, ['delay' => '2h']);

        $action->refresh();

        $this->assertEquals(ScheduledAction::STATUS_PENDING_RESOLUTION, $action->resolution_status);
        $this->assertEquals(ScheduledAction::INTENT_WALL_CLOCK, $action->intent_type);
        $this->assertEquals(['delay' => '2h'], $action->intent_payload);
        $this->assertNull($action->execute_at_utc);

        Queue::assertPushed(ResolveIntentJob::class);
    }

    public function test_reschedule_throws_for_executed_action(): void
    {
        $action = $this->createAction(ScheduledAction::STATUS_EXECUTED);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->reschedule($action, ['delay' => '2h']);
    }

    // ==================== SNOOZE ====================

    public function test_snooze_gated_action(): void
    {
        $action = $this->createGatedAction(ScheduledAction::STATUS_AWAITING_RESPONSE);
        $action->update(['snooze_count' => 0]);

        $this->service->snooze($action, 'tomorrow');

        $action->refresh();

        $this->assertEquals(ScheduledAction::STATUS_PENDING_RESOLUTION, $action->resolution_status);
        $this->assertEquals(1, $action->snooze_count);
        $this->assertEquals(['preset' => 'tomorrow'], $action->intent_payload);

        Queue::assertPushed(ResolveIntentJob::class);
    }

    public function test_snooze_throws_for_immediate_action(): void
    {
        $action = $this->createAction(ScheduledAction::STATUS_AWAITING_RESPONSE);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only gated actions can be snoozed');
        $this->service->snooze($action, 'tomorrow');
    }

    public function test_snooze_throws_when_max_snoozes_reached(): void
    {
        $action = $this->createGatedAction(ScheduledAction::STATUS_AWAITING_RESPONSE);
        $action->update(['snooze_count' => 5]); // Max is 5

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Maximum snoozes reached');
        $this->service->snooze($action, 'tomorrow');
    }

    // ==================== DOMAIN VERIFICATION ====================

    public function test_create_action_requires_domain_verification_when_threshold_exceeded(): void
    {
        $domain = 'unverified-domain.com';

        // Create enough actions to exceed daily threshold (10)
        for ($i = 0; $i < 10; $i++) {
            ScheduledAction::create([
                'account_id' => $this->user->account_id,
                'created_by_user_id' => $this->user->id,
                'name' => "Action {$i}",
                'mode' => ScheduledAction::MODE_IMMEDIATE,
                'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
                'intent_payload' => [],
                'resolution_status' => ScheduledAction::STATUS_RESOLVED,
                'request' => ['url' => "https://{$domain}/webhook"],
            ]);
        }

        // Next create should require verification
        $this->expectException(DomainVerificationRequiredException::class);

        $this->service->create($this->user, [
            'name' => 'Unverified Domain Action',
            'intent' => ['delay' => '1h'],
            'request' => ['url' => "https://{$domain}/webhook"],
        ]);
    }

    public function test_create_action_succeeds_with_verified_domain(): void
    {
        $domain = 'verified-domain.com';

        // Create enough actions to exceed daily threshold
        for ($i = 0; $i < 10; $i++) {
            ScheduledAction::create([
                'account_id' => $this->user->account_id,
                'created_by_user_id' => $this->user->id,
                'name' => "Action {$i}",
                'mode' => ScheduledAction::MODE_IMMEDIATE,
                'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
                'intent_payload' => [],
                'resolution_status' => ScheduledAction::STATUS_RESOLVED,
                'request' => ['url' => "https://{$domain}/webhook"],
            ]);
        }

        // Verify the domain
        VerifiedDomain::create([
            'account_id' => $this->user->account_id,
            'domain' => $domain,
            'verification_token' => VerifiedDomain::generateToken(),
            'verified_at' => now(),
            'expires_at' => now()->addMonths(12),
            'method' => VerifiedDomain::METHOD_DNS,
        ]);

        $result = $this->service->create($this->user, [
            'name' => 'Verified Domain Action',
            'intent' => ['delay' => '1h'],
            'request' => ['url' => "https://{$domain}/webhook"],
        ]);

        $this->assertNotNull($result['action']);
    }

    public function test_create_action_under_threshold_does_not_require_verification(): void
    {
        // First action to unverified domain should work (under threshold)
        $result = $this->service->create($this->user, [
            'name' => 'Under Threshold Action',
            'intent' => ['delay' => '1h'],
            'request' => ['url' => 'https://new-domain.com/webhook'],
        ]);

        $this->assertNotNull($result['action']);
    }

    public function test_admin_user_skips_domain_verification(): void
    {
        $this->user->update(['is_admin' => true]);

        // Create enough actions to exceed threshold
        $domain = 'admin-domain.com';
        for ($i = 0; $i < 10; $i++) {
            ScheduledAction::create([
                'account_id' => $this->user->account_id,
                'created_by_user_id' => $this->user->id,
                'name' => "Action {$i}",
                'mode' => ScheduledAction::MODE_IMMEDIATE,
                'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
                'intent_payload' => [],
                'resolution_status' => ScheduledAction::STATUS_RESOLVED,
                'request' => ['url' => "https://{$domain}/webhook"],
            ]);
        }

        // Admin should still be able to create without verification
        $result = $this->service->create($this->user, [
            'name' => 'Admin Action',
            'intent' => ['delay' => '1h'],
            'request' => ['url' => "https://{$domain}/webhook"],
        ]);

        $this->assertNotNull($result['action']);
    }

    // ==================== QUOTA TRACKING ====================

    public function test_create_action_records_quota_usage(): void
    {
        // Use a domain that's under threshold (no verification needed)
        $this->service->create($this->user, [
            'name' => 'Quota Test Action',
            'intent' => ['delay' => '1h'],
            'request' => ['url' => 'https://quota-test.com/webhook'],
        ]);

        // Check that usage counter was updated
        $counter = \App\Models\UsageCounter::forCurrentMonth($this->user->account_id);
        $this->assertEquals(1, $counter->actions_created);
    }

    // ==================== HELPERS ====================

    private function createAction(string $status): ScheduledAction
    {
        return ScheduledAction::create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'name' => 'Test Action',
            'mode' => ScheduledAction::MODE_IMMEDIATE,
            'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
            'intent_payload' => [],
            'resolution_status' => $status,
            'execute_at_utc' => now()->addHour(),
            'request' => ['url' => 'https://example.com', 'method' => 'POST'],
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
            'execute_at_utc' => now()->addHour(),
            'gate' => [
                'message' => 'Test gate message',
                'recipients' => ['test@example.com'],
                'channels' => ['email'],
                'timeout' => '7d',
                'max_snoozes' => 5,
            ],
        ]);
    }
}

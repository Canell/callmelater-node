<?php

namespace Tests\Feature;

use App\Jobs\DeliverHttpAction;
use App\Jobs\DispatcherJob;
use App\Models\ActionCoordinationKey;
use App\Models\ScheduledAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoordinationOnExecuteTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
        Queue::fake();
    }

    // ==================== VALIDATION TESTS ====================

    public function test_can_create_action_with_on_execute_condition(): void
    {
        $response = $this->postJson('/api/v1/actions', [
            'name' => 'Coordinated Action',
            'intent' => ['delay' => '1h'],
            'coordination_keys' => ['deploy:prod'],
            'coordination' => [
                'on_execute' => [
                    'condition' => 'skip_if_previous_pending',
                ],
            ],
            'request' => [
                'url' => 'https://example.com/webhook',
            ],
        ]);

        $response->assertStatus(201);

        $action = ScheduledAction::find($response->json('data.id'));
        $this->assertEquals('skip_if_previous_pending', $action->coordination_config['on_execute']['condition']);
    }

    public function test_on_execute_requires_coordination_keys(): void
    {
        $response = $this->postJson('/api/v1/actions', [
            'name' => 'Coordinated Action',
            'intent' => ['delay' => '1h'],
            'coordination' => [
                'on_execute' => [
                    'condition' => 'skip_if_previous_pending',
                ],
            ],
            'request' => [
                'url' => 'https://example.com/webhook',
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['coordination_keys']);
    }

    public function test_validates_on_execute_condition_values(): void
    {
        $response = $this->postJson('/api/v1/actions', [
            'name' => 'Coordinated Action',
            'intent' => ['delay' => '1h'],
            'coordination_keys' => ['test:1'],
            'coordination' => [
                'on_execute' => [
                    'condition' => 'invalid_condition',
                ],
            ],
            'request' => [
                'url' => 'https://example.com/webhook',
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['coordination.on_execute.condition']);
    }

    public function test_validates_on_condition_not_met_values(): void
    {
        $response = $this->postJson('/api/v1/actions', [
            'name' => 'Coordinated Action',
            'intent' => ['delay' => '1h'],
            'coordination_keys' => ['test:1'],
            'coordination' => [
                'on_execute' => [
                    'condition' => 'wait_for_previous',
                    'on_condition_not_met' => 'invalid_behavior',
                ],
            ],
            'request' => [
                'url' => 'https://example.com/webhook',
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['coordination.on_execute.on_condition_not_met']);
    }

    public function test_can_set_reschedule_options(): void
    {
        $response = $this->postJson('/api/v1/actions', [
            'name' => 'Coordinated Action',
            'intent' => ['delay' => '1h'],
            'coordination_keys' => ['deploy:prod'],
            'coordination' => [
                'on_execute' => [
                    'condition' => 'wait_for_previous',
                    'on_condition_not_met' => 'reschedule',
                    'reschedule_delay' => 600,
                    'max_reschedules' => 5,
                ],
            ],
            'request' => [
                'url' => 'https://example.com/webhook',
            ],
        ]);

        $response->assertStatus(201);

        $action = ScheduledAction::find($response->json('data.id'));
        $this->assertEquals(600, $action->coordination_config['on_execute']['reschedule_delay']);
        $this->assertEquals(5, $action->coordination_config['on_execute']['max_reschedules']);
    }

    // ==================== DISPATCHER TESTS ====================

    public function test_skip_if_previous_pending_cancels_when_previous_exists(): void
    {
        // Create first action that is still pending
        $first = $this->createActionWithKey('test:1', ScheduledAction::STATUS_RESOLVED);
        $first->update(['execute_at_utc' => now()->addHour()]);

        // Create second action with skip_if_previous_pending
        $second = $this->createActionWithKey('test:1', ScheduledAction::STATUS_RESOLVED, [
            'on_execute' => ['condition' => 'skip_if_previous_pending'],
        ]);
        $second->update(['execute_at_utc' => now()->subMinute()]);

        // Run dispatcher
        (new DispatcherJob)->handle();

        // Second action should be cancelled
        $second->refresh();
        $this->assertEquals(ScheduledAction::STATUS_CANCELLED, $second->resolution_status);
        $this->assertStringContains('skip_if_previous_pending', $second->failure_reason);
    }

    public function test_skip_if_previous_pending_proceeds_when_previous_is_terminal(): void
    {
        // Create first action that is executed (terminal)
        $first = $this->createActionWithKey('test:1', ScheduledAction::STATUS_EXECUTED);

        // Create second action with skip_if_previous_pending
        $second = $this->createActionWithKey('test:1', ScheduledAction::STATUS_RESOLVED, [
            'on_execute' => ['condition' => 'skip_if_previous_pending'],
        ]);
        $second->update(['execute_at_utc' => now()->subMinute()]);

        // Run dispatcher
        (new DispatcherJob)->handle();

        // Second action should be executing
        $second->refresh();
        $this->assertEquals(ScheduledAction::STATUS_EXECUTING, $second->resolution_status);

        Queue::assertPushed(DeliverHttpAction::class);
    }

    public function test_execute_if_previous_failed_proceeds_when_previous_failed(): void
    {
        // Create first action that failed
        $first = $this->createActionWithKey('test:1', ScheduledAction::STATUS_FAILED);

        // Create second action with execute_if_previous_failed
        $second = $this->createActionWithKey('test:1', ScheduledAction::STATUS_RESOLVED, [
            'on_execute' => ['condition' => 'execute_if_previous_failed'],
        ]);
        $second->update(['execute_at_utc' => now()->subMinute()]);

        // Run dispatcher
        (new DispatcherJob)->handle();

        // Second action should proceed
        $second->refresh();
        $this->assertEquals(ScheduledAction::STATUS_EXECUTING, $second->resolution_status);
    }

    public function test_execute_if_previous_failed_cancels_when_previous_succeeded(): void
    {
        // Create first action that succeeded
        $first = $this->createActionWithKey('test:1', ScheduledAction::STATUS_EXECUTED);

        // Create second action with execute_if_previous_failed
        $second = $this->createActionWithKey('test:1', ScheduledAction::STATUS_RESOLVED, [
            'on_execute' => ['condition' => 'execute_if_previous_failed'],
        ]);
        $second->update(['execute_at_utc' => now()->subMinute()]);

        // Run dispatcher
        (new DispatcherJob)->handle();

        // Second action should be cancelled
        $second->refresh();
        $this->assertEquals(ScheduledAction::STATUS_CANCELLED, $second->resolution_status);
    }

    public function test_execute_if_previous_succeeded_proceeds_when_previous_succeeded(): void
    {
        // Create first action that succeeded
        $first = $this->createActionWithKey('test:1', ScheduledAction::STATUS_EXECUTED);

        // Create second action with execute_if_previous_succeeded
        $second = $this->createActionWithKey('test:1', ScheduledAction::STATUS_RESOLVED, [
            'on_execute' => ['condition' => 'execute_if_previous_succeeded'],
        ]);
        $second->update(['execute_at_utc' => now()->subMinute()]);

        // Run dispatcher
        (new DispatcherJob)->handle();

        // Second action should proceed
        $second->refresh();
        $this->assertEquals(ScheduledAction::STATUS_EXECUTING, $second->resolution_status);
    }

    public function test_wait_for_previous_reschedules_when_previous_pending(): void
    {
        // Create first action that is still pending
        $first = $this->createActionWithKey('test:1', ScheduledAction::STATUS_RESOLVED);
        $first->update(['execute_at_utc' => now()->addHour()]);

        // Create second action with wait_for_previous + reschedule
        $second = $this->createActionWithKey('test:1', ScheduledAction::STATUS_RESOLVED, [
            'on_execute' => [
                'condition' => 'wait_for_previous',
                'on_condition_not_met' => 'reschedule',
                'reschedule_delay' => 300,
            ],
        ]);
        $originalExecuteAt = now()->subMinute();
        $second->update(['execute_at_utc' => $originalExecuteAt]);

        // Run dispatcher
        (new DispatcherJob)->handle();

        // Second action should be rescheduled (still resolved, but with new execute_at)
        $second->refresh();
        $this->assertEquals(ScheduledAction::STATUS_RESOLVED, $second->resolution_status);
        $this->assertEquals(1, $second->coordination_reschedule_count);
        $this->assertTrue($second->execute_at_utc->gt($originalExecuteAt));
    }

    public function test_wait_for_previous_cancels_after_max_reschedules(): void
    {
        // Create first action that is still pending
        $first = $this->createActionWithKey('test:1', ScheduledAction::STATUS_RESOLVED);
        $first->update(['execute_at_utc' => now()->addHour()]);

        // Create second action at max reschedules
        $second = $this->createActionWithKey('test:1', ScheduledAction::STATUS_RESOLVED, [
            'on_execute' => [
                'condition' => 'wait_for_previous',
                'on_condition_not_met' => 'reschedule',
                'max_reschedules' => 3,
            ],
        ]);
        $second->update([
            'execute_at_utc' => now()->subMinute(),
            'coordination_reschedule_count' => 3, // Already at max
        ]);

        // Run dispatcher
        (new DispatcherJob)->handle();

        // Second action should be cancelled
        $second->refresh();
        $this->assertEquals(ScheduledAction::STATUS_CANCELLED, $second->resolution_status);
        $this->assertStringContains('max reschedules', $second->failure_reason);
    }

    public function test_on_execute_only_checks_same_account(): void
    {
        // Create action for different user
        $otherUser = User::factory()->create();
        $otherAction = ScheduledAction::create([
            'account_id' => $otherUser->account_id,
            'created_by_user_id' => $otherUser->id,
            'name' => 'Other Account Action',
            'mode' => ScheduledAction::MODE_IMMEDIATE,
            'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
            'intent_payload' => [],
            'resolution_status' => ScheduledAction::STATUS_RESOLVED,
            'execute_at_utc' => now()->addHour(),
            'request' => ['url' => 'https://example.com', 'method' => 'POST'],
        ]);
        ActionCoordinationKey::create([
            'action_id' => $otherAction->id,
            'coordination_key' => 'test:1',
        ]);

        // Create action for current user with skip_if_previous_pending
        $myAction = $this->createActionWithKey('test:1', ScheduledAction::STATUS_RESOLVED, [
            'on_execute' => ['condition' => 'skip_if_previous_pending'],
        ]);
        $myAction->update(['execute_at_utc' => now()->subMinute()]);

        // Run dispatcher
        (new DispatcherJob)->handle();

        // My action should proceed (other account's action doesn't count)
        $myAction->refresh();
        $this->assertEquals(ScheduledAction::STATUS_EXECUTING, $myAction->resolution_status);
    }

    public function test_action_without_on_execute_proceeds_normally(): void
    {
        // Create first action
        $first = $this->createActionWithKey('test:1', ScheduledAction::STATUS_RESOLVED);
        $first->update(['execute_at_utc' => now()->addHour()]);

        // Create second action WITHOUT on_execute condition
        $second = ScheduledAction::create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'name' => 'No Condition',
            'mode' => ScheduledAction::MODE_IMMEDIATE,
            'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
            'intent_payload' => [],
            'resolution_status' => ScheduledAction::STATUS_RESOLVED,
            'execute_at_utc' => now()->subMinute(),
            'request' => ['url' => 'https://example.com', 'method' => 'POST'],
        ]);
        ActionCoordinationKey::create([
            'action_id' => $second->id,
            'coordination_key' => 'test:1',
        ]);

        // Run dispatcher
        (new DispatcherJob)->handle();

        // Second action should proceed (no condition to evaluate)
        $second->refresh();
        $this->assertEquals(ScheduledAction::STATUS_EXECUTING, $second->resolution_status);
    }

    // ==================== API RESPONSE TESTS ====================

    public function test_show_action_includes_related_actions(): void
    {
        // Create multiple actions with same coordination key
        $first = $this->createActionWithKey('group:123', ScheduledAction::STATUS_EXECUTED);
        $second = $this->createActionWithKey('group:123', ScheduledAction::STATUS_RESOLVED);
        $third = $this->createActionWithKey('group:123', ScheduledAction::STATUS_PENDING_RESOLUTION);

        $response = $this->getJson("/api/v1/actions/{$second->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'related_actions' => [
                        '*' => ['id', 'name', 'status', 'created_at'],
                    ],
                ],
            ]);

        $relatedIds = collect($response->json('data.related_actions'))->pluck('id')->toArray();
        $this->assertContains($first->id, $relatedIds);
        $this->assertContains($third->id, $relatedIds);
        $this->assertNotContains($second->id, $relatedIds); // Should not include self
    }

    public function test_show_action_includes_replaced_by(): void
    {
        // Create original action
        $original = $this->createActionWithKey('test:1', ScheduledAction::STATUS_CANCELLED);

        // Create replacement action
        $replacement = $this->createActionWithKey('test:1', ScheduledAction::STATUS_RESOLVED);

        // Link them
        $original->update(['replaced_by_action_id' => $replacement->id]);

        $response = $this->getJson("/api/v1/actions/{$original->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'replaced_by' => ['id', 'name', 'status'],
                ],
            ])
            ->assertJsonPath('data.replaced_by.id', $replacement->id);
    }

    public function test_show_action_includes_coordination_config(): void
    {
        $action = $this->createActionWithKey('test:1', ScheduledAction::STATUS_RESOLVED, [
            'on_create' => 'replace_existing',
            'on_execute' => [
                'condition' => 'wait_for_previous',
                'on_condition_not_met' => 'reschedule',
            ],
        ]);

        $response = $this->getJson("/api/v1/actions/{$action->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.coordination_config.on_create', 'replace_existing')
            ->assertJsonPath('data.coordination_config.on_execute.condition', 'wait_for_previous');
    }

    public function test_show_action_includes_coordination_reschedule_count(): void
    {
        $action = $this->createActionWithKey('test:1', ScheduledAction::STATUS_RESOLVED, [
            'on_execute' => [
                'condition' => 'wait_for_previous',
                'on_condition_not_met' => 'reschedule',
            ],
        ]);
        $action->update(['coordination_reschedule_count' => 3]);

        $response = $this->getJson("/api/v1/actions/{$action->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.coordination_reschedule_count', 3);
    }

    // ==================== HELPERS ====================

    private function createActionWithKey(
        string $key,
        string $status,
        ?array $coordinationConfig = null
    ): ScheduledAction {
        $action = ScheduledAction::create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'name' => "Action with key {$key}",
            'mode' => ScheduledAction::MODE_IMMEDIATE,
            'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
            'intent_payload' => [],
            'resolution_status' => $status,
            'execute_at_utc' => now()->addHour(),
            'request' => ['url' => 'https://example.com', 'method' => 'POST'],
            'coordination_config' => $coordinationConfig,
        ]);

        ActionCoordinationKey::create([
            'action_id' => $action->id,
            'coordination_key' => $key,
        ]);

        return $action;
    }

    private function assertStringContains(string $needle, ?string $haystack): void
    {
        $this->assertNotNull($haystack, "Expected string to contain '{$needle}' but got null");
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Expected string to contain '{$needle}', got: {$haystack}"
        );
    }
}

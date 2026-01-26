<?php

namespace Tests\Feature;

use App\Jobs\ResolveIntentJob;
use App\Models\ActionCoordinationKey;
use App\Models\ScheduledAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoordinationOnCreateTest extends TestCase
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

    // ==================== COORDINATION KEYS VALIDATION ====================

    public function test_can_create_action_with_coordination_keys(): void
    {
        $response = $this->postJson('/api/v1/actions', [
            'name' => 'Coordinated Action',
            'intent' => ['delay' => '1h'],
            'coordination_keys' => ['deploy:prod', 'service:api'],
            'request' => ['url' => 'https://example.com/webhook'],
        ]);

        $response->assertStatus(201);

        $action = ScheduledAction::find($response->json('data.id'));
        $this->assertCount(2, $action->coordination_keys);
        $this->assertContains('deploy:prod', $action->coordination_keys);
        $this->assertContains('service:api', $action->coordination_keys);
    }

    public function test_coordination_keys_are_stored_in_pivot_table(): void
    {
        $response = $this->postJson('/api/v1/actions', [
            'name' => 'Coordinated Action',
            'intent' => ['delay' => '1h'],
            'coordination_keys' => ['key1', 'key2'],
            'request' => ['url' => 'https://example.com/webhook'],
        ]);

        $response->assertStatus(201);

        $actionId = $response->json('data.id');
        $this->assertDatabaseHas('action_coordination_keys', [
            'action_id' => $actionId,
            'coordination_key' => 'key1',
        ]);
        $this->assertDatabaseHas('action_coordination_keys', [
            'action_id' => $actionId,
            'coordination_key' => 'key2',
        ]);
    }

    public function test_coordination_keys_max_limit(): void
    {
        $response = $this->postJson('/api/v1/actions', [
            'name' => 'Too Many Keys',
            'intent' => ['delay' => '1h'],
            'coordination_keys' => ['k1', 'k2', 'k3', 'k4', 'k5', 'k6', 'k7', 'k8', 'k9', 'k10', 'k11'],
            'request' => ['url' => 'https://example.com/webhook'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['coordination_keys']);
    }

    public function test_coordination_keys_format_validation(): void
    {
        $response = $this->postJson('/api/v1/actions', [
            'name' => 'Invalid Key Format',
            'intent' => ['delay' => '1h'],
            'coordination_keys' => ['valid-key', 'invalid key with spaces'],
            'request' => ['url' => 'https://example.com/webhook'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['coordination_keys.1']);
    }

    public function test_coordination_keys_allow_valid_characters(): void
    {
        $response = $this->postJson('/api/v1/actions', [
            'name' => 'Valid Key Formats',
            'intent' => ['delay' => '1h'],
            'coordination_keys' => [
                'simple',
                'with-dashes',
                'with_underscores',
                'with:colons',
                'with.dots',
                'MixedCase123',
            ],
            'request' => ['url' => 'https://example.com/webhook'],
        ]);

        $response->assertStatus(201);
    }

    public function test_duplicate_coordination_keys_are_deduplicated(): void
    {
        $response = $this->postJson('/api/v1/actions', [
            'name' => 'Duplicate Keys',
            'intent' => ['delay' => '1h'],
            'coordination_keys' => ['same-key', 'same-key', 'other-key'],
            'request' => ['url' => 'https://example.com/webhook'],
        ]);

        $response->assertStatus(201);

        $action = ScheduledAction::find($response->json('data.id'));
        // Keys should be deduplicated
        $keyCount = ActionCoordinationKey::where('action_id', $action->id)->count();
        $this->assertEquals(2, $keyCount);
    }

    // ==================== ON_CREATE: REPLACE_EXISTING ====================

    public function test_replace_existing_cancels_previous_and_links(): void
    {
        // Create first action with a coordination key
        $first = $this->createActionWithKey('deploy:prod', ScheduledAction::STATUS_RESOLVED);

        // Create second action with replace_existing
        $response = $this->postJson('/api/v1/actions', [
            'name' => 'Replacement Action',
            'intent' => ['delay' => '1h'],
            'coordination_keys' => ['deploy:prod'],
            'coordination' => ['on_create' => 'replace_existing'],
            'request' => ['url' => 'https://example.com/webhook'],
        ]);

        $response->assertStatus(201);

        // First action should be cancelled
        $first->refresh();
        $this->assertEquals(ScheduledAction::STATUS_CANCELLED, $first->resolution_status);

        // First action should be linked to replacement
        $newActionId = $response->json('data.id');
        $this->assertEquals($newActionId, $first->replaced_by_action_id);

        // Response should include replaced_action_ids in meta
        $this->assertContains($first->id, $response->json('meta.replaced_action_ids', []));
    }

    public function test_replace_existing_cancels_multiple_actions(): void
    {
        // Create multiple actions with same key
        $first = $this->createActionWithKey('deploy:prod', ScheduledAction::STATUS_RESOLVED);
        $second = $this->createActionWithKey('deploy:prod', ScheduledAction::STATUS_PENDING_RESOLUTION);

        // Create replacement
        $response = $this->postJson('/api/v1/actions', [
            'name' => 'Replacement',
            'intent' => ['delay' => '1h'],
            'coordination_keys' => ['deploy:prod'],
            'coordination' => ['on_create' => 'replace_existing'],
            'request' => ['url' => 'https://example.com/webhook'],
        ]);

        $response->assertStatus(201);

        // Both should be cancelled and linked
        $first->refresh();
        $second->refresh();

        $this->assertEquals(ScheduledAction::STATUS_CANCELLED, $first->resolution_status);
        $this->assertEquals(ScheduledAction::STATUS_CANCELLED, $second->resolution_status);

        $newActionId = $response->json('data.id');
        $this->assertEquals($newActionId, $first->replaced_by_action_id);
        $this->assertEquals($newActionId, $second->replaced_by_action_id);
    }

    public function test_replace_existing_ignores_terminal_actions(): void
    {
        // Create executed action (terminal state)
        $executed = $this->createActionWithKey('deploy:prod', ScheduledAction::STATUS_EXECUTED);

        // Create replacement
        $response = $this->postJson('/api/v1/actions', [
            'name' => 'Replacement',
            'intent' => ['delay' => '1h'],
            'coordination_keys' => ['deploy:prod'],
            'coordination' => ['on_create' => 'replace_existing'],
            'request' => ['url' => 'https://example.com/webhook'],
        ]);

        $response->assertStatus(201);

        // Executed action should not be modified
        $executed->refresh();
        $this->assertEquals(ScheduledAction::STATUS_EXECUTED, $executed->resolution_status);
        $this->assertNull($executed->replaced_by_action_id);
    }

    public function test_replace_existing_ignores_executing_actions(): void
    {
        // Create executing action (in-flight protection)
        $executing = $this->createActionWithKey('deploy:prod', ScheduledAction::STATUS_EXECUTING);

        // Create replacement
        $response = $this->postJson('/api/v1/actions', [
            'name' => 'Replacement',
            'intent' => ['delay' => '1h'],
            'coordination_keys' => ['deploy:prod'],
            'coordination' => ['on_create' => 'replace_existing'],
            'request' => ['url' => 'https://example.com/webhook'],
        ]);

        $response->assertStatus(201);

        // Executing action should not be cancelled
        $executing->refresh();
        $this->assertEquals(ScheduledAction::STATUS_EXECUTING, $executing->resolution_status);
    }

    public function test_replace_existing_only_affects_matching_keys(): void
    {
        // Create actions with different keys
        $sameKey = $this->createActionWithKey('deploy:prod', ScheduledAction::STATUS_RESOLVED);
        $differentKey = $this->createActionWithKey('deploy:staging', ScheduledAction::STATUS_RESOLVED);

        // Create replacement for deploy:prod only
        $response = $this->postJson('/api/v1/actions', [
            'name' => 'Replacement',
            'intent' => ['delay' => '1h'],
            'coordination_keys' => ['deploy:prod'],
            'coordination' => ['on_create' => 'replace_existing'],
            'request' => ['url' => 'https://example.com/webhook'],
        ]);

        $response->assertStatus(201);

        $sameKey->refresh();
        $differentKey->refresh();

        // Only matching key should be cancelled
        $this->assertEquals(ScheduledAction::STATUS_CANCELLED, $sameKey->resolution_status);
        $this->assertEquals(ScheduledAction::STATUS_RESOLVED, $differentKey->resolution_status);
    }

    public function test_replace_existing_only_affects_same_account(): void
    {
        // Create action for different account
        $otherUser = User::factory()->create();
        $otherAction = ScheduledAction::create([
            'account_id' => $otherUser->account_id,
            'created_by_user_id' => $otherUser->id,
            'name' => 'Other Account',
            'mode' => ScheduledAction::MODE_IMMEDIATE,
            'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
            'intent_payload' => [],
            'resolution_status' => ScheduledAction::STATUS_RESOLVED,
            'execute_at_utc' => now()->addHour(),
            'request' => ['url' => 'https://example.com', 'method' => 'POST'],
        ]);
        ActionCoordinationKey::create([
            'action_id' => $otherAction->id,
            'coordination_key' => 'deploy:prod',
        ]);

        // Create replacement for same key
        $response = $this->postJson('/api/v1/actions', [
            'name' => 'Replacement',
            'intent' => ['delay' => '1h'],
            'coordination_keys' => ['deploy:prod'],
            'coordination' => ['on_create' => 'replace_existing'],
            'request' => ['url' => 'https://example.com/webhook'],
        ]);

        $response->assertStatus(201);

        // Other account's action should not be affected
        $otherAction->refresh();
        $this->assertEquals(ScheduledAction::STATUS_RESOLVED, $otherAction->resolution_status);
    }

    // ==================== ON_CREATE: CANCEL_EXISTING ====================

    public function test_cancel_existing_cancels_previous_without_linking(): void
    {
        // Create first action
        $first = $this->createActionWithKey('deploy:prod', ScheduledAction::STATUS_RESOLVED);

        // Create second action with cancel_existing
        $response = $this->postJson('/api/v1/actions', [
            'name' => 'New Action',
            'intent' => ['delay' => '1h'],
            'coordination_keys' => ['deploy:prod'],
            'coordination' => ['on_create' => 'cancel_existing'],
            'request' => ['url' => 'https://example.com/webhook'],
        ]);

        $response->assertStatus(201);

        // First action should be cancelled but not linked
        $first->refresh();
        $this->assertEquals(ScheduledAction::STATUS_CANCELLED, $first->resolution_status);
        // cancel_existing does not create replaced_by link (that's the difference from replace_existing)
    }

    // ==================== ON_CREATE: SKIP_IF_EXISTS ====================

    public function test_skip_if_exists_returns_existing_action(): void
    {
        // Create first action
        $first = $this->createActionWithKey('deploy:prod', ScheduledAction::STATUS_RESOLVED);

        // Try to create second with skip_if_exists
        $response = $this->postJson('/api/v1/actions', [
            'name' => 'Should Be Skipped',
            'intent' => ['delay' => '1h'],
            'coordination_keys' => ['deploy:prod'],
            'coordination' => ['on_create' => 'skip_if_exists'],
            'request' => ['url' => 'https://example.com/webhook'],
        ]);

        // Returns 200 OK (found existing) instead of 201 Created
        $response->assertStatus(200);

        // Should return existing action
        $this->assertEquals($first->id, $response->json('data.id'));

        // Meta should indicate skipped
        $this->assertTrue($response->json('meta.skipped'));
        $this->assertEquals('existing_action_found', $response->json('meta.reason'));

        // No new action should be created
        $this->assertEquals(1, ScheduledAction::where('account_id', $this->user->account_id)->count());
    }

    public function test_skip_if_exists_creates_when_no_existing(): void
    {
        // No existing action with this key
        $response = $this->postJson('/api/v1/actions', [
            'name' => 'New Action',
            'intent' => ['delay' => '1h'],
            'coordination_keys' => ['deploy:prod'],
            'coordination' => ['on_create' => 'skip_if_exists'],
            'request' => ['url' => 'https://example.com/webhook'],
        ]);

        $response->assertStatus(201);
        // When action is created (not skipped), meta.skipped should not be true
        $this->assertNotTrue($response->json('meta.skipped'));
        $this->assertEquals('New Action', $response->json('data.name'));
    }

    public function test_skip_if_exists_ignores_terminal_actions(): void
    {
        // Create executed action (terminal)
        $executed = $this->createActionWithKey('deploy:prod', ScheduledAction::STATUS_EXECUTED);

        // Try to create with skip_if_exists
        $response = $this->postJson('/api/v1/actions', [
            'name' => 'New Action',
            'intent' => ['delay' => '1h'],
            'coordination_keys' => ['deploy:prod'],
            'coordination' => ['on_create' => 'skip_if_exists'],
            'request' => ['url' => 'https://example.com/webhook'],
        ]);

        $response->assertStatus(201);

        // Should create new action (terminal actions don't count)
        $this->assertNotEquals($executed->id, $response->json('data.id'));
        // When action is created (not skipped), meta.skipped should not be true
        $this->assertNotTrue($response->json('meta.skipped'));
    }

    // ==================== ON_CREATE VALIDATION ====================

    public function test_on_create_requires_coordination_keys(): void
    {
        $response = $this->postJson('/api/v1/actions', [
            'name' => 'Missing Keys',
            'intent' => ['delay' => '1h'],
            'coordination' => ['on_create' => 'replace_existing'],
            'request' => ['url' => 'https://example.com/webhook'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['coordination_keys']);
    }

    public function test_validates_on_create_values(): void
    {
        $response = $this->postJson('/api/v1/actions', [
            'name' => 'Invalid Value',
            'intent' => ['delay' => '1h'],
            'coordination_keys' => ['key1'],
            'coordination' => ['on_create' => 'invalid_behavior'],
            'request' => ['url' => 'https://example.com/webhook'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['coordination.on_create']);
    }

    // ==================== API: COORDINATION KEYS ENDPOINT ====================

    public function test_list_coordination_keys_endpoint(): void
    {
        // Create actions with various keys
        $this->createActionWithKey('deploy:prod', ScheduledAction::STATUS_EXECUTED);
        $this->createActionWithKey('deploy:staging', ScheduledAction::STATUS_RESOLVED);
        $this->createActionWithKey('service:api', ScheduledAction::STATUS_PENDING_RESOLUTION);

        $response = $this->getJson('/api/v1/coordination-keys');

        $response->assertStatus(200)
            ->assertJsonStructure(['keys'])
            ->assertJsonCount(3, 'keys');

        $keys = $response->json('keys');
        $this->assertContains('deploy:prod', $keys);
        $this->assertContains('deploy:staging', $keys);
        $this->assertContains('service:api', $keys);
    }

    public function test_list_coordination_keys_only_returns_own_account(): void
    {
        // Create action for current user
        $this->createActionWithKey('my-key', ScheduledAction::STATUS_RESOLVED);

        // Create action for different account
        $otherUser = User::factory()->create();
        $otherAction = ScheduledAction::create([
            'account_id' => $otherUser->account_id,
            'created_by_user_id' => $otherUser->id,
            'name' => 'Other',
            'mode' => ScheduledAction::MODE_IMMEDIATE,
            'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
            'intent_payload' => [],
            'resolution_status' => ScheduledAction::STATUS_RESOLVED,
            'request' => ['url' => 'https://example.com', 'method' => 'POST'],
        ]);
        ActionCoordinationKey::create([
            'action_id' => $otherAction->id,
            'coordination_key' => 'other-key',
        ]);

        $response = $this->getJson('/api/v1/coordination-keys');

        $response->assertStatus(200);
        $keys = $response->json('keys');
        $this->assertContains('my-key', $keys);
        $this->assertNotContains('other-key', $keys);
    }

    public function test_list_coordination_keys_returns_unique(): void
    {
        // Create multiple actions with same key
        $this->createActionWithKey('same-key', ScheduledAction::STATUS_EXECUTED);
        $this->createActionWithKey('same-key', ScheduledAction::STATUS_RESOLVED);

        $response = $this->getJson('/api/v1/coordination-keys');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'keys');
    }

    // ==================== API: FILTER BY COORDINATION KEY ====================

    public function test_filter_actions_by_coordination_key(): void
    {
        // Create actions with different keys
        $action1 = $this->createActionWithKey('deploy:prod', ScheduledAction::STATUS_RESOLVED);
        $action2 = $this->createActionWithKey('deploy:prod', ScheduledAction::STATUS_EXECUTED);
        $action3 = $this->createActionWithKey('deploy:staging', ScheduledAction::STATUS_RESOLVED);

        $response = $this->getJson('/api/v1/actions?coordination_key=deploy:prod');

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($action1->id, $ids);
        $this->assertContains($action2->id, $ids);
        $this->assertNotContains($action3->id, $ids);
    }

    // ==================== API: ACTION RESOURCE SHOWS COORDINATION KEYS ====================

    public function test_action_resource_includes_coordination_keys(): void
    {
        $action = $this->createActionWithKey('deploy:prod', ScheduledAction::STATUS_RESOLVED);
        ActionCoordinationKey::create([
            'action_id' => $action->id,
            'coordination_key' => 'service:api',
        ]);
        $action->refresh();

        $response = $this->getJson("/api/v1/actions/{$action->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['coordination_keys']]);

        $keys = $response->json('data.coordination_keys');
        $this->assertContains('deploy:prod', $keys);
        $this->assertContains('service:api', $keys);
    }

    // ==================== REPLACED_BY RELATIONSHIP ====================

    public function test_replaced_by_relationship_exists(): void
    {
        // Create and replace an action
        $original = $this->createActionWithKey('test', ScheduledAction::STATUS_RESOLVED);

        $response = $this->postJson('/api/v1/actions', [
            'name' => 'Replacement',
            'intent' => ['delay' => '1h'],
            'coordination_keys' => ['test'],
            'coordination' => ['on_create' => 'replace_existing'],
            'request' => ['url' => 'https://example.com/webhook'],
        ]);

        $response->assertStatus(201);

        $replacement = ScheduledAction::find($response->json('data.id'));
        $original->refresh();

        // Check replaced_by relationship
        $this->assertNotNull($original->replacedBy);
        $this->assertEquals($replacement->id, $original->replacedBy->id);

        // Check replacements relationship (inverse)
        $this->assertCount(1, $replacement->replacements);
        $this->assertEquals($original->id, $replacement->replacements->first()->id);
    }

    // ==================== HELPERS ====================

    private function createActionWithKey(string $key, string $status): ScheduledAction
    {
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
        ]);

        ActionCoordinationKey::create([
            'action_id' => $action->id,
            'coordination_key' => $key,
        ]);

        return $action;
    }
}

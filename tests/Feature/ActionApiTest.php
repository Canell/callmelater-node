<?php

namespace Tests\Feature;

use App\Models\ScheduledAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActionApiTest extends TestCase
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

    // ==================== CREATE IMMEDIATE ACTION ====================

    public function test_can_create_immediate_action(): void
    {
        $response = $this->postJson('/api/v1/actions', [
            'name' => 'Test HTTP Action',
            'mode' => 'immediate',
            'execute_at' => now()->addHour()->toIso8601String(),
            'request' => [
                'url' => 'https://example.com/webhook',
                'method' => 'POST',
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'mode',
                    'status',
                    'execute_at',
                ],
            ]);

        $this->assertDatabaseHas('scheduled_actions', [
            'name' => 'Test HTTP Action',
            'mode' => 'immediate',
            'account_id' => $this->user->account_id,
        ]);
    }

    public function test_can_create_action_without_name(): void
    {
        $response = $this->postJson('/api/v1/actions', [
            'mode' => 'immediate',
            'execute_at' => now()->addHour()->toIso8601String(),
            'request' => [
                'url' => 'https://example.com/webhook',
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'HTTP Action');
    }

    public function test_can_create_action_without_mode_defaults_to_immediate(): void
    {
        $response = $this->postJson('/api/v1/actions', [
            'execute_at' => now()->addHour()->toIso8601String(),
            'request' => [
                'url' => 'https://example.com/webhook',
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.mode', 'immediate');
    }

    public function test_minimal_immediate_action_payload(): void
    {
        // This is the minimal valid payload as documented
        $response = $this->postJson('/api/v1/actions', [
            'intent' => ['delay' => '1m'],
            'request' => [
                'url' => 'https://example.com/webhook',
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.mode', 'immediate')
            ->assertJsonPath('data.name', 'HTTP Action');
    }

    public function test_action_uses_user_webhook_secret_by_default(): void
    {
        // Ensure user has a webhook secret
        $this->user->update(['webhook_secret' => 'whsec_test_user_secret']);

        $response = $this->postJson('/api/v1/actions', [
            'intent' => ['delay' => '1h'],
            'request' => [
                'url' => 'https://example.com/webhook',
            ],
        ]);

        $response->assertStatus(201);

        $action = ScheduledAction::find($response->json('data.id'));
        $this->assertEquals('whsec_test_user_secret', $action->webhook_secret);
    }

    public function test_action_can_override_webhook_secret(): void
    {
        $this->user->update(['webhook_secret' => 'whsec_user_default']);

        $response = $this->postJson('/api/v1/actions', [
            'intent' => ['delay' => '1h'],
            'request' => [
                'url' => 'https://example.com/webhook',
            ],
            'webhook_secret' => 'custom_action_secret',
        ]);

        $response->assertStatus(201);

        $action = ScheduledAction::find($response->json('data.id'));
        $this->assertEquals('custom_action_secret', $action->webhook_secret);
    }

    public function test_can_create_immediate_action_with_intent(): void
    {
        $response = $this->postJson('/api/v1/actions', [
            'name' => 'Delayed Action',
            'mode' => 'immediate',
            'intent' => ['delay' => '1h'],
            'request' => [
                'url' => 'https://example.com/webhook',
                'method' => 'POST',
            ],
        ]);

        $response->assertStatus(201);

        // The action should be created with the intent stored
        $action = ScheduledAction::find($response->json('data.id'));
        $this->assertNotNull($action);
        $this->assertEquals('1h', $action->intent_payload['delay'] ?? null);
    }

    public function test_create_immediate_action_requires_url(): void
    {
        $response = $this->postJson('/api/v1/actions', [
            'name' => 'Test Action',
            'mode' => 'immediate',
            'execute_at' => now()->addHour()->toIso8601String(),
            'request' => [
                'method' => 'POST',
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['request.url']);
    }

    public function test_create_action_requires_execute_at_or_intent(): void
    {
        $response = $this->postJson('/api/v1/actions', [
            'name' => 'Test Action',
            'mode' => 'immediate',
            'request' => [
                'url' => 'https://example.com/webhook',
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['execute_at']);
    }

    public function test_create_action_validates_url_format(): void
    {
        $response = $this->postJson('/api/v1/actions', [
            'name' => 'Test Action',
            'mode' => 'immediate',
            'execute_at' => now()->addHour()->toIso8601String(),
            'request' => [
                'url' => 'not-a-valid-url',
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['request.url']);
    }

    public function test_create_action_validates_http_method(): void
    {
        $response = $this->postJson('/api/v1/actions', [
            'name' => 'Test Action',
            'mode' => 'immediate',
            'execute_at' => now()->addHour()->toIso8601String(),
            'request' => [
                'url' => 'https://example.com/webhook',
                'method' => 'INVALID',
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['request.method']);
    }

    public function test_idempotency_key_prevents_duplicate_creation(): void
    {
        $data = [
            'name' => 'Test Action',
            'mode' => 'immediate',
            'execute_at' => now()->addHour()->toIso8601String(),
            'idempotency_key' => 'unique-key-123',
            'request' => [
                'url' => 'https://example.com/webhook',
            ],
        ];

        // First request should succeed
        $response = $this->postJson('/api/v1/actions', $data);
        $response->assertStatus(201);

        // Second request with same key should fail
        $response = $this->postJson('/api/v1/actions', $data);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['idempotency_key']);
    }

    // ==================== CREATE GATED ACTION ====================

    public function test_can_create_gated_action(): void
    {
        $response = $this->postJson('/api/v1/actions', [
            'name' => 'Test Gated Action',
            'mode' => 'gated',
            'execute_at' => now()->addHour()->toIso8601String(),
            'gate' => [
                'message' => 'Please confirm this action',
                'recipients' => ['test@example.com'],
                'channels' => ['email'],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.mode', 'gated');

        $this->assertDatabaseHas('scheduled_actions', [
            'name' => 'Test Gated Action',
            'mode' => 'gated',
        ]);
    }

    public function test_create_gated_action_requires_gate(): void
    {
        $response = $this->postJson('/api/v1/actions', [
            'name' => 'Test Gated',
            'mode' => 'gated',
            'execute_at' => now()->addHour()->toIso8601String(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['gate']);
    }

    public function test_create_gated_action_requires_recipients(): void
    {
        $response = $this->postJson('/api/v1/actions', [
            'name' => 'Test Gated',
            'mode' => 'gated',
            'execute_at' => now()->addHour()->toIso8601String(),
            'gate' => [
                'message' => 'Please confirm',
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['gate.recipients']);
    }

    public function test_create_gated_action_validates_recipient_format(): void
    {
        $response = $this->postJson('/api/v1/actions', [
            'name' => 'Test Gated',
            'mode' => 'gated',
            'execute_at' => now()->addHour()->toIso8601String(),
            'gate' => [
                'message' => 'Please confirm',
                'recipients' => ['not-an-email'],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['gate.recipients.0']);
    }

    public function test_create_gated_action_accepts_phone_number_recipient(): void
    {
        $response = $this->postJson('/api/v1/actions', [
            'name' => 'Test Gated',
            'mode' => 'gated',
            'execute_at' => now()->addHour()->toIso8601String(),
            'gate' => [
                'message' => 'Please confirm',
                'recipients' => ['+15551234567'],
                'channels' => ['sms'],
            ],
        ]);

        // Will fail validation because free plan doesn't allow SMS
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['gate.channels']);
    }

    public function test_can_create_gated_action_with_request(): void
    {
        $response = $this->postJson('/api/v1/actions', [
            'name' => 'Deploy to Production',
            'mode' => 'gated',
            'execute_at' => now()->addHour()->toIso8601String(),
            'gate' => [
                'message' => 'Ready to deploy?',
                'recipients' => ['ops@example.com'],
                'channels' => ['email'],
            ],
            'request' => [
                'url' => 'https://api.example.com/deploy',
                'method' => 'POST',
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.mode', 'gated');

        $action = ScheduledAction::find($response->json('data.id'));
        $this->assertNotNull($action->request);
        $this->assertEquals('https://api.example.com/deploy', $action->request['url']);
    }

    // ==================== LIST ACTIONS ====================

    public function test_can_list_actions(): void
    {
        // Create some actions
        $this->createImmediateAction('Action 1');
        $this->createImmediateAction('Action 2');
        $this->createImmediateAction('Action 3');

        $response = $this->getJson('/api/v1/actions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'mode', 'status'],
                ],
                'meta' => ['current_page', 'total'],
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_list_actions_filters_by_status(): void
    {
        $this->createImmediateAction('Pending', ScheduledAction::STATUS_PENDING_RESOLUTION);
        $this->createImmediateAction('Executed', ScheduledAction::STATUS_EXECUTED);
        $this->createImmediateAction('Failed', ScheduledAction::STATUS_FAILED);

        $response = $this->getJson('/api/v1/actions?status='.ScheduledAction::STATUS_EXECUTED);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Executed');
    }

    public function test_list_actions_filters_by_mode(): void
    {
        $this->createImmediateAction('Immediate Action', ScheduledAction::STATUS_PENDING_RESOLUTION);
        $this->createGatedAction('Gated Action');

        $response = $this->getJson('/api/v1/actions?mode=gated');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.mode', 'gated');
    }

    public function test_user_only_sees_own_account_actions(): void
    {
        // Create action for current user
        $this->createImmediateAction('My Action');

        // Create another user and their action
        $otherUser = User::factory()->create();
        ScheduledAction::create([
            'account_id' => $otherUser->account_id,
            'created_by_user_id' => $otherUser->id,
            'name' => 'Other User Action',
            'mode' => ScheduledAction::MODE_IMMEDIATE,
            'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
            'intent_payload' => ['execute_at' => now()->addHour()->toIso8601String()],
            'resolution_status' => ScheduledAction::STATUS_PENDING_RESOLUTION,
            'request' => ['url' => 'https://example.com', 'method' => 'POST'],
        ]);

        $response = $this->getJson('/api/v1/actions');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'My Action');
    }

    // ==================== SHOW ACTION ====================

    public function test_can_show_action(): void
    {
        $action = $this->createImmediateAction('Test Action');

        $response = $this->getJson("/api/v1/actions/{$action->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $action->id)
            ->assertJsonPath('data.name', 'Test Action');
    }

    public function test_show_returns_404_for_nonexistent_action(): void
    {
        $response = $this->getJson('/api/v1/actions/00000000-0000-0000-0000-000000000000');

        $response->assertStatus(404);
    }

    public function test_cannot_show_other_users_action(): void
    {
        $otherUser = User::factory()->create();
        $action = ScheduledAction::create([
            'account_id' => $otherUser->account_id,
            'created_by_user_id' => $otherUser->id,
            'name' => 'Other User Action',
            'mode' => ScheduledAction::MODE_IMMEDIATE,
            'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
            'intent_payload' => ['execute_at' => now()->addHour()->toIso8601String()],
            'resolution_status' => ScheduledAction::STATUS_PENDING_RESOLUTION,
            'request' => ['url' => 'https://example.com', 'method' => 'POST'],
        ]);

        $response = $this->getJson("/api/v1/actions/{$action->id}");

        $response->assertStatus(404);
    }

    // ==================== CANCEL ACTION ====================

    public function test_can_cancel_pending_action(): void
    {
        $action = $this->createImmediateAction('Test Action', ScheduledAction::STATUS_RESOLVED);

        $response = $this->deleteJson("/api/v1/actions/{$action->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Action cancelled']);

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_CANCELLED, $action->resolution_status);
    }

    public function test_cannot_cancel_executed_action(): void
    {
        $action = $this->createImmediateAction('Test Action', ScheduledAction::STATUS_EXECUTED);

        $response = $this->deleteJson("/api/v1/actions/{$action->id}");

        $response->assertStatus(422);

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_EXECUTED, $action->resolution_status);
    }

    public function test_cancel_by_idempotency_key(): void
    {
        $action = $this->createImmediateAction('Test Action', ScheduledAction::STATUS_RESOLVED);
        $action->update(['idempotency_key' => 'my-unique-key']);

        $response = $this->deleteJson('/api/v1/actions', [
            'idempotency_key' => 'my-unique-key',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Action cancelled']);

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_CANCELLED, $action->resolution_status);
    }

    public function test_cancel_by_idempotency_key_returns_404_if_not_found(): void
    {
        $response = $this->deleteJson('/api/v1/actions', [
            'idempotency_key' => 'nonexistent-key',
        ]);

        $response->assertStatus(404);
    }

    public function test_cancel_already_cancelled_is_idempotent(): void
    {
        $action = $this->createImmediateAction('Test Action', ScheduledAction::STATUS_CANCELLED);
        $action->update(['idempotency_key' => 'my-key']);

        $response = $this->deleteJson('/api/v1/actions', [
            'idempotency_key' => 'my-key',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Action already cancelled']);
    }

    public function test_cancel_executed_action_returns_conflict(): void
    {
        $action = $this->createImmediateAction('Test Action', ScheduledAction::STATUS_EXECUTED);
        $action->update(['idempotency_key' => 'my-key']);

        $response = $this->deleteJson('/api/v1/actions', [
            'idempotency_key' => 'my-key',
        ]);

        $response->assertStatus(409)
            ->assertJson(['message' => 'Action already executed']);
    }

    // ==================== AUTHENTICATION ====================

    public function test_unauthenticated_user_cannot_access_actions(): void
    {
        // Clear the authenticated user
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/v1/actions');

        $response->assertStatus(401);
    }

    // ==================== HELPERS ====================

    private function createImmediateAction(
        string $name,
        string $status = ScheduledAction::STATUS_PENDING_RESOLUTION
    ): ScheduledAction {
        return ScheduledAction::create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'name' => $name,
            'mode' => ScheduledAction::MODE_IMMEDIATE,
            'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
            'intent_payload' => ['execute_at' => now()->addHour()->toIso8601String()],
            'resolution_status' => $status,
            'execute_at_utc' => now()->addHour(),
            'request' => ['url' => 'https://example.com', 'method' => 'POST'],
        ]);
    }

    private function createGatedAction(string $name): ScheduledAction
    {
        return ScheduledAction::create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'name' => $name,
            'mode' => ScheduledAction::MODE_GATED,
            'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
            'intent_payload' => ['execute_at' => now()->addHour()->toIso8601String()],
            'resolution_status' => ScheduledAction::STATUS_PENDING_RESOLUTION,
            'execute_at_utc' => now()->addHour(),
            'gate' => [
                'message' => 'Test gate message',
                'recipients' => ['test@example.com'],
                'channels' => ['email'],
            ],
        ]);
    }
}

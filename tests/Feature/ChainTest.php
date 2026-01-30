<?php

namespace Tests\Feature;

use App\Models\ActionChain;
use App\Models\ScheduledAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChainTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
        Queue::fake();
    }

    // ==================== CRUD: LIST ====================

    public function test_can_list_chains(): void
    {
        Sanctum::actingAs($this->user);

        ActionChain::factory()->count(3)->create([
            'account_id' => $this->user->account_id,
        ]);

        $response = $this->getJson('/api/v1/chains');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_list_only_shows_own_account_chains(): void
    {
        Sanctum::actingAs($this->user);

        ActionChain::factory()->count(2)->create([
            'account_id' => $this->user->account_id,
        ]);
        ActionChain::factory()->count(3)->create([
            'account_id' => $this->otherUser->account_id,
        ]);

        $response = $this->getJson('/api/v1/chains');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_list_can_filter_by_status(): void
    {
        Sanctum::actingAs($this->user);

        ActionChain::factory()->create([
            'account_id' => $this->user->account_id,
            'status' => ActionChain::STATUS_PENDING,
        ]);
        ActionChain::factory()->create([
            'account_id' => $this->user->account_id,
            'status' => ActionChain::STATUS_RUNNING,
        ]);
        ActionChain::factory()->create([
            'account_id' => $this->user->account_id,
            'status' => ActionChain::STATUS_COMPLETED,
        ]);

        $response = $this->getJson('/api/v1/chains?status=running');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'running');
    }

    // ==================== CRUD: CREATE ====================

    public function test_can_create_chain(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/chains', [
            'name' => 'User Onboarding',
            'steps' => [
                [
                    'name' => 'Create User',
                    'type' => 'http_call',
                    'url' => 'https://api.example.com/users',
                    'method' => 'POST',
                ],
                [
                    'name' => 'Wait',
                    'type' => 'delay',
                    'delay' => '5m',
                ],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'User Onboarding')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'status',
                    'current_step',
                    'steps',
                    'created_at',
                ],
            ]);

        $this->assertDatabaseHas('action_chains', [
            'name' => 'User Onboarding',
            'account_id' => $this->user->account_id,
        ]);
    }

    public function test_create_requires_at_least_2_steps(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/chains', [
            'name' => 'Invalid Chain',
            'steps' => [
                ['name' => 'Only Step', 'type' => 'http_call', 'url' => 'https://example.com'],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_create_validates_step_types(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/chains', [
            'name' => 'Invalid Step Type',
            'steps' => [
                ['name' => 'Step 1', 'type' => 'invalid_type'],
                ['name' => 'Step 2', 'type' => 'delay', 'delay' => '5m'],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_create_chain_creates_first_action(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/chains', [
            'name' => 'Test Chain',
            'steps' => [
                [
                    'name' => 'First Step',
                    'type' => 'http_call',
                    'url' => 'https://api.example.com/step1',
                    'method' => 'POST',
                ],
                [
                    'name' => 'Second Step',
                    'type' => 'delay',
                    'delay' => '5m',
                ],
            ],
        ]);

        $response->assertStatus(201);

        $chainId = $response->json('data.id');

        // First step action should be created
        $this->assertDatabaseHas('scheduled_actions', [
            'chain_id' => $chainId,
            'chain_step' => 0,
        ]);
    }

    public function test_create_chain_with_input_variables(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/chains', [
            'name' => 'Chain with Input',
            'steps' => [
                [
                    'name' => 'Process {{input.item_type}}',
                    'type' => 'http_call',
                    'url' => 'https://api.example.com/{{input.item_type}}',
                ],
                [
                    'name' => 'Wait',
                    'type' => 'delay',
                    'delay' => '5m',
                ],
            ],
            'input' => [
                'item_type' => 'orders',
                'item_id' => '12345',
            ],
        ]);

        $response->assertStatus(201);

        $chain = ActionChain::find($response->json('data.id'));
        $this->assertEquals(['item_type' => 'orders', 'item_id' => '12345'], $chain->input);
    }

    // ==================== CRUD: VIEW ====================

    public function test_can_view_chain(): void
    {
        Sanctum::actingAs($this->user);

        $chain = ActionChain::factory()->create([
            'account_id' => $this->user->account_id,
        ]);

        $response = $this->getJson("/api/v1/chains/{$chain->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $chain->id)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'status',
                    'current_step',
                    'steps',
                    'context',
                    'created_at',
                ],
            ]);
    }

    public function test_cannot_view_other_account_chain(): void
    {
        Sanctum::actingAs($this->user);

        $chain = ActionChain::factory()->create([
            'account_id' => $this->otherUser->account_id,
        ]);

        $response = $this->getJson("/api/v1/chains/{$chain->id}");

        $response->assertStatus(404);
    }

    public function test_view_includes_step_status(): void
    {
        Sanctum::actingAs($this->user);

        $chain = ActionChain::factory()->create([
            'account_id' => $this->user->account_id,
            'current_step' => 1,
            'context' => [
                'steps' => [
                    0 => [
                        'status' => 'executed',
                        'response' => ['id' => '123'],
                    ],
                ],
            ],
        ]);

        $response = $this->getJson("/api/v1/chains/{$chain->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.current_step', 1);
    }

    // ==================== CRUD: CANCEL ====================

    public function test_can_cancel_chain(): void
    {
        Sanctum::actingAs($this->user);

        $chain = ActionChain::factory()->create([
            'account_id' => $this->user->account_id,
            'status' => ActionChain::STATUS_RUNNING,
        ]);

        $response = $this->deleteJson("/api/v1/chains/{$chain->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        $chain->refresh();
        $this->assertEquals(ActionChain::STATUS_CANCELLED, $chain->status);
    }

    public function test_cannot_cancel_completed_chain(): void
    {
        Sanctum::actingAs($this->user);

        $chain = ActionChain::factory()->create([
            'account_id' => $this->user->account_id,
            'status' => ActionChain::STATUS_COMPLETED,
        ]);

        $response = $this->deleteJson("/api/v1/chains/{$chain->id}");

        $response->assertStatus(400);
    }

    public function test_cannot_cancel_other_account_chain(): void
    {
        Sanctum::actingAs($this->user);

        $chain = ActionChain::factory()->create([
            'account_id' => $this->otherUser->account_id,
            'status' => ActionChain::STATUS_RUNNING,
        ]);

        $response = $this->deleteJson("/api/v1/chains/{$chain->id}");

        $response->assertStatus(404);
    }

    public function test_cancel_chain_cancels_pending_actions(): void
    {
        Sanctum::actingAs($this->user);

        $chain = ActionChain::factory()->create([
            'account_id' => $this->user->account_id,
            'status' => ActionChain::STATUS_RUNNING,
        ]);

        // Create a pending action for this chain
        $action = ScheduledAction::factory()->create([
            'account_id' => $this->user->account_id,
            'chain_id' => $chain->id,
            'chain_step' => 1,
            'resolution_status' => ScheduledAction::STATUS_RESOLVED,
        ]);

        $this->deleteJson("/api/v1/chains/{$chain->id}");

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_CANCELLED, $action->resolution_status);
    }

    // ==================== STEP TYPES ====================

    public function test_chain_with_http_call_step(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/chains', [
            'name' => 'HTTP Chain',
            'steps' => [
                [
                    'name' => 'API Call',
                    'type' => 'http_call',
                    'url' => 'https://api.example.com/webhook',
                    'method' => 'POST',
                    'headers' => ['X-Custom' => 'value'],
                    'body' => ['key' => 'value'],
                ],
                [
                    'name' => 'Second Call',
                    'type' => 'http_call',
                    'url' => 'https://api.example.com/webhook2',
                ],
            ],
        ]);

        $response->assertStatus(201);

        $chain = ActionChain::find($response->json('data.id'));
        $this->assertEquals('POST', $chain->steps[0]['method']);
        $this->assertEquals(['X-Custom' => 'value'], $chain->steps[0]['headers']);
    }

    public function test_chain_with_gated_step(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/chains', [
            'name' => 'Approval Chain',
            'steps' => [
                [
                    'name' => 'Initial Request',
                    'type' => 'http_call',
                    'url' => 'https://api.example.com/request',
                ],
                [
                    'name' => 'Manager Approval',
                    'type' => 'gated',
                    'gate' => [
                        'message' => 'Please approve this request',
                        'recipients' => ['manager@example.com'],
                        'channels' => ['email'],
                    ],
                ],
            ],
        ]);

        $response->assertStatus(201);

        $chain = ActionChain::find($response->json('data.id'));
        $this->assertEquals('gated', $chain->steps[1]['type']);
        $this->assertEquals('Please approve this request', $chain->steps[1]['gate']['message']);
    }

    public function test_chain_with_delay_step(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/chains', [
            'name' => 'Delayed Chain',
            'steps' => [
                [
                    'name' => 'Initial Action',
                    'type' => 'http_call',
                    'url' => 'https://api.example.com/start',
                ],
                [
                    'name' => 'Wait 1 hour',
                    'type' => 'delay',
                    'delay' => '1h',
                ],
                [
                    'name' => 'Follow-up',
                    'type' => 'http_call',
                    'url' => 'https://api.example.com/followup',
                ],
            ],
        ]);

        $response->assertStatus(201);

        $chain = ActionChain::find($response->json('data.id'));
        $this->assertEquals('delay', $chain->steps[1]['type']);
        $this->assertEquals('1h', $chain->steps[1]['delay']);
    }

    // ==================== ERROR HANDLING ====================

    public function test_chain_with_fail_chain_error_handling(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/chains', [
            'name' => 'Strict Chain',
            'error_handling' => 'fail_chain',
            'steps' => [
                ['name' => 'Step 1', 'type' => 'http_call', 'url' => 'https://api.example.com/1'],
                ['name' => 'Step 2', 'type' => 'http_call', 'url' => 'https://api.example.com/2'],
            ],
        ]);

        $response->assertStatus(201);

        $chain = ActionChain::find($response->json('data.id'));
        $this->assertEquals('fail_chain', $chain->error_handling);
    }

    public function test_chain_with_skip_step_error_handling(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/chains', [
            'name' => 'Lenient Chain',
            'error_handling' => 'skip_step',
            'steps' => [
                ['name' => 'Step 1', 'type' => 'http_call', 'url' => 'https://api.example.com/1'],
                ['name' => 'Step 2', 'type' => 'http_call', 'url' => 'https://api.example.com/2'],
            ],
        ]);

        $response->assertStatus(201);

        $chain = ActionChain::find($response->json('data.id'));
        $this->assertEquals('skip_step', $chain->error_handling);
    }

    // ==================== CONDITIONS ====================

    public function test_chain_step_with_condition(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/chains', [
            'name' => 'Conditional Chain',
            'steps' => [
                [
                    'name' => 'Step 1',
                    'type' => 'http_call',
                    'url' => 'https://api.example.com/check',
                ],
                [
                    'name' => 'Conditional Step',
                    'type' => 'http_call',
                    'url' => 'https://api.example.com/proceed',
                    'condition' => "{{steps.0.response.approved}} == true",
                ],
            ],
        ]);

        $response->assertStatus(201);

        $chain = ActionChain::find($response->json('data.id'));
        $this->assertEquals("{{steps.0.response.approved}} == true", $chain->steps[1]['condition']);
    }
}

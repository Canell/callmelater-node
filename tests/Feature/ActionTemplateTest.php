<?php

namespace Tests\Feature;

use App\Models\ActionTemplate;
use App\Models\ScheduledAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActionTemplateTest extends TestCase
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

    public function test_can_list_templates(): void
    {
        Sanctum::actingAs($this->user);

        ActionTemplate::factory()->count(3)->create([
            'account_id' => $this->user->account_id,
        ]);

        $response = $this->getJson('/api/v1/templates');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_list_only_shows_own_account_templates(): void
    {
        Sanctum::actingAs($this->user);

        // Create templates for both users
        ActionTemplate::factory()->count(2)->create([
            'account_id' => $this->user->account_id,
        ]);
        ActionTemplate::factory()->count(3)->create([
            'account_id' => $this->otherUser->account_id,
        ]);

        $response = $this->getJson('/api/v1/templates');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    // ==================== CRUD: CREATE ====================

    public function test_can_create_immediate_template(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/templates', [
            'name' => 'Deploy Service',
            'mode' => 'immediate',
            'request_config' => [
                'url' => 'https://api.example.com/deploy',
                'method' => 'POST',
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Deploy Service')
            ->assertJsonPath('data.mode', 'immediate')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'mode',
                    'trigger_url',
                    'trigger_token',
                    'created_at',
                ],
            ]);

        $this->assertDatabaseHas('action_templates', [
            'name' => 'Deploy Service',
            'mode' => 'immediate',
            'account_id' => $this->user->account_id,
        ]);
    }

    public function test_can_create_gated_template(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/templates', [
            'name' => 'Approve Deployment',
            'mode' => 'gated',
            'gate_config' => [
                'message' => 'Approve deployment?',
                'recipients' => ['ops@example.com'],
                'timeout' => '4h',
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.mode', 'gated');
    }

    public function test_create_requires_request_config_for_immediate(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/templates', [
            'name' => 'Missing Request',
            'mode' => 'immediate',
        ]);

        $response->assertStatus(422);
    }

    public function test_create_requires_gate_config_for_gated(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/templates', [
            'name' => 'Missing Gate',
            'mode' => 'gated',
        ]);

        $response->assertStatus(422);
    }

    public function test_create_validates_placeholder_names(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/templates', [
            'name' => 'Invalid Placeholder',
            'mode' => 'immediate',
            'request_config' => [
                'url' => 'https://api.example.com/deploy',
            ],
            'placeholders' => [
                ['name' => '123invalid', 'required' => true],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_create_generates_unique_trigger_token(): void
    {
        Sanctum::actingAs($this->user);

        $response1 = $this->postJson('/api/v1/templates', [
            'name' => 'Template 1',
            'mode' => 'immediate',
            'request_config' => ['url' => 'https://api.example.com/1'],
        ]);

        $response2 = $this->postJson('/api/v1/templates', [
            'name' => 'Template 2',
            'mode' => 'immediate',
            'request_config' => ['url' => 'https://api.example.com/2'],
        ]);

        $token1 = $response1->json('data.trigger_token');
        $token2 = $response2->json('data.trigger_token');

        $this->assertNotEquals($token1, $token2);
        $this->assertStringStartsWith('clmt_', $token1);
        $this->assertStringStartsWith('clmt_', $token2);
    }

    public function test_create_allows_placeholders_in_coordination_keys(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/templates', [
            'name' => 'Deploy Service',
            'mode' => 'immediate',
            'request_config' => ['url' => 'https://api.example.com/deploy'],
            'default_coordination_keys' => [
                'booking{{idbooking}}',
                'deployment:{{service}}',
                'env:{{env}}',
            ],
            'placeholders' => [
                ['name' => 'idbooking', 'required' => true],
                ['name' => 'service', 'required' => true],
                ['name' => 'env', 'required' => false, 'default' => 'staging'],
            ],
        ]);

        $response->assertStatus(201);
        $this->assertEquals(
            ['booking{{idbooking}}', 'deployment:{{service}}', 'env:{{env}}'],
            $response->json('data.default_coordination_keys')
        );
    }

    public function test_create_rejects_invalid_coordination_keys(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/templates', [
            'name' => 'Deploy Service',
            'mode' => 'immediate',
            'request_config' => ['url' => 'https://api.example.com/deploy'],
            'default_coordination_keys' => [
                'invalid key with spaces',
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_create_rejects_coordination_keys_with_invalid_placeholder_syntax(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/templates', [
            'name' => 'Deploy Service',
            'mode' => 'immediate',
            'request_config' => ['url' => 'https://api.example.com/deploy'],
            'default_coordination_keys' => [
                'booking{idbooking}', // Single braces - invalid
            ],
        ]);

        $response->assertStatus(422);
    }

    // ==================== CRUD: VIEW ====================

    public function test_can_view_template(): void
    {
        Sanctum::actingAs($this->user);

        $template = ActionTemplate::factory()->create([
            'account_id' => $this->user->account_id,
        ]);

        $response = $this->getJson("/api/v1/templates/{$template->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $template->id);
    }

    public function test_cannot_view_other_account_template(): void
    {
        Sanctum::actingAs($this->user);

        $template = ActionTemplate::factory()->create([
            'account_id' => $this->otherUser->account_id,
        ]);

        $response = $this->getJson("/api/v1/templates/{$template->id}");

        $response->assertStatus(404);
    }

    // ==================== CRUD: UPDATE ====================

    public function test_can_update_template(): void
    {
        Sanctum::actingAs($this->user);

        $template = ActionTemplate::factory()->create([
            'account_id' => $this->user->account_id,
            'name' => 'Original Name',
        ]);

        $response = $this->putJson("/api/v1/templates/{$template->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name');
    }

    public function test_cannot_update_other_account_template(): void
    {
        Sanctum::actingAs($this->user);

        $template = ActionTemplate::factory()->create([
            'account_id' => $this->otherUser->account_id,
        ]);

        $response = $this->putJson("/api/v1/templates/{$template->id}", [
            'name' => 'Hacked',
        ]);

        $response->assertStatus(404);
    }

    // ==================== CRUD: DELETE ====================

    public function test_can_delete_template(): void
    {
        Sanctum::actingAs($this->user);

        $template = ActionTemplate::factory()->create([
            'account_id' => $this->user->account_id,
        ]);

        $response = $this->deleteJson("/api/v1/templates/{$template->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('action_templates', ['id' => $template->id]);
    }

    public function test_cannot_delete_other_account_template(): void
    {
        Sanctum::actingAs($this->user);

        $template = ActionTemplate::factory()->create([
            'account_id' => $this->otherUser->account_id,
        ]);

        $response = $this->deleteJson("/api/v1/templates/{$template->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('action_templates', ['id' => $template->id]);
    }

    // ==================== REGENERATE TOKEN ====================

    public function test_can_regenerate_token(): void
    {
        Sanctum::actingAs($this->user);

        $template = ActionTemplate::factory()->create([
            'account_id' => $this->user->account_id,
        ]);
        $oldToken = $template->trigger_token;

        $response = $this->postJson("/api/v1/templates/{$template->id}/regenerate-token");

        $response->assertStatus(200);
        $newToken = $response->json('data.trigger_token');

        $this->assertNotEquals($oldToken, $newToken);
        $this->assertStringStartsWith('clmt_', $newToken);
    }

    public function test_regenerate_invalidates_old_token(): void
    {
        $template = ActionTemplate::factory()->create([
            'account_id' => $this->user->account_id,
            'mode' => 'immediate',
            'request_config' => [
                'url' => 'https://api.example.com/test',
                'method' => 'POST',
            ],
        ]);
        $oldToken = $template->trigger_token;

        // Regenerate token
        Sanctum::actingAs($this->user);
        $this->postJson("/api/v1/templates/{$template->id}/regenerate-token");

        // Old token should not work
        $response = $this->postJson("/t/{$oldToken}", [
            'intent' => ['delay' => '1h'],
        ]);

        $response->assertStatus(404);
    }

    // ==================== PLAN LIMITS ====================

    public function test_free_plan_limited_to_templates(): void
    {
        Sanctum::actingAs($this->user);

        // Create templates up to the free plan limit
        $limit = config('callmelater.plans.free.max_templates', 2);

        for ($i = 0; $i < $limit; $i++) {
            ActionTemplate::factory()->create([
                'account_id' => $this->user->account_id,
            ]);
        }

        // Try to create one more
        $response = $this->postJson('/api/v1/templates', [
            'name' => 'Over Limit',
            'mode' => 'immediate',
            'request_config' => ['url' => 'https://api.example.com/test'],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.limit.0', fn ($msg) => str_contains($msg, 'limit'));
    }

    // ==================== TRIGGER: BASIC ====================

    public function test_can_trigger_template_creates_action(): void
    {
        $template = ActionTemplate::factory()->create([
            'account_id' => $this->user->account_id,
            'mode' => 'immediate',
            'request_config' => [
                'url' => 'https://api.example.com/webhook',
                'method' => 'POST',
            ],
        ]);

        $response = $this->postJson("/t/{$template->trigger_token}", [
            'intent' => ['delay' => '1h'],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.mode', 'immediate')
            ->assertJsonPath('data.template_id', $template->id);

        $this->assertDatabaseHas('scheduled_actions', [
            'template_id' => $template->id,
            'account_id' => $this->user->account_id,
        ]);
    }

    public function test_trigger_substitutes_placeholders_in_url(): void
    {
        $template = ActionTemplate::factory()->create([
            'account_id' => $this->user->account_id,
            'mode' => 'immediate',
            'request_config' => [
                'url' => 'https://api.example.com/{{service}}/deploy',
                'method' => 'POST',
            ],
            'placeholders' => [
                ['name' => 'service', 'required' => true],
            ],
        ]);

        $response = $this->postJson("/t/{$template->trigger_token}", [
            'service' => 'api-gateway',
            'intent' => ['delay' => '1h'],
        ]);

        $response->assertStatus(201);

        $action = ScheduledAction::where('template_id', $template->id)->first();
        $this->assertEquals('https://api.example.com/api-gateway/deploy', $action->request['url']);
    }

    public function test_trigger_substitutes_placeholders_in_headers(): void
    {
        $template = ActionTemplate::factory()->create([
            'account_id' => $this->user->account_id,
            'mode' => 'immediate',
            'request_config' => [
                'url' => 'https://api.example.com/deploy',
                'method' => 'POST',
                'headers' => [
                    'X-Service' => '{{service}}',
                    'Authorization' => 'Bearer {{token}}',
                ],
            ],
            'placeholders' => [
                ['name' => 'service', 'required' => true],
                ['name' => 'token', 'required' => true],
            ],
        ]);

        $response = $this->postJson("/t/{$template->trigger_token}", [
            'service' => 'users',
            'token' => 'abc123',
            'intent' => ['delay' => '1m'],
        ]);

        $response->assertStatus(201);

        $action = ScheduledAction::where('template_id', $template->id)->first();
        $this->assertEquals('users', $action->request['headers']['X-Service']);
        $this->assertEquals('Bearer abc123', $action->request['headers']['Authorization']);
    }

    public function test_trigger_substitutes_placeholders_in_body(): void
    {
        $template = ActionTemplate::factory()->create([
            'account_id' => $this->user->account_id,
            'mode' => 'immediate',
            'request_config' => [
                'url' => 'https://api.example.com/deploy',
                'method' => 'POST',
                'body' => [
                    'service' => '{{service}}',
                    'version' => '{{version}}',
                ],
            ],
            'placeholders' => [
                ['name' => 'service', 'required' => true],
                ['name' => 'version', 'required' => true],
            ],
        ]);

        $response = $this->postJson("/t/{$template->trigger_token}", [
            'service' => 'api',
            'version' => '2.0.1',
            'intent' => ['delay' => '1m'],
        ]);

        $response->assertStatus(201);

        $action = ScheduledAction::where('template_id', $template->id)->first();
        $this->assertEquals('api', $action->request['body']['service']);
        $this->assertEquals('2.0.1', $action->request['body']['version']);
    }

    public function test_trigger_substitutes_placeholders_in_gate_message(): void
    {
        $template = ActionTemplate::factory()->create([
            'account_id' => $this->user->account_id,
            'mode' => 'gated',
            'gate_config' => [
                'message' => 'Deploy {{service}} v{{version}} to {{env}}?',
                'recipients' => ['ops@example.com'],
                'timeout' => '4h',
            ],
            'placeholders' => [
                ['name' => 'service', 'required' => true],
                ['name' => 'version', 'required' => true],
                ['name' => 'env', 'required' => true],
            ],
        ]);

        $response = $this->postJson("/t/{$template->trigger_token}", [
            'service' => 'api-gateway',
            'version' => '3.0',
            'env' => 'production',
            'intent' => ['delay' => '1m'],
        ]);

        $response->assertStatus(201);

        $action = ScheduledAction::where('template_id', $template->id)->first();
        $this->assertEquals('Deploy api-gateway v3.0 to production?', $action->gate['message']);
    }

    // ==================== TRIGGER: VALIDATION ====================

    public function test_trigger_validates_required_placeholders(): void
    {
        $template = ActionTemplate::factory()->create([
            'account_id' => $this->user->account_id,
            'mode' => 'immediate',
            'request_config' => [
                'url' => 'https://api.example.com/{{service}}/deploy',
            ],
            'placeholders' => [
                ['name' => 'service', 'required' => true],
            ],
        ]);

        $response = $this->postJson("/t/{$template->trigger_token}", [
            'intent' => ['delay' => '1h'],
            // Missing 'service' parameter
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.service', fn ($msg) => str_contains($msg, 'service'));
    }

    public function test_trigger_applies_default_values(): void
    {
        $template = ActionTemplate::factory()->create([
            'account_id' => $this->user->account_id,
            'mode' => 'immediate',
            'request_config' => [
                'url' => 'https://api.example.com/{{env}}/deploy',
            ],
            'placeholders' => [
                ['name' => 'env', 'required' => false, 'default' => 'staging'],
            ],
        ]);

        $response = $this->postJson("/t/{$template->trigger_token}", [
            'intent' => ['delay' => '1h'],
            // Not providing 'env' - should use default
        ]);

        $response->assertStatus(201);

        $action = ScheduledAction::where('template_id', $template->id)->first();
        $this->assertEquals('https://api.example.com/staging/deploy', $action->request['url']);
    }

    public function test_trigger_fails_with_missing_required_placeholder(): void
    {
        $template = ActionTemplate::factory()->create([
            'account_id' => $this->user->account_id,
            'mode' => 'immediate',
            'request_config' => [
                'url' => 'https://api.example.com/{{service}}',
            ],
            'placeholders' => [
                ['name' => 'service', 'required' => true],
                ['name' => 'version', 'required' => true],
            ],
        ]);

        $response = $this->postJson("/t/{$template->trigger_token}", [
            'service' => 'api',
            // Missing 'version'
            'intent' => ['delay' => '1h'],
        ]);

        $response->assertStatus(422);
    }

    // ==================== TRIGGER: STATS ====================

    public function test_trigger_increments_trigger_count(): void
    {
        $template = ActionTemplate::factory()->create([
            'account_id' => $this->user->account_id,
            'mode' => 'immediate',
            'trigger_count' => 5,
            'request_config' => [
                'url' => 'https://api.example.com/test',
            ],
        ]);

        $this->postJson("/t/{$template->trigger_token}", [
            'intent' => ['delay' => '1h'],
        ]);

        $template->refresh();
        $this->assertEquals(6, $template->trigger_count);
    }

    public function test_trigger_updates_last_triggered_at(): void
    {
        $template = ActionTemplate::factory()->create([
            'account_id' => $this->user->account_id,
            'mode' => 'immediate',
            'last_triggered_at' => null,
            'request_config' => [
                'url' => 'https://api.example.com/test',
            ],
        ]);

        $this->postJson("/t/{$template->trigger_token}", [
            'intent' => ['delay' => '1h'],
        ]);

        $template->refresh();
        $this->assertNotNull($template->last_triggered_at);
    }

    // ==================== TRIGGER: ERRORS ====================

    public function test_trigger_returns_404_for_invalid_token(): void
    {
        $response = $this->postJson('/api/t/clmt_nonexistent_token_that_does_not_exist_', [
            'intent' => ['delay' => '1h'],
        ]);

        $response->assertStatus(404);
    }

    public function test_trigger_returns_404_for_inactive_template(): void
    {
        $template = ActionTemplate::factory()->create([
            'account_id' => $this->user->account_id,
            'mode' => 'immediate',
            'is_active' => false,
            'request_config' => [
                'url' => 'https://api.example.com/test',
            ],
        ]);

        $response = $this->postJson("/t/{$template->trigger_token}", [
            'intent' => ['delay' => '1h'],
        ]);

        $response->assertStatus(404);
    }

    // ==================== ACTION LINKS TO TEMPLATE ====================

    public function test_action_links_back_to_template(): void
    {
        $template = ActionTemplate::factory()->create([
            'account_id' => $this->user->account_id,
            'mode' => 'immediate',
            'request_config' => [
                'url' => 'https://api.example.com/test',
            ],
        ]);

        $this->postJson("/t/{$template->trigger_token}", [
            'intent' => ['delay' => '1h'],
        ]);

        $action = ScheduledAction::where('template_id', $template->id)->first();

        $this->assertNotNull($action);
        $this->assertEquals($template->id, $action->template_id);
        $this->assertNotNull($action->template);
        $this->assertEquals($template->name, $action->template->name);
    }

    // ==================== COORDINATION ====================

    public function test_trigger_uses_default_coordination_keys(): void
    {
        $template = ActionTemplate::factory()->create([
            'account_id' => $this->user->account_id,
            'mode' => 'immediate',
            'request_config' => [
                'url' => 'https://api.example.com/test',
            ],
            'default_coordination_keys' => ['deployment:api', 'env:prod'],
        ]);

        $response = $this->postJson("/t/{$template->trigger_token}", [
            'intent' => ['delay' => '1h'],
        ]);

        $response->assertStatus(201);

        $action = ScheduledAction::where('template_id', $template->id)->first();
        $this->assertContains('deployment:api', $action->coordination_keys);
        $this->assertContains('env:prod', $action->coordination_keys);
    }

    public function test_trigger_substitutes_placeholders_in_coordination_keys(): void
    {
        $template = ActionTemplate::factory()->create([
            'account_id' => $this->user->account_id,
            'mode' => 'immediate',
            'request_config' => [
                'url' => 'https://api.example.com/{{service}}',
            ],
            'default_coordination_keys' => ['deployment:{{service}}', 'env:{{env}}'],
            'placeholders' => [
                ['name' => 'service', 'required' => true],
                ['name' => 'env', 'required' => true],
            ],
        ]);

        $response = $this->postJson("/t/{$template->trigger_token}", [
            'service' => 'api-gateway',
            'env' => 'production',
            'intent' => ['delay' => '1h'],
        ]);

        $response->assertStatus(201);

        $action = ScheduledAction::where('template_id', $template->id)->first();
        $this->assertContains('deployment:api-gateway', $action->coordination_keys);
        $this->assertContains('env:production', $action->coordination_keys);
    }

    // ==================== INTENT ====================

    public function test_trigger_uses_default_1s_delay(): void
    {
        $template = ActionTemplate::factory()->create([
            'account_id' => $this->user->account_id,
            'mode' => 'immediate',
            'request_config' => [
                'url' => 'https://api.example.com/test',
            ],
        ]);

        $response = $this->postJson("/t/{$template->trigger_token}", [
            // No intent specified - should default to 1s
        ]);

        $response->assertStatus(201);
    }

    public function test_trigger_accepts_custom_intent(): void
    {
        $template = ActionTemplate::factory()->create([
            'account_id' => $this->user->account_id,
            'mode' => 'immediate',
            'request_config' => [
                'url' => 'https://api.example.com/test',
            ],
        ]);

        $response = $this->postJson("/t/{$template->trigger_token}", [
            'intent' => ['delay' => '2h'],
        ]);

        $response->assertStatus(201);
    }

    // ==================== TOGGLE ACTIVE ====================

    public function test_can_toggle_template_active(): void
    {
        Sanctum::actingAs($this->user);

        $template = ActionTemplate::factory()->create([
            'account_id' => $this->user->account_id,
            'is_active' => true,
        ]);

        $response = $this->postJson("/api/v1/templates/{$template->id}/toggle-active");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_active', false);

        $response = $this->postJson("/api/v1/templates/{$template->id}/toggle-active");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_active', true);
    }

    // ==================== LIMITS ENDPOINT ====================

    public function test_limits_endpoint_returns_plan_info(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/templates/limits');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'current',
                'max',
                'plan',
            ]);
    }

    // ==================== STRING BODY TEMPLATES ====================

    public function test_trigger_substitutes_numeric_placeholders_in_string_body(): void
    {
        // Test that body can be stored as a string template for numeric placeholder values
        // e.g., {"idbooking": {{idbooking}}} -> {"idbooking": 12345}
        $template = ActionTemplate::factory()->create([
            'account_id' => $this->user->account_id,
            'mode' => 'immediate',
            'request_config' => [
                'url' => 'https://api.example.com/bookings',
                'method' => 'POST',
                // Body stored as string to allow numeric placeholder substitution
                'body' => '{"idbooking": {{idbooking}}, "name": "{{name}}"}',
            ],
            'placeholders' => [
                ['name' => 'idbooking', 'required' => true],
                ['name' => 'name', 'required' => true],
            ],
        ]);

        $response = $this->postJson("/t/{$template->trigger_token}", [
            'idbooking' => 12345,
            'name' => 'Test Booking',
        ]);

        $response->assertStatus(201);

        $action = ScheduledAction::where('template_id', $template->id)->first();
        $this->assertEquals(12345, $action->request['body']['idbooking']);
        $this->assertEquals('Test Booking', $action->request['body']['name']);
    }
}

<?php

namespace Tests\Feature;

use App\Models\ChatConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntegrationApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_lists_integrations(): void
    {
        // Give user Pro plan to access integrations
        $this->user->account->update(['manual_plan' => 'pro']);

        ChatConnection::create([
            'account_id' => $this->user->account_id,
            'provider' => 'teams',
            'name' => 'DevOps Channel',
            'teams_webhook_url' => 'https://outlook.office.com/webhook/test',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/integrations');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.provider', 'teams')
            ->assertJsonPath('data.0.name', 'DevOps Channel')
            ->assertJsonPath('can_create', true);
    }

    public function test_free_plan_cannot_create_integration(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/integrations', [
            'provider' => 'teams',
            'name' => 'Test',
            'teams_webhook_url' => 'https://outlook.office.com/webhook/test',
        ]);

        $response->assertForbidden()
            ->assertJsonPath('message', 'Chat integrations require a Pro or Business plan.');
    }

    public function test_creates_teams_integration(): void
    {
        $this->user->account->update(['manual_plan' => 'pro']);

        $response = $this->actingAs($this->user)->postJson('/api/v1/integrations', [
            'provider' => 'teams',
            'name' => 'Engineering Channel',
            'teams_webhook_url' => 'https://outlook.office.com/webhook/some-id/some-token',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.provider', 'teams')
            ->assertJsonPath('data.name', 'Engineering Channel')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('chat_connections', [
            'account_id' => $this->user->account_id,
            'provider' => 'teams',
            'name' => 'Engineering Channel',
        ]);
    }

    public function test_validates_teams_webhook_url_format(): void
    {
        $this->user->account->update(['manual_plan' => 'pro']);

        $response = $this->actingAs($this->user)->postJson('/api/v1/integrations', [
            'provider' => 'teams',
            'name' => 'Test',
            'teams_webhook_url' => 'https://example.com/webhook', // Not a Teams URL
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Invalid Teams webhook URL. Please use an Incoming Webhook URL from Microsoft Teams or a Workflows webhook URL.');
    }

    public function test_deletes_integration(): void
    {
        $this->user->account->update(['manual_plan' => 'pro']);

        $connection = ChatConnection::create([
            'account_id' => $this->user->account_id,
            'provider' => 'teams',
            'name' => 'To Delete',
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/api/v1/integrations/{$connection->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Connection deleted.');

        $this->assertDatabaseMissing('chat_connections', ['id' => $connection->id]);
    }

    public function test_cannot_delete_other_accounts_integration(): void
    {
        $otherUser = User::factory()->create();
        $connection = ChatConnection::create([
            'account_id' => $otherUser->account_id,
            'provider' => 'teams',
            'name' => 'Other Account',
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/api/v1/integrations/{$connection->id}");

        $response->assertNotFound();
    }

    public function test_tests_teams_integration(): void
    {
        $this->user->account->update(['manual_plan' => 'pro']);

        Http::fake([
            'outlook.office.com/*' => Http::response('', 200),
        ]);

        $connection = ChatConnection::create([
            'account_id' => $this->user->account_id,
            'provider' => 'teams',
            'name' => 'Test Channel',
            'teams_webhook_url' => 'https://outlook.office.com/webhook/test',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->postJson("/api/v1/integrations/{$connection->id}/test");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Test message sent successfully.');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'outlook.office.com');
        });
    }

    public function test_test_fails_with_bad_webhook(): void
    {
        $this->user->account->update(['manual_plan' => 'pro']);

        Http::fake([
            'outlook.office.com/*' => Http::response('Unauthorized', 401),
        ]);

        $connection = ChatConnection::create([
            'account_id' => $this->user->account_id,
            'provider' => 'teams',
            'name' => 'Bad Webhook',
            'teams_webhook_url' => 'https://outlook.office.com/webhook/bad',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->postJson("/api/v1/integrations/{$connection->id}/test");

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_toggles_integration(): void
    {
        $this->user->account->update(['manual_plan' => 'pro']);

        $connection = ChatConnection::create([
            'account_id' => $this->user->account_id,
            'provider' => 'teams',
            'name' => 'Toggle Test',
            'is_active' => true,
        ]);

        // Disable
        $response = $this->actingAs($this->user)->postJson("/api/v1/integrations/{$connection->id}/toggle");
        $response->assertOk()
            ->assertJsonPath('is_active', false);

        // Enable
        $response = $this->actingAs($this->user)->postJson("/api/v1/integrations/{$connection->id}/toggle");
        $response->assertOk()
            ->assertJsonPath('is_active', true);
    }

    public function test_integration_response_hides_sensitive_data(): void
    {
        $this->user->account->update(['manual_plan' => 'pro']);

        ChatConnection::create([
            'account_id' => $this->user->account_id,
            'provider' => 'teams',
            'name' => 'Sensitive Test',
            'teams_webhook_url' => 'https://outlook.office.com/webhook/secret-token',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/integrations');

        $response->assertOk()
            ->assertJsonPath('data.0.has_webhook_url', true)
            ->assertJsonMissing(['teams_webhook_url']); // Should not expose full URL
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/integrations')->assertUnauthorized();
        $this->postJson('/api/v1/integrations')->assertUnauthorized();
    }
}

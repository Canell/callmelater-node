<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Webhook;
use App\Services\UrlValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class WebhookApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();

        // Mock UrlValidator to bypass DNS resolution in tests (except for blocked hosts)
        $urlValidator = Mockery::mock(UrlValidator::class);
        $urlValidator->shouldReceive('validate')
            ->andReturnUsing(function (string $url) {
                // Still block localhost for security tests
                if (str_contains($url, 'localhost')) {
                    throw new \InvalidArgumentException('This hostname is not allowed');
                }
            });
        $this->app->instance(UrlValidator::class, $urlValidator);
    }

    public function test_can_list_webhooks(): void
    {
        Webhook::factory()->count(3)->create([
            'account_id' => $this->user->account_id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/webhooks');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_webhook(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/webhooks', [
                'name' => 'My Webhook',
                'url' => 'https://example.com/webhook',
                'events' => ['action.executed', 'action.failed'],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'My Webhook')
            ->assertJsonPath('data.url', 'https://example.com/webhook');

        $this->assertDatabaseHas('webhooks', [
            'account_id' => $this->user->account_id,
            'name' => 'My Webhook',
            'url' => 'https://example.com/webhook',
        ]);
    }

    public function test_create_webhook_requires_events(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/webhooks', [
                'url' => 'https://example.com/webhook',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['events']);
    }

    public function test_create_webhook_validates_event_types(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/webhooks', [
                'url' => 'https://example.com/webhook',
                'events' => ['invalid.event'],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['events.0']);
    }

    public function test_create_webhook_validates_url(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/webhooks', [
                'url' => 'not-a-url',
                'events' => ['action.executed'],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['url']);
    }

    public function test_create_webhook_blocks_localhost(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/webhooks', [
                'url' => 'http://localhost/webhook',
                'events' => ['action.executed'],
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.url.0', 'This hostname is not allowed');
    }

    public function test_duplicate_url_updates_existing_webhook(): void
    {
        $webhook = Webhook::factory()->create([
            'account_id' => $this->user->account_id,
            'url' => 'https://example.com/webhook',
            'events' => ['action.executed'],
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/webhooks', [
                'name' => 'Updated Name',
                'url' => 'https://example.com/webhook',
                'events' => ['action.failed'],
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Webhook updated');

        // Should have merged events
        $webhook->refresh();
        $this->assertContains('action.executed', $webhook->events);
        $this->assertContains('action.failed', $webhook->events);
    }

    public function test_can_get_webhook(): void
    {
        $webhook = Webhook::factory()->create([
            'account_id' => $this->user->account_id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/webhooks/{$webhook->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $webhook->id);
    }

    public function test_cannot_get_other_accounts_webhook(): void
    {
        $otherUser = User::factory()->create();
        $webhook = Webhook::factory()->create([
            'account_id' => $otherUser->account_id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/webhooks/{$webhook->id}");

        $response->assertNotFound();
    }

    public function test_can_update_webhook(): void
    {
        $webhook = Webhook::factory()->create([
            'account_id' => $this->user->account_id,
            'name' => 'Old Name',
        ]);

        $response = $this->actingAs($this->user)
            ->patchJson("/api/v1/webhooks/{$webhook->id}", [
                'name' => 'New Name',
                'is_active' => false,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.is_active', false);
    }

    public function test_can_delete_webhook(): void
    {
        $webhook = Webhook::factory()->create([
            'account_id' => $this->user->account_id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/webhooks/{$webhook->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Webhook deleted');

        $this->assertDatabaseMissing('webhooks', ['id' => $webhook->id]);
    }

    public function test_cannot_delete_other_accounts_webhook(): void
    {
        $otherUser = User::factory()->create();
        $webhook = Webhook::factory()->create([
            'account_id' => $otherUser->account_id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/webhooks/{$webhook->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('webhooks', ['id' => $webhook->id]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ActionTestEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_test_endpoint_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/actions/test', [
            'url' => 'https://example.com/webhook',
        ]);

        $response->assertStatus(401);
    }

    public function test_test_endpoint_requires_url(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/actions/test', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['url']);
    }

    public function test_test_endpoint_validates_url_format(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/actions/test', [
                'url' => 'not-a-valid-url',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['url']);
    }

    public function test_test_endpoint_validates_method(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/actions/test', [
                'url' => 'https://example.com/webhook',
                'method' => 'INVALID',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['method']);
    }

    public function test_test_endpoint_returns_success_result(): void
    {
        // Disable IP blocking since Http::fake() doesn't make real network calls
        config(['callmelater.http.block_private_ips' => false]);

        Http::fake([
            'https://example.com/webhook' => Http::response(['received' => true], 200),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/actions/test', [
                'url' => 'https://example.com/webhook',
                'method' => 'POST',
                'body' => ['test' => true],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'status_code' => 200,
            ])
            ->assertJsonStructure([
                'success',
                'status_code',
                'duration_ms',
                'error',
                'body',
            ]);
    }

    public function test_test_endpoint_returns_failure_result(): void
    {
        // Disable IP blocking since Http::fake() doesn't make real network calls
        config(['callmelater.http.block_private_ips' => false]);

        Http::fake([
            'https://example.com/webhook' => Http::response('Not Found', 404),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/actions/test', [
                'url' => 'https://example.com/webhook',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'status_code' => 404,
            ]);
    }

    public function test_test_endpoint_blocks_private_ips(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/actions/test', [
                'url' => 'http://192.168.1.1/internal',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'status_code' => null,
            ])
            ->assertJsonPath('error', fn ($error) => str_contains($error, 'private IP'));
    }

    public function test_test_endpoint_accepts_headers(): void
    {
        // Disable IP blocking since Http::fake() doesn't make real network calls
        config(['callmelater.http.block_private_ips' => false]);

        Http::fake([
            'https://example.com/webhook' => Http::response(['ok' => true], 200),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/actions/test', [
                'url' => 'https://example.com/webhook',
                'method' => 'POST',
                'headers' => [
                    'Authorization' => 'Bearer test-token',
                    'X-Custom-Header' => 'custom-value',
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer test-token')
                && $request->hasHeader('X-Custom-Header', 'custom-value');
        });
    }
}

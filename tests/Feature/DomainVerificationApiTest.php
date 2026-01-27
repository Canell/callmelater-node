<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VerifiedDomain;
use App\Services\DomainVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DomainVerificationApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    // ==================== LIST DOMAINS ====================

    public function test_can_list_domains(): void
    {
        // Create some domains
        VerifiedDomain::create([
            'account_id' => $this->user->account_id,
            'domain' => 'example.com',
            'verification_token' => VerifiedDomain::generateToken(),
            'verified_at' => now(),
            'expires_at' => now()->addMonths(12),
            'method' => VerifiedDomain::METHOD_DNS,
        ]);

        VerifiedDomain::create([
            'account_id' => $this->user->account_id,
            'domain' => 'test.com',
            'verification_token' => VerifiedDomain::generateToken(),
        ]);

        $response = $this->getJson('/api/v1/domains');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'domain',
                        'verified',
                        'verification_token',
                    ],
                ],
            ])
            ->assertJsonCount(2, 'data');
    }

    public function test_user_only_sees_own_account_domains(): void
    {
        // Create domain for current user
        VerifiedDomain::create([
            'account_id' => $this->user->account_id,
            'domain' => 'mysite.com',
            'verification_token' => VerifiedDomain::generateToken(),
        ]);

        // Create domain for another user
        $otherUser = User::factory()->create();
        VerifiedDomain::create([
            'account_id' => $otherUser->account_id,
            'domain' => 'othersite.com',
            'verification_token' => VerifiedDomain::generateToken(),
        ]);

        $response = $this->getJson('/api/v1/domains');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.domain', 'mysite.com');
    }

    // ==================== SHOW DOMAIN ====================

    public function test_can_get_domain_verification_instructions(): void
    {
        $response = $this->getJson('/api/v1/domains/example.com');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'domain',
                'verified',
                'verification_token',
                'verification_methods' => [
                    'dns' => ['type', 'value', 'instructions'],
                    'file' => ['url', 'content', 'instructions'],
                ],
            ])
            ->assertJsonPath('domain', 'example.com')
            ->assertJsonPath('verified', false);
    }

    public function test_get_domain_creates_verification_record(): void
    {
        $this->assertDatabaseMissing('verified_domains', [
            'domain' => 'newdomain.com',
        ]);

        $response = $this->getJson('/api/v1/domains/newdomain.com');

        $response->assertStatus(200);

        $this->assertDatabaseHas('verified_domains', [
            'account_id' => $this->user->account_id,
            'domain' => 'newdomain.com',
        ]);
    }

    public function test_get_domain_normalizes_url(): void
    {
        // The domain is passed in the URL path, so we use simple uppercase to test normalization
        $response = $this->getJson('/api/v1/domains/EXAMPLE.COM');

        $response->assertStatus(200)
            ->assertJsonPath('domain', 'example.com');
    }

    // ==================== VERIFY DOMAIN ====================

    public function test_verify_returns_404_for_unknown_domain(): void
    {
        $response = $this->postJson('/api/v1/domains/unknown.com/verify');

        $response->assertStatus(404)
            ->assertJson(['error' => 'not_found']);
    }

    public function test_verify_already_verified_domain_returns_success(): void
    {
        VerifiedDomain::create([
            'account_id' => $this->user->account_id,
            'domain' => 'verified.com',
            'verification_token' => VerifiedDomain::generateToken(),
            'verified_at' => now(),
            'expires_at' => now()->addMonths(12),
            'method' => VerifiedDomain::METHOD_DNS,
        ]);

        $response = $this->postJson('/api/v1/domains/verified.com/verify');

        $response->assertStatus(200)
            ->assertJson([
                'verified' => true,
                'message' => 'Domain is already verified.',
            ]);
    }

    // Domain verification is now permanent (no expiration), so no test for expired domains needed

    // ==================== DELETE DOMAIN ====================

    public function test_can_delete_domain(): void
    {
        VerifiedDomain::create([
            'account_id' => $this->user->account_id,
            'domain' => 'todelete.com',
            'verification_token' => VerifiedDomain::generateToken(),
        ]);

        $response = $this->deleteJson('/api/v1/domains/todelete.com');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Domain verification removed.']);

        $this->assertDatabaseMissing('verified_domains', [
            'domain' => 'todelete.com',
        ]);
    }

    public function test_delete_returns_404_for_unknown_domain(): void
    {
        $response = $this->deleteJson('/api/v1/domains/notfound.com');

        $response->assertStatus(404)
            ->assertJson(['error' => 'not_found']);
    }

    public function test_cannot_delete_other_users_domain(): void
    {
        $otherUser = User::factory()->create();
        VerifiedDomain::create([
            'account_id' => $otherUser->account_id,
            'domain' => 'otheruser.com',
            'verification_token' => VerifiedDomain::generateToken(),
        ]);

        $response = $this->deleteJson('/api/v1/domains/otheruser.com');

        $response->assertStatus(404);
    }

    // ==================== AUTHENTICATION ====================

    public function test_unauthenticated_user_cannot_access_domains(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/v1/domains');

        $response->assertStatus(401);
    }
}

<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\AccountPlanOverride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualPlanOverrideTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->account = $this->user->account;
    }

    public function test_get_plan_returns_free_by_default(): void
    {
        $this->assertEquals('free', $this->account->getPlan());
    }

    public function test_manual_plan_overrides_stripe_subscription(): void
    {
        $this->account->update(['manual_plan' => 'pro']);

        $this->assertEquals('pro', $this->account->getPlan());
        $this->assertTrue($this->account->isPlanManuallyManaged());
    }

    public function test_manual_plan_business(): void
    {
        $this->account->update(['manual_plan' => 'business']);

        $this->assertEquals('business', $this->account->getPlan());
        $this->assertTrue($this->account->isPlanManuallyManaged());
    }

    public function test_expired_manual_plan_falls_back_to_free(): void
    {
        $this->account->update([
            'manual_plan' => 'pro',
            'manual_plan_expires_at' => now()->subDay(),
        ]);

        $this->assertEquals('free', $this->account->getPlan());
        $this->assertFalse($this->account->isPlanManuallyManaged());
    }

    public function test_non_expired_manual_plan_is_active(): void
    {
        $this->account->update([
            'manual_plan' => 'pro',
            'manual_plan_expires_at' => now()->addDay(),
        ]);

        $this->assertEquals('pro', $this->account->getPlan());
        $this->assertTrue($this->account->isPlanManuallyManaged());
    }

    public function test_null_expiration_means_no_expiry(): void
    {
        $this->account->update([
            'manual_plan' => 'business',
            'manual_plan_expires_at' => null,
        ]);

        $this->assertEquals('business', $this->account->getPlan());
        $this->assertTrue($this->account->isPlanManuallyManaged());
    }

    public function test_invalid_manual_plan_value_is_ignored(): void
    {
        $this->account->update(['manual_plan' => 'invalid']);

        $this->assertEquals('free', $this->account->getPlan());
        $this->assertFalse($this->account->isPlanManuallyManaged());
    }

    public function test_set_manual_plan_creates_audit_log(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->account->setManualPlan('pro', now()->addMonths(3), 'Beta tester', $admin);

        $this->assertEquals('pro', $this->account->fresh()->getPlan());

        $this->assertDatabaseHas('account_plan_overrides', [
            'account_id' => $this->account->id,
            'plan' => 'pro',
            'reason' => 'Beta tester',
            'set_by_user_id' => $admin->id,
            'action' => 'set',
        ]);
    }

    public function test_revoke_manual_plan_creates_audit_log(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->account->update(['manual_plan' => 'pro']);
        $this->account->revokeManualPlan('No longer needed', $admin);

        $this->assertEquals('free', $this->account->fresh()->getPlan());

        $this->assertDatabaseHas('account_plan_overrides', [
            'account_id' => $this->account->id,
            'plan' => null,
            'reason' => 'No longer needed',
            'set_by_user_id' => $admin->id,
            'action' => 'revoked',
        ]);
    }

    public function test_plan_limits_use_manual_plan(): void
    {
        $this->account->update(['manual_plan' => 'pro']);

        $limits = $this->account->getPlanLimits();

        // Pro plan should have higher limits than free
        $freeLimits = config('callmelater.plans.free');
        $proLimits = config('callmelater.plans.pro');

        $this->assertEquals($proLimits, $limits);
        $this->assertGreaterThan($freeLimits['max_actions_per_month'], $limits['max_actions_per_month']);
    }

    public function test_subscription_api_returns_manual_plan_info(): void
    {
        $this->account->update([
            'manual_plan' => 'business',
            'manual_plan_expires_at' => now()->addMonths(3),
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/subscription/status');

        $response->assertStatus(200)
            ->assertJson([
                'plan' => 'business',
                'is_manually_managed' => true,
            ])
            ->assertJsonStructure([
                'manual_plan_expires_at',
            ]);
    }

    public function test_subscription_api_shows_not_manually_managed_for_free(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/subscription/status');

        $response->assertStatus(200)
            ->assertJson([
                'plan' => 'free',
                'is_manually_managed' => false,
            ]);
    }
}

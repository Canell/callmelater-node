<?php

namespace Tests\Feature;

use App\Models\UsageCounter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuotaApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_can_get_quota(): void
    {
        $response = $this->getJson('/api/v1/quota');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'period' => ['year', 'month', 'month_name'],
                'actions' => ['used', 'limit', 'percentage'],
                'sms' => ['used', 'limit', 'percentage'],
                'plan',
            ]);
    }

    public function test_quota_returns_correct_period(): void
    {
        $response = $this->getJson('/api/v1/quota');

        $response->assertStatus(200)
            ->assertJsonPath('period.year', now()->year)
            ->assertJsonPath('period.month', now()->month);
    }

    public function test_quota_returns_zero_for_new_account(): void
    {
        $response = $this->getJson('/api/v1/quota');

        $response->assertStatus(200)
            ->assertJsonPath('actions.used', 0)
            ->assertJsonPath('sms.used', 0);
    }

    public function test_quota_reflects_actual_usage(): void
    {
        $counter = UsageCounter::forCurrentMonth($this->user->account_id);
        $counter->incrementActions(25);
        $counter->incrementSms(5);

        $response = $this->getJson('/api/v1/quota');

        $response->assertStatus(200)
            ->assertJsonPath('actions.used', 25)
            ->assertJsonPath('sms.used', 5);
    }

    public function test_quota_returns_plan_limits(): void
    {
        $response = $this->getJson('/api/v1/quota');
        $data = $response->json();

        // Free plan has 100 actions per month
        $this->assertEquals(100, $data['actions']['limit']);
        // Free plan has 0 SMS
        $this->assertEquals(0, $data['sms']['limit']);
    }

    public function test_quota_returns_plan_name(): void
    {
        $response = $this->getJson('/api/v1/quota');

        $response->assertStatus(200)
            ->assertJsonPath('plan', 'free');
    }

    public function test_quota_calculates_percentage(): void
    {
        $counter = UsageCounter::forCurrentMonth($this->user->account_id);
        $counter->update(['actions_created' => 50]); // 50% of 100

        $response = $this->getJson('/api/v1/quota');

        $response->assertStatus(200);
        // JSON decodes whole number floats as integers
        $this->assertEquals(50, $response->json('actions.percentage'));
    }

    public function test_unauthenticated_user_cannot_get_quota(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/v1/quota');

        $response->assertStatus(401);
    }
}

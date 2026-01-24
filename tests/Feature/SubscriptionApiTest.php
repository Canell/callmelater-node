<?php

namespace Tests\Feature;

use App\Models\ScheduledAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_can_get_subscription_status(): void
    {
        $response = $this->getJson('/api/subscription/status');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'subscribed',
                'plan',
                'on_trial',
                'canceled',
                'can_manage_billing',
                'limits' => [
                    'actions_per_month',
                    'active_actions',
                    'max_attempts',
                    'recipients_per_reminder',
                    'new_recipients_per_day',
                    'history_days',
                ],
                'usage' => [
                    'actions_this_month',
                    'executions_this_month',
                    'reminders_this_month',
                ],
            ]);
    }

    public function test_free_user_has_correct_plan(): void
    {
        $response = $this->getJson('/api/subscription/status');

        $response->assertStatus(200)
            ->assertJsonPath('plan', 'free')
            ->assertJsonPath('subscribed', false);
    }

    public function test_usage_stats_reflect_actual_usage(): void
    {
        // Create some actions for this month
        for ($i = 0; $i < 3; $i++) {
            ScheduledAction::create([
                'account_id' => $this->user->account_id,
                'created_by_user_id' => $this->user->id,
                'name' => "Action {$i}",
                'mode' => ScheduledAction::MODE_IMMEDIATE,
                'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
                'intent_payload' => ['execute_at' => now()->addHour()->toIso8601String()],
                'resolution_status' => ScheduledAction::STATUS_PENDING_RESOLUTION,
                'request' => ['url' => 'https://example.com', 'method' => 'POST'],
            ]);
        }

        // Create an executed action
        ScheduledAction::create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'name' => 'Executed Action',
            'mode' => ScheduledAction::MODE_IMMEDIATE,
            'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
            'intent_payload' => ['execute_at' => now()->subHour()->toIso8601String()],
            'resolution_status' => ScheduledAction::STATUS_EXECUTED,
            'execute_at_utc' => now()->subHour(),
            'executed_at_utc' => now()->subMinutes(30),
            'request' => ['url' => 'https://example.com', 'method' => 'POST'],
        ]);

        // Create a reminder
        ScheduledAction::create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'name' => 'Test Reminder',
            'mode' => ScheduledAction::MODE_GATED,
            'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
            'intent_payload' => ['execute_at' => now()->addHour()->toIso8601String()],
            'resolution_status' => ScheduledAction::STATUS_PENDING_RESOLUTION,
            'gate' => [
                'message' => 'Test message',
                'recipients' => ['test@example.com'],
                'channels' => ['email'],
            ],
        ]);

        $response = $this->getJson('/api/subscription/status');

        $response->assertStatus(200)
            ->assertJsonPath('usage.actions_this_month', 5)
            ->assertJsonPath('usage.executions_this_month', 1)
            ->assertJsonPath('usage.reminders_this_month', 1);
    }

    public function test_checkout_requires_valid_plan(): void
    {
        $response = $this->postJson('/api/subscription/checkout', [
            'plan' => 'invalid_plan',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['plan']);
    }

    public function test_checkout_validates_billing_period(): void
    {
        $response = $this->postJson('/api/subscription/checkout', [
            'plan' => 'pro',
            'billing' => 'invalid_period',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['billing']);
    }

    public function test_non_owner_cannot_create_checkout(): void
    {
        // Create another user in the same account but not owner
        $member = User::factory()->create();
        $member->update(['account_id' => $this->user->account_id]);
        $this->user->account->members()->attach($member->id, ['role' => 'member']);

        Sanctum::actingAs($member);

        $response = $this->postJson('/api/subscription/checkout', [
            'plan' => 'pro',
        ]);

        $response->assertStatus(403)
            ->assertJson(['error' => 'Only account owner or admin can manage billing']);
    }

    public function test_non_owner_cannot_cancel_subscription(): void
    {
        $member = User::factory()->create();
        $member->update(['account_id' => $this->user->account_id]);
        $this->user->account->members()->attach($member->id, ['role' => 'member']);

        Sanctum::actingAs($member);

        $response = $this->postJson('/api/subscription/cancel');

        $response->assertStatus(403);
    }

    public function test_cancel_fails_without_active_subscription(): void
    {
        $response = $this->postJson('/api/subscription/cancel');

        $response->assertStatus(400)
            ->assertJson(['error' => 'No active subscription']);
    }

    public function test_resume_fails_without_canceled_subscription(): void
    {
        $response = $this->postJson('/api/subscription/resume');

        $response->assertStatus(400)
            ->assertJson(['error' => 'No canceled subscription to resume']);
    }

    public function test_non_owner_cannot_access_billing_portal(): void
    {
        $member = User::factory()->create();
        $member->update(['account_id' => $this->user->account_id]);
        $this->user->account->members()->attach($member->id, ['role' => 'member']);

        Sanctum::actingAs($member);

        $response = $this->postJson('/api/subscription/portal');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_subscription(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/subscription/status');

        $response->assertStatus(401);
    }

    public function test_owner_can_manage_billing(): void
    {
        $response = $this->getJson('/api/subscription/status');

        $response->assertStatus(200)
            ->assertJsonPath('can_manage_billing', true);
    }

    public function test_member_cannot_manage_billing(): void
    {
        $member = User::factory()->create();
        $member->update(['account_id' => $this->user->account_id]);
        $this->user->account->members()->attach($member->id, ['role' => 'member']);

        Sanctum::actingAs($member);

        $response = $this->getJson('/api/subscription/status');

        $response->assertStatus(200)
            ->assertJsonPath('can_manage_billing', false);
    }
}

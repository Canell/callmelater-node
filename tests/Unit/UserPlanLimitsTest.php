<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPlanLimitsTest extends TestCase
{
    use RefreshDatabase;
    public function test_free_user_gets_free_plan(): void
    {
        $user = User::factory()->create();

        $this->assertEquals('free', $user->getPlan());
    }

    public function test_free_plan_limits(): void
    {
        $user = User::factory()->create();

        $limits = $user->getPlanLimits();

        $this->assertEquals(100, $limits['max_actions_per_month']);
        $this->assertEquals(10, $limits['max_pending_actions']);
        $this->assertEquals(30, $limits['max_schedule_days']);
        $this->assertEquals(3, $limits['max_recipients']);
        $this->assertEquals(3, $limits['max_retries']);
    }

    public function test_get_plan_limit_returns_specific_value(): void
    {
        $user = User::factory()->create();

        $this->assertEquals(30, $user->getPlanLimit('max_schedule_days'));
        $this->assertEquals(3, $user->getPlanLimit('max_recipients'));
    }

    public function test_get_plan_limit_returns_default_for_unknown_key(): void
    {
        $user = User::factory()->create();

        $this->assertEquals(0, $user->getPlanLimit('unknown_key'));
        $this->assertEquals(999, $user->getPlanLimit('unknown_key', 999));
    }
}

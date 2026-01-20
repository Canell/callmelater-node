<?php

namespace Tests\Unit;

use App\Models\UsageCounter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsageCounterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_for_current_month_creates_counter_if_not_exists(): void
    {
        $counter = UsageCounter::forCurrentMonth($this->user->account_id);

        $this->assertNotNull($counter->id);
        $this->assertEquals($this->user->account_id, $counter->account_id);
        $this->assertEquals(now()->year, $counter->period_year);
        $this->assertEquals(now()->month, $counter->period_month);
    }

    public function test_for_current_month_returns_existing_counter(): void
    {
        $existing = UsageCounter::create([
            'account_id' => $this->user->account_id,
            'period_year' => now()->year,
            'period_month' => now()->month,
            'actions_created' => 50,
            'sms_sent' => 10,
        ]);

        $counter = UsageCounter::forCurrentMonth($this->user->account_id);

        $this->assertEquals($existing->id, $counter->id);
        $this->assertEquals(50, $counter->actions_created);
        $this->assertEquals(10, $counter->sms_sent);
    }

    public function test_increment_actions(): void
    {
        $counter = UsageCounter::forCurrentMonth($this->user->account_id);

        $counter->incrementActions();

        $counter->refresh();
        $this->assertEquals(1, $counter->actions_created);
    }

    public function test_increment_actions_by_count(): void
    {
        $counter = UsageCounter::forCurrentMonth($this->user->account_id);

        $counter->incrementActions(5);

        $counter->refresh();
        $this->assertEquals(5, $counter->actions_created);
    }

    public function test_increment_sms(): void
    {
        $counter = UsageCounter::forCurrentMonth($this->user->account_id);

        $counter->incrementSms();

        $counter->refresh();
        $this->assertEquals(1, $counter->sms_sent);
    }

    public function test_increment_sms_by_count(): void
    {
        $counter = UsageCounter::forCurrentMonth($this->user->account_id);

        $counter->incrementSms(3);

        $counter->refresh();
        $this->assertEquals(3, $counter->sms_sent);
    }

    public function test_get_remaining_actions(): void
    {
        $counter = UsageCounter::forCurrentMonth($this->user->account_id);
        $counter->update(['actions_created' => 30]);

        $remaining = $counter->getRemainingActions(100);

        $this->assertEquals(70, $remaining);
    }

    public function test_get_remaining_actions_returns_zero_when_at_limit(): void
    {
        $counter = UsageCounter::forCurrentMonth($this->user->account_id);
        $counter->update(['actions_created' => 100]);

        $remaining = $counter->getRemainingActions(100);

        $this->assertEquals(0, $remaining);
    }

    public function test_get_remaining_actions_returns_zero_when_over_limit(): void
    {
        $counter = UsageCounter::forCurrentMonth($this->user->account_id);
        $counter->update(['actions_created' => 150]);

        $remaining = $counter->getRemainingActions(100);

        $this->assertEquals(0, $remaining);
    }

    public function test_get_remaining_sms(): void
    {
        $counter = UsageCounter::forCurrentMonth($this->user->account_id);
        $counter->update(['sms_sent' => 5]);

        $remaining = $counter->getRemainingSms(50);

        $this->assertEquals(45, $remaining);
    }

    public function test_get_remaining_sms_returns_zero_when_at_limit(): void
    {
        $counter = UsageCounter::forCurrentMonth($this->user->account_id);
        $counter->update(['sms_sent' => 50]);

        $remaining = $counter->getRemainingSms(50);

        $this->assertEquals(0, $remaining);
    }

    public function test_belongs_to_account(): void
    {
        $counter = UsageCounter::forCurrentMonth($this->user->account_id);

        $this->assertEquals($this->user->account_id, $counter->account->id);
    }

    public function test_default_values_are_zero(): void
    {
        $counter = UsageCounter::forCurrentMonth($this->user->account_id);

        $this->assertEquals(0, $counter->actions_created);
        $this->assertEquals(0, $counter->sms_sent);
    }

    public function test_separate_counters_per_account(): void
    {
        $otherUser = User::factory()->create();

        $counter1 = UsageCounter::forCurrentMonth($this->user->account_id);
        $counter2 = UsageCounter::forCurrentMonth($otherUser->account_id);

        $counter1->incrementActions(10);
        $counter2->incrementActions(5);

        $counter1->refresh();
        $counter2->refresh();

        $this->assertEquals(10, $counter1->actions_created);
        $this->assertEquals(5, $counter2->actions_created);
    }

    public function test_separate_counters_per_month(): void
    {
        // Create counter for current month
        $currentCounter = UsageCounter::forCurrentMonth($this->user->account_id);
        $currentCounter->incrementActions(50);

        // Create counter for previous month
        $previousCounter = UsageCounter::create([
            'account_id' => $this->user->account_id,
            'period_year' => now()->subMonth()->year,
            'period_month' => now()->subMonth()->month,
            'actions_created' => 100,
            'sms_sent' => 20,
        ]);

        // forCurrentMonth should return current month counter
        $retrieved = UsageCounter::forCurrentMonth($this->user->account_id);

        $this->assertEquals($currentCounter->id, $retrieved->id);
        $this->assertEquals(50, $retrieved->actions_created);
    }
}

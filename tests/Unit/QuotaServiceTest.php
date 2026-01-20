<?php

namespace Tests\Unit;

use App\Mail\QuotaWarningMail;
use App\Models\Account;
use App\Models\UsageCounter;
use App\Models\User;
use App\Services\QuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class QuotaServiceTest extends TestCase
{
    use RefreshDatabase;

    private QuotaService $quotaService;

    private User $user;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->quotaService = new QuotaService;
        $this->user = User::factory()->create();
        $this->account = $this->user->account;
        Mail::fake();
    }

    public function test_get_usage_returns_correct_structure(): void
    {
        $usage = $this->quotaService->getUsage($this->account);

        $this->assertArrayHasKey('actions', $usage);
        $this->assertArrayHasKey('sms', $usage);
        $this->assertArrayHasKey('used', $usage['actions']);
        $this->assertArrayHasKey('limit', $usage['actions']);
        $this->assertArrayHasKey('percentage', $usage['actions']);
    }

    public function test_get_usage_returns_zero_for_new_account(): void
    {
        $usage = $this->quotaService->getUsage($this->account);

        $this->assertEquals(0, $usage['actions']['used']);
        $this->assertEquals(0, $usage['sms']['used']);
    }

    public function test_get_usage_reflects_actual_usage(): void
    {
        $counter = UsageCounter::forCurrentMonth($this->account->id);
        $counter->incrementActions(5);
        $counter->incrementSms(2);

        $usage = $this->quotaService->getUsage($this->account);

        $this->assertEquals(5, $usage['actions']['used']);
        $this->assertEquals(2, $usage['sms']['used']);
    }

    public function test_can_create_action_returns_true_when_under_limit(): void
    {
        $this->assertTrue($this->quotaService->canCreateAction($this->account));
    }

    public function test_can_create_action_returns_false_when_at_limit(): void
    {
        $counter = UsageCounter::forCurrentMonth($this->account->id);
        $limit = $this->account->getPlanLimit('max_actions_per_month');
        $counter->update(['actions_created' => $limit]);

        $this->assertFalse($this->quotaService->canCreateAction($this->account));
    }

    public function test_can_send_sms_returns_false_for_free_plan(): void
    {
        // Free plan has 0 SMS
        $this->assertFalse($this->quotaService->canSendSms($this->account));
    }

    public function test_get_remaining_actions(): void
    {
        $counter = UsageCounter::forCurrentMonth($this->account->id);
        $counter->incrementActions(10);

        $limit = $this->account->getPlanLimit('max_actions_per_month');
        $remaining = $this->quotaService->getRemainingActions($this->account);

        $this->assertEquals($limit - 10, $remaining);
    }

    public function test_record_action_created_increments_counter(): void
    {
        $this->quotaService->recordActionCreated($this->account);

        $counter = UsageCounter::forCurrentMonth($this->account->id);
        $this->assertEquals(1, $counter->actions_created);
    }

    public function test_record_sms_sent_increments_counter(): void
    {
        $this->quotaService->recordSmsSent($this->account, 3);

        $counter = UsageCounter::forCurrentMonth($this->account->id);
        $this->assertEquals(3, $counter->sms_sent);
    }

    public function test_warning_sent_at_80_percent(): void
    {
        $limit = $this->account->getPlanLimit('max_actions_per_month');
        $counter = UsageCounter::forCurrentMonth($this->account->id);
        $counter->update(['actions_created' => (int) ($limit * 0.8)]);

        $this->quotaService->checkAndSendWarning($this->account);

        Mail::assertQueued(QuotaWarningMail::class);
    }

    public function test_warning_not_sent_below_80_percent(): void
    {
        $limit = $this->account->getPlanLimit('max_actions_per_month');
        $counter = UsageCounter::forCurrentMonth($this->account->id);
        $counter->update(['actions_created' => (int) ($limit * 0.5)]);

        $this->quotaService->checkAndSendWarning($this->account);

        Mail::assertNotQueued(QuotaWarningMail::class);
    }

    public function test_warning_only_sent_once_per_month(): void
    {
        $limit = $this->account->getPlanLimit('max_actions_per_month');
        $counter = UsageCounter::forCurrentMonth($this->account->id);
        $counter->update(['actions_created' => (int) ($limit * 0.9)]);

        // First check - should send warning
        $this->quotaService->checkAndSendWarning($this->account);
        Mail::assertQueued(QuotaWarningMail::class, 1);

        // Refresh account with fresh owner relationship to pick up updated quota_warning_sent_at
        $this->account = $this->account->fresh(['owner']);

        // Second check - should not send again
        $this->quotaService->checkAndSendWarning($this->account);
        Mail::assertQueued(QuotaWarningMail::class, 1); // Still only 1
    }

    public function test_percentage_calculation(): void
    {
        $counter = UsageCounter::forCurrentMonth($this->account->id);
        $counter->update(['actions_created' => 50]);

        $usage = $this->quotaService->getUsage($this->account);
        $limit = $this->account->getPlanLimit('max_actions_per_month');
        $expectedPercentage = round((50 / $limit) * 100, 1);

        $this->assertEquals($expectedPercentage, $usage['actions']['percentage']);
    }
}

<?php

namespace App\Services;

use App\Mail\QuotaWarningMail;
use App\Models\Account;
use App\Models\UsageCounter;
use Illuminate\Support\Facades\Mail;

class QuotaService
{
    public const WARNING_THRESHOLD = 80; // 80%

    /**
     * Get current usage for an account.
     *
     * @return array{actions: array{used: int, limit: int, percentage: float}, sms: array{used: int, limit: int, percentage: float}}
     */
    public function getUsage(Account $account): array
    {
        $counter = UsageCounter::forCurrentMonth($account->id);
        $limits = $account->getPlanLimits();

        return [
            'actions' => [
                'used' => $counter->actions_created,
                'limit' => $limits['max_actions_per_month'],
                'percentage' => $this->calculatePercentage($counter->actions_created, $limits['max_actions_per_month']),
            ],
            'sms' => [
                'used' => $counter->sms_sent,
                'limit' => $limits['sms_per_month'],
                'percentage' => $this->calculatePercentage($counter->sms_sent, $limits['sms_per_month']),
            ],
        ];
    }

    /**
     * Check if action creation is within quota.
     */
    public function canCreateAction(Account $account): bool
    {
        $counter = UsageCounter::forCurrentMonth($account->id);
        $limit = $account->getPlanLimit('max_actions_per_month');

        return $counter->actions_created < $limit;
    }

    /**
     * Get remaining actions for this month.
     */
    public function getRemainingActions(Account $account): int
    {
        $counter = UsageCounter::forCurrentMonth($account->id);
        $limit = $account->getPlanLimit('max_actions_per_month');

        return max(0, $limit - $counter->actions_created);
    }

    /**
     * Check if SMS sending is within quota.
     */
    public function canSendSms(Account $account, int $count = 1): bool
    {
        $limit = $account->getPlanLimit('sms_per_month');

        // If plan doesn't allow SMS at all
        if ($limit === 0) {
            return false;
        }

        $counter = UsageCounter::forCurrentMonth($account->id);

        return ($counter->sms_sent + $count) <= $limit;
    }

    /**
     * Get remaining SMS quota.
     */
    public function getRemainingSms(Account $account): int
    {
        $counter = UsageCounter::forCurrentMonth($account->id);
        $limit = $account->getPlanLimit('sms_per_month');

        return max(0, $limit - $counter->sms_sent);
    }

    /**
     * Record action creation and check for warnings.
     */
    public function recordActionCreated(Account $account): void
    {
        $counter = UsageCounter::forCurrentMonth($account->id);
        $counter->incrementActions();

        $this->checkAndSendWarning($account);
    }

    /**
     * Record SMS sent (called at send time, not creation time).
     */
    public function recordSmsSent(Account $account, int $count = 1): void
    {
        $counter = UsageCounter::forCurrentMonth($account->id);
        $counter->incrementSms($count);

        $this->checkAndSendWarning($account);
    }

    /**
     * Check if 80% warning should be sent.
     */
    public function checkAndSendWarning(Account $account): void
    {
        $owner = $account->owner;

        if (! $owner || ! $owner->email) {
            return;
        }

        // Already sent warning this month?
        if ($owner->quota_warning_sent_at && $owner->quota_warning_sent_at->isCurrentMonth()) {
            return;
        }

        $usage = $this->getUsage($account);

        // Check if any limit is at 80%
        $shouldWarn = $usage['actions']['percentage'] >= self::WARNING_THRESHOLD ||
                      $usage['sms']['percentage'] >= self::WARNING_THRESHOLD;

        if ($shouldWarn) {
            Mail::to($owner->email)->queue(new QuotaWarningMail($account, $usage));
            $owner->update(['quota_warning_sent_at' => now()]);
        }
    }

    /**
     * Calculate percentage.
     */
    private function calculatePercentage(int $used, int $limit): float
    {
        if ($limit === 0) {
            return 0;
        }

        return round(($used / $limit) * 100, 1);
    }
}

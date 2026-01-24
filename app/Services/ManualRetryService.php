<?php

namespace App\Services;

use App\Exceptions\RetryNotAllowedException;
use App\Jobs\DeliverHttpAction;
use App\Jobs\DeliverReminder;
use App\Models\ExecutionCycle;
use App\Models\ScheduledAction;
use App\Models\User;

class ManualRetryService
{
    public const MAX_MANUAL_RETRIES_PER_HOUR = 3;

    public function __construct(
        private QuotaService $quotaService
    ) {}

    /**
     * Check if manual retry is allowed.
     *
     * @return array{allowed: bool, reasons: string[]}
     */
    public function canRetry(ScheduledAction $action, User $user): array
    {
        $reasons = [];

        // 1. Only failed actions can be retried
        if ($action->resolution_status !== ScheduledAction::STATUS_FAILED) {
            $reasons[] = 'Only failed actions can be retried';
        }

        // 2. Rate limit: max 3 per hour
        $retriesInLastHour = $this->getRetriesInLastHour($action);
        if ($retriesInLastHour >= self::MAX_MANUAL_RETRIES_PER_HOUR) {
            $reasons[] = 'Maximum 3 manual retries per hour exceeded. Please wait before retrying again.';
        }

        // 3. Check action quota
        $account = $action->account;
        if ($account && ! $this->quotaService->canCreateAction($account)) {
            $reasons[] = 'Monthly action quota exceeded. Upgrade your plan to retry.';
        }

        // 4. Check SMS quota for gated actions with phone recipients
        if ($action->isGated() && $account) {
            $smsCount = $this->countSmsRecipients($action);
            if ($smsCount > 0 && ! $this->quotaService->canSendSms($account, $smsCount)) {
                $remaining = $this->quotaService->getRemainingSms($account);
                $reasons[] = "SMS quota exceeded. You have {$remaining} SMS remaining, but this reminder requires {$smsCount}.";
            }
        }

        return [
            'allowed' => empty($reasons),
            'reasons' => $reasons,
        ];
    }

    /**
     * Perform manual retry.
     *
     * @throws RetryNotAllowedException
     */
    public function retry(ScheduledAction $action, User $user): ExecutionCycle
    {
        $check = $this->canRetry($action, $user);

        if (! $check['allowed']) {
            throw new RetryNotAllowedException(implode('; ', $check['reasons']));
        }

        // Get next cycle number
        $lastCycle = ExecutionCycle::where('action_id', $action->id)
            ->orderBy('cycle_number', 'desc')
            ->first();
        $nextCycleNumber = $lastCycle ? $lastCycle->cycle_number + 1 : 1;

        // Create new execution cycle
        $cycle = ExecutionCycle::create([
            'action_id' => $action->id,
            'cycle_number' => $nextCycleNumber,
            'triggered_by' => ExecutionCycle::TRIGGERED_MANUAL,
            'triggered_by_user_id' => $user->id,
            'started_at' => now(),
            'result' => ExecutionCycle::RESULT_IN_PROGRESS,
        ]);

        // Update manual retry tracking
        $retriesInLastHour = $this->getRetriesInLastHour($action);

        // Reset action state for re-execution
        $action->update([
            'resolution_status' => ScheduledAction::STATUS_RESOLVED,
            'current_execution_cycle_id' => $cycle->id,
            'execute_at_utc' => now(),
            'next_retry_at' => null,
            'attempt_count' => 0,  // Reset attempts for this cycle
            'failure_reason' => null,
            'manual_retry_count' => $retriesInLastHour + 1,
            'last_manual_retry_at' => now(),
        ]);

        // Increment usage counter (counts as new action execution)
        if ($action->account) {
            $this->quotaService->recordActionCreated($action->account);
        }

        // Dispatch appropriate job immediately
        $action->markAsExecuting();

        if ($action->isImmediate()) {
            DeliverHttpAction::dispatch($action);
        } else {
            DeliverReminder::dispatch($action);
        }

        return $cycle;
    }

    /**
     * Count retries in the last hour.
     */
    private function getRetriesInLastHour(ScheduledAction $action): int
    {
        if (! $action->last_manual_retry_at) {
            return 0;
        }

        // If last retry was more than an hour ago, reset count
        if ($action->last_manual_retry_at->diffInMinutes(now()) >= 60) {
            return 0;
        }

        return $action->manual_retry_count;
    }

    /**
     * Count SMS recipients in a reminder action.
     */
    private function countSmsRecipients(ScheduledAction $action): int
    {
        $gate = $action->gate ?? [];
        $recipients = $gate['recipients'] ?? [];
        $count = 0;

        foreach ($recipients as $recipient) {
            if ($this->isPhoneNumber($recipient)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Check if a value is a phone number.
     */
    private function isPhoneNumber(string $value): bool
    {
        return preg_match('/^\+?[\d\s\-\(\)]+$/', $value) === 1 &&
               strlen(preg_replace('/\D/', '', $value)) >= 10;
    }
}

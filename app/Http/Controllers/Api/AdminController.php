<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\DeliveryAttempt;
use App\Models\ReminderEvent;
use App\Models\ReminderRecipient;
use App\Models\ScheduledAction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class AdminController extends Controller
{
    /**
     * Get overview stats (counters).
     */
    public function overview(): JsonResponse
    {
        $now = now();

        // User stats
        $users = [
            'total' => User::count(),
            'last_7_days' => User::where('created_at', '>=', $now->copy()->subDays(7))->count(),
            'last_15_days' => User::where('created_at', '>=', $now->copy()->subDays(15))->count(),
            'last_30_days' => User::where('created_at', '>=', $now->copy()->subDays(30))->count(),
        ];

        // Subscription stats (by plan)
        $subscriptions = $this->getSubscriptionStats();

        // Action stats
        $actions = [
            'total' => ScheduledAction::count(),
            'immediate' => ScheduledAction::where('mode', 'immediate')->count(),
            'gated' => ScheduledAction::where('mode', 'gated')->count(),
            'gated_with_request' => ScheduledAction::where('mode', 'gated')
                ->whereNotNull('request')
                ->count(),
            'executed' => ScheduledAction::where('resolution_status', 'executed')->count(),
            'failed' => ScheduledAction::where('resolution_status', 'failed')->count(),
            'cancelled' => ScheduledAction::where('resolution_status', 'cancelled')->count(),
            'pending' => ScheduledAction::where('resolution_status', 'resolved')->count(),
        ];

        // Calculate failure rate
        $attempted = $actions['executed'] + $actions['failed'];
        $actions['failure_rate'] = $attempted > 0
            ? round(($actions['failed'] / $attempted) * 100, 2)
            : 0;

        // Reminder stats
        $reminders = [
            'sent' => ReminderEvent::where('event_type', 'sent')->count(),
            'confirmed' => ReminderEvent::where('event_type', 'confirmed')->count(),
            'declined' => ReminderEvent::where('event_type', 'declined')->count(),
            'snoozed' => ReminderEvent::where('event_type', 'snoozed')->count(),
            'escalated' => ReminderEvent::where('event_type', 'escalated')->count(),
        ];

        // Messaging stats (SMS vs Email)
        $messaging = $this->getMessagingStats($now);

        return response()->json([
            'users' => $users,
            'subscriptions' => $subscriptions,
            'actions' => $actions,
            'reminders' => $reminders,
            'messaging' => $messaging,
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get daily trends for the last 30 days.
     */
    public function trends(): JsonResponse
    {
        $days = 30;
        $startDate = now()->subDays($days)->startOfDay();

        // Actions created per day
        $actionsPerDay = ScheduledAction::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as count')
        )
            ->where('created_at', '>=', $startDate)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date')
            ->toArray();

        // Failures per day
        $failuresPerDay = ScheduledAction::select(
            DB::raw('DATE(updated_at) as date'),
            DB::raw('COUNT(*) as count')
        )
            ->where('resolution_status', 'failed')
            ->where('updated_at', '>=', $startDate)
            ->groupBy(DB::raw('DATE(updated_at)'))
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date')
            ->toArray();

        // New users per day
        $usersPerDay = User::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as count')
        )
            ->where('created_at', '>=', $startDate)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date')
            ->toArray();

        // Reminder events per day
        $remindersSentPerDay = ReminderEvent::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as count')
        )
            ->where('event_type', 'sent')
            ->where('created_at', '>=', $startDate)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date')
            ->toArray();

        // Build date range with zeros for missing dates
        $dates = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $dates[] = [
                'date' => $date,
                'actions' => $actionsPerDay[$date] ?? 0,
                'failures' => $failuresPerDay[$date] ?? 0,
                'users' => $usersPerDay[$date] ?? 0,
                'reminders_sent' => $remindersSentPerDay[$date] ?? 0,
            ];
        }

        return response()->json([
            'days' => $days,
            'data' => $dates,
        ]);
    }

    /**
     * Get operational health signals.
     */
    public function health(): JsonResponse
    {
        $now = now();
        $warnings = [];
        $errors = [];

        // Actions stuck in resolved state (past due by 5+ minutes)
        $stuckResolved = ScheduledAction::where('resolution_status', ScheduledAction::STATUS_RESOLVED)
            ->where('execute_at_utc', '<', $now->copy()->subMinutes(5))
            ->count();

        if ($stuckResolved > 10) {
            $errors[] = [
                'type' => 'stuck_resolved',
                'message' => "{$stuckResolved} actions are stuck in resolved state (past due > 5 min)",
                'count' => $stuckResolved,
            ];
        } elseif ($stuckResolved > 0) {
            $warnings[] = [
                'type' => 'stuck_resolved',
                'message' => "{$stuckResolved} actions are stuck in resolved state",
                'count' => $stuckResolved,
            ];
        }

        // Actions stuck in EXECUTING state (> 10 minutes indicates worker crash)
        $stuckExecuting = ScheduledAction::where('resolution_status', ScheduledAction::STATUS_EXECUTING)
            ->where('updated_at', '<', $now->copy()->subMinutes(10))
            ->count();

        if ($stuckExecuting > 0) {
            $errors[] = [
                'type' => 'stuck_executing',
                'message' => "{$stuckExecuting} actions stuck in executing state (possible worker crash)",
                'count' => $stuckExecuting,
            ];
        }

        // Actions stuck in awaiting_response for too long (> 24 hours)
        $stuckAwaiting = ScheduledAction::where('resolution_status', ScheduledAction::STATUS_AWAITING_RESPONSE)
            ->where('updated_at', '<', $now->copy()->subHours(24))
            ->count();

        if ($stuckAwaiting > 0) {
            $warnings[] = [
                'type' => 'stuck_awaiting',
                'message' => "{$stuckAwaiting} reminders awaiting response for > 24 hours",
                'count' => $stuckAwaiting,
            ];
        }

        // High retry count actions (> 3 attempts, still not resolved)
        $highRetry = ScheduledAction::where('resolution_status', ScheduledAction::STATUS_RESOLVED)
            ->where('attempt_count', '>', 3)
            ->count();

        if ($highRetry > 0) {
            $warnings[] = [
                'type' => 'high_retry',
                'message' => "{$highRetry} actions have > 3 retry attempts",
                'count' => $highRetry,
            ];
        }

        // Failure rate in last hour - only count DELIVERY_ERROR (infrastructure issues)
        // Customer errors (4xx/5xx from their endpoints) don't indicate platform problems
        $deliveryAttempts = DeliveryAttempt::where('created_at', '>=', $now->copy()->subHour())
            ->selectRaw('failure_category, COUNT(*) as count')
            ->groupBy('failure_category')
            ->pluck('count', 'failure_category');

        $lastHourTotal = $deliveryAttempts->sum();
        $deliveryErrors = $deliveryAttempts->get(DeliveryAttempt::CATEGORY_DELIVERY_ERROR, 0);
        $customer4xx = $deliveryAttempts->get(DeliveryAttempt::CATEGORY_CUSTOMER_4XX, 0);
        $customer5xx = $deliveryAttempts->get(DeliveryAttempt::CATEGORY_CUSTOMER_5XX, 0);

        // Platform failure rate only counts infrastructure/delivery errors
        $lastHourFailureRate = $lastHourTotal > 0
            ? round(($deliveryErrors / $lastHourTotal) * 100, 2)
            : 0;

        if ($lastHourFailureRate > 10) {
            $errors[] = [
                'type' => 'high_failure_rate',
                'message' => "Platform failure rate in last hour: {$lastHourFailureRate}%",
                'rate' => $lastHourFailureRate,
            ];
        } elseif ($lastHourFailureRate > 5) {
            $warnings[] = [
                'type' => 'elevated_failure_rate',
                'message' => "Platform failure rate in last hour: {$lastHourFailureRate}%",
                'rate' => $lastHourFailureRate,
            ];
        }

        // Warn about high customer error rates (for visibility, not platform health)
        $customerErrorRate = $lastHourTotal > 0
            ? round((($customer4xx + $customer5xx) / $lastHourTotal) * 100, 2)
            : 0;

        // Determine overall status
        $status = 'healthy';
        if (count($errors) > 0) {
            $status = 'critical';
        } elseif (count($warnings) > 0) {
            $status = 'warning';
        }

        return response()->json([
            'status' => $status,
            'errors' => $errors,
            'warnings' => $warnings,
            'metrics' => [
                'stuck_resolved' => $stuckResolved,
                'stuck_executing' => $stuckExecuting,
                'stuck_awaiting' => $stuckAwaiting,
                'high_retry_count' => $highRetry,
                // Platform failure rate (only infrastructure errors, not customer endpoint errors)
                'last_hour_failure_rate' => $lastHourFailureRate,
                // Customer endpoint errors (for visibility only, not platform health)
                'last_hour_customer_error_rate' => $customerErrorRate,
                'last_hour_delivery_attempts' => $lastHourTotal,
                'last_hour_delivery_errors' => $deliveryErrors,
                'last_hour_customer_4xx' => $customer4xx,
                'last_hour_customer_5xx' => $customer5xx,
            ],
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get queue health status.
     */
    public function queue(): JsonResponse
    {
        $queueStats = [
            'pending' => 0,
            'failed_last_hour' => 0,
            'connection' => 'unknown',
        ];

        try {
            // Check Redis connection
            Redis::ping();
            $queueStats['connection'] = 'connected';

            // Get pending jobs count (Redis queue)
            $queueStats['pending'] = Redis::llen('queues:default') ?: 0;

            // Get failed jobs from database
            $queueStats['failed_last_hour'] = DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subHour())
                ->count();
        } catch (\Exception $e) {
            $queueStats['connection'] = 'error';
            $queueStats['error'] = $e->getMessage();
        }

        // Determine queue health status
        $status = 'healthy';
        if ($queueStats['connection'] !== 'connected') {
            $status = 'critical';
        } elseif ($queueStats['pending'] > 100 || $queueStats['failed_last_hour'] > 10) {
            $status = 'warning';
        }

        return response()->json([
            'status' => $status,
            'stats' => $queueStats,
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get list of users with their account and subscription info.
     */
    public function users(): JsonResponse
    {
        $users = User::with(['account.subscriptions'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($user) {
                $account = $user->account;

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_admin' => $user->is_admin,
                    'email_verified_at' => $user->email_verified_at,
                    'created_at' => $user->created_at,
                    'account' => $account ? [
                        'id' => $account->id,
                        'name' => $account->name,
                        'is_owner' => $account->owner_id === $user->id,
                        'manual_plan' => $account->manual_plan,
                        'manual_plan_expires_at' => $account->manual_plan_expires_at,
                        'manual_plan_reason' => $account->manual_plan_reason,
                    ] : null,
                    'plan' => $account?->getPlan() ?? 'free',
                    'is_manually_managed' => $account?->isPlanManuallyManaged() ?? false,
                    'actions_count' => $account ? ScheduledAction::where('account_id', $account->id)->count() : 0,
                ];
            });

        return response()->json([
            'users' => $users,
            'total' => $users->count(),
        ]);
    }

    /**
     * Set manual plan for an account.
     */
    public function setManualPlan(Request $request, Account $account): JsonResponse
    {
        $validated = $request->validate([
            'plan' => 'nullable|in:pro,business',
            'expires_at' => 'nullable|date|after:now',
            'reason' => 'nullable|string|max:255',
        ]);

        $admin = $request->user();

        if ($validated['plan']) {
            // Set manual plan
            $account->setManualPlan(
                $validated['plan'],
                $validated['expires_at'] ?? null,
                $validated['reason'] ?? null,
                $admin
            );

            return response()->json([
                'message' => "Manual plan set to {$validated['plan']}",
                'account' => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'plan' => $account->getPlan(),
                    'manual_plan' => $account->manual_plan,
                    'manual_plan_expires_at' => $account->manual_plan_expires_at,
                    'manual_plan_reason' => $account->manual_plan_reason,
                    'is_manually_managed' => $account->isPlanManuallyManaged(),
                ],
            ]);
        } else {
            // Revoke manual plan
            $account->revokeManualPlan($validated['reason'] ?? null, $admin);

            return response()->json([
                'message' => 'Manual plan revoked',
                'account' => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'plan' => $account->getPlan(),
                    'manual_plan' => null,
                    'manual_plan_expires_at' => null,
                    'manual_plan_reason' => null,
                    'is_manually_managed' => false,
                ],
            ]);
        }
    }

    /**
     * Get plan override history for an account.
     */
    public function getPlanOverrides(Account $account): JsonResponse
    {
        $overrides = $account->planOverrides()
            ->with('setBy:id,name,email')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'account_id' => $account->id,
            'overrides' => $overrides,
        ]);
    }

    /**
     * Get user's plan based on subscription.
     *
     * @phpstan-ignore method.unused
     */
    private function getUserPlan($subscription): string
    {
        if (! $subscription) {
            return 'free';
        }

        $proPrices = array_filter([
            config('services.stripe.prices.pro_monthly'),
            config('services.stripe.prices.pro_annual'),
        ]);
        $businessPrices = array_filter([
            config('services.stripe.prices.business_monthly'),
            config('services.stripe.prices.business_annual'),
        ]);

        if (in_array($subscription->stripe_price, $businessPrices)) {
            return 'business';
        }
        if (in_array($subscription->stripe_price, $proPrices)) {
            return 'pro';
        }

        return 'free';
    }

    /**
     * Get subscription stats by plan.
     *
     * @return array<string, int>
     */
    private function getSubscriptionStats(): array
    {
        $stats = [
            'free' => 0,
            'pro' => 0,
            'business' => 0,
            'total_paying' => 0,
        ];

        // Count accounts without active subscriptions (free tier)
        // Billable trait is on Account, not User
        $stats['free'] = Account::whereDoesntHave('subscriptions', function ($query) {
            $query->where('stripe_status', 'active');
        })->count();

        // Count by plan (requires checking stripe_price against config)
        $proPriceMonthly = config('services.stripe.prices.pro_monthly');
        $proPriceAnnual = config('services.stripe.prices.pro_annual');
        $businessPriceMonthly = config('services.stripe.prices.business_monthly');
        $businessPriceAnnual = config('services.stripe.prices.business_annual');

        $proPrices = array_filter([$proPriceMonthly, $proPriceAnnual]);
        $businessPrices = array_filter([$businessPriceMonthly, $businessPriceAnnual]);

        if (! empty($proPrices)) {
            $stats['pro'] = DB::table('subscriptions')
                ->where('stripe_status', 'active')
                ->whereIn('stripe_price', $proPrices)
                ->count();
        }

        if (! empty($businessPrices)) {
            $stats['business'] = DB::table('subscriptions')
                ->where('stripe_status', 'active')
                ->whereIn('stripe_price', $businessPrices)
                ->count();
        }

        $stats['total_paying'] = $stats['pro'] + $stats['business'];

        return $stats;
    }

    /**
     * Get messaging statistics (SMS and Email).
     */
    private function getMessagingStats(\Carbon\Carbon $now): array
    {
        // Get all recipients and categorize in PHP for database compatibility
        // (REGEXP syntax differs between MySQL, PostgreSQL, and SQLite)
        $recipients = ReminderRecipient::all();

        $categorized = $recipients->map(function ($r) {
            return [
                'channel' => $this->isPhoneNumber($r->email) ? 'sms' : 'email',
                'status' => $r->status,
                'created_at' => $r->created_at,
            ];
        });

        $last24h = $now->copy()->subHours(24);
        $last7d = $now->copy()->subDays(7);
        $last30d = $now->copy()->subDays(30);

        // Email stats
        $emails = $categorized->where('channel', 'email');
        $emailStats = [
            'total' => $emails->count(),
            'last_24h' => $emails->where('created_at', '>=', $last24h)->count(),
            'last_7d' => $emails->where('created_at', '>=', $last7d)->count(),
            'last_30d' => $emails->where('created_at', '>=', $last30d)->count(),
            'sent' => $emails->where('status', ReminderRecipient::STATUS_SENT)->count(),
            'confirmed' => $emails->where('status', ReminderRecipient::STATUS_CONFIRMED)->count(),
            'declined' => $emails->where('status', ReminderRecipient::STATUS_DECLINED)->count(),
            'snoozed' => $emails->where('status', ReminderRecipient::STATUS_SNOOZED)->count(),
            'blocked' => $emails->where('status', ReminderRecipient::STATUS_BLOCKED)->count(),
        ];

        // SMS stats
        $sms = $categorized->where('channel', 'sms');
        $smsStats = [
            'total' => $sms->count(),
            'last_24h' => $sms->where('created_at', '>=', $last24h)->count(),
            'last_7d' => $sms->where('created_at', '>=', $last7d)->count(),
            'last_30d' => $sms->where('created_at', '>=', $last30d)->count(),
            'sent' => $sms->where('status', ReminderRecipient::STATUS_SENT)->count(),
            'confirmed' => $sms->where('status', ReminderRecipient::STATUS_CONFIRMED)->count(),
            'declined' => $sms->where('status', ReminderRecipient::STATUS_DECLINED)->count(),
            'snoozed' => $sms->where('status', ReminderRecipient::STATUS_SNOOZED)->count(),
            'blocked' => $sms->where('status', ReminderRecipient::STATUS_BLOCKED)->count(),
        ];

        return [
            'email' => $emailStats,
            'sms' => $smsStats,
        ];
    }

    /**
     * Check if a string is a phone number.
     */
    private function isPhoneNumber(string $value): bool
    {
        // Phone numbers start with + or contain only digits, spaces, dashes, parentheses
        $cleaned = preg_replace('/[\s\-\(\)]+/', '', $value);

        return str_starts_with($value, '+') || preg_match('/^\d{7,15}$/', $cleaned);
    }
}

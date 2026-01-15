<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\ReminderEvent;
use App\Models\ScheduledAction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
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
            'http' => ScheduledAction::where('type', 'http')->count(),
            'reminder' => ScheduledAction::where('type', 'reminder')->count(),
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

        return response()->json([
            'users' => $users,
            'subscriptions' => $subscriptions,
            'actions' => $actions,
            'reminders' => $reminders,
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

        // Failure rate in last hour
        $lastHourTotal = ScheduledAction::where('updated_at', '>=', $now->copy()->subHour())
            ->whereIn('resolution_status', ['executed', 'failed'])
            ->count();

        $lastHourFailed = ScheduledAction::where('updated_at', '>=', $now->copy()->subHour())
            ->where('resolution_status', 'failed')
            ->count();

        $lastHourFailureRate = $lastHourTotal > 0
            ? round(($lastHourFailed / $lastHourTotal) * 100, 2)
            : 0;

        if ($lastHourFailureRate > 10) {
            $errors[] = [
                'type' => 'high_failure_rate',
                'message' => "Failure rate in last hour: {$lastHourFailureRate}%",
                'rate' => $lastHourFailureRate,
            ];
        } elseif ($lastHourFailureRate > 5) {
            $warnings[] = [
                'type' => 'elevated_failure_rate',
                'message' => "Failure rate in last hour: {$lastHourFailureRate}%",
                'rate' => $lastHourFailureRate,
            ];
        }

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
                'last_hour_failure_rate' => $lastHourFailureRate,
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

        if (!empty($proPrices)) {
            $stats['pro'] = DB::table('subscriptions')
                ->where('stripe_status', 'active')
                ->whereIn('stripe_price', $proPrices)
                ->count();
        }

        if (!empty($businessPrices)) {
            $stats['business'] = DB::table('subscriptions')
                ->where('stripe_status', 'active')
                ->whereIn('stripe_price', $businessPrices)
                ->count();
        }

        $stats['total_paying'] = $stats['pro'] + $stats['business'];

        return $stats;
    }
}

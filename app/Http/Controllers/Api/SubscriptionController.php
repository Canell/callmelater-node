<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScheduledAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /**
     * Get the current user's subscription status.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $limits = $user->getPlanLimits();

        // Get usage stats for current month
        $startOfMonth = now()->startOfMonth();
        $actionsThisMonth = $user->actions()->where('created_at', '>=', $startOfMonth)->count();
        $executionsThisMonth = $user->actions()
            ->where('resolution_status', ScheduledAction::STATUS_EXECUTED)
            ->where('executed_at_utc', '>=', $startOfMonth)
            ->count();
        $remindersThisMonth = $user->actions()
            ->where('type', ScheduledAction::TYPE_REMINDER)
            ->where('created_at', '>=', $startOfMonth)
            ->count();

        return response()->json([
            'subscribed' => $user->subscribed('default'),
            'plan' => $user->getPlan(),
            'on_trial' => $user->onTrial('default'),
            'canceled' => $user->subscription('default')?->canceled() ?? false,
            'ends_at' => $user->subscription('default')?->ends_at,
            'limits' => [
                'actions_per_month' => $limits['max_actions_per_month'] ?? null,
                'active_actions' => $limits['max_pending_actions'] ?? null,
                'max_attempts' => $limits['max_retries'] ?? 3,
                'recipients_per_reminder' => $limits['max_recipients'] ?? 5,
                'new_recipients_per_day' => $limits['new_recipients_per_day'] ?? 5,
                'history_days' => $limits['history_days'] ?? 365,
            ],
            'usage' => [
                'actions_this_month' => $actionsThisMonth,
                'executions_this_month' => $executionsThisMonth,
                'reminders_this_month' => $remindersThisMonth,
            ],
        ]);
    }

    /**
     * Create a Stripe Checkout session for subscribing.
     */
    public function checkout(Request $request): JsonResponse
    {
        $request->validate([
            'plan' => 'required|in:pro,business',
            'billing' => 'sometimes|in:monthly,annual',
        ]);

        $user = $request->user();
        $billing = $request->input('billing', 'monthly');
        $priceId = $this->getPriceId($request->input('plan'), $billing);

        if (! $priceId) {
            return response()->json(['error' => 'Invalid plan or billing period'], 400);
        }

        $checkout = $user->newSubscription('default', $priceId)
            ->checkout([
                'success_url' => config('app.url').'/dashboard?subscription=success',
                'cancel_url' => config('app.url').'/pricing?subscription=cancelled',
            ]);

        /** @var string $url */
        $url = $checkout->asStripeCheckoutSession()->url;

        return response()->json([
            'checkout_url' => $url,
        ]);
    }

    /**
     * Create a billing portal session for managing subscription.
     */
    public function portal(Request $request): JsonResponse
    {
        $user = $request->user();

        $url = $user->billingPortalUrl(
            config('app.url') . '/dashboard'
        );

        return response()->json([
            'portal_url' => $url,
        ]);
    }

    /**
     * Cancel the current subscription.
     */
    public function cancel(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->subscribed('default')) {
            return response()->json(['error' => 'No active subscription'], 400);
        }

        $user->subscription('default')->cancel();

        return response()->json([
            'message' => 'Subscription cancelled',
            'ends_at' => $user->subscription('default')->ends_at,
        ]);
    }

    /**
     * Resume a cancelled subscription.
     */
    public function resume(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->subscription('default');

        if (! $subscription || ! $subscription->canceled()) {
            return response()->json(['error' => 'No canceled subscription to resume'], 400);
        }

        $subscription->resume();

        return response()->json([
            'message' => 'Subscription resumed',
        ]);
    }

    /**
     * Get the Stripe price ID for a plan and billing period.
     */
    private function getPriceId(string $plan, string $billing = 'monthly'): ?string
    {
        $key = "{$plan}_{$billing}";

        return config("services.stripe.prices.{$key}");
    }
}

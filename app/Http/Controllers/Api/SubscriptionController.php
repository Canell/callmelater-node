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
        $account = $user->account;
        $limits = $user->getPlanLimits();

        // Get usage stats for current month (account-wide)
        $startOfMonth = now()->startOfMonth();
        $actionsThisMonth = $account->actions()->where('created_at', '>=', $startOfMonth)->count();
        $executionsThisMonth = $account->actions()
            ->where('resolution_status', ScheduledAction::STATUS_EXECUTED)
            ->where('executed_at_utc', '>=', $startOfMonth)
            ->count();
        $remindersThisMonth = $account->actions()
            ->where('type', ScheduledAction::TYPE_REMINDER)
            ->where('created_at', '>=', $startOfMonth)
            ->count();

        // Check if plan is manually managed
        $isManuallyManaged = $account->isPlanManuallyManaged();

        return response()->json([
            'subscribed' => $account->subscribed('default'),
            'plan' => $account->getPlan(),
            'on_trial' => $account->onTrial('default'),
            'canceled' => $account->subscription('default')?->canceled() ?? false,
            'ends_at' => $account->subscription('default')?->ends_at,
            'can_manage_billing' => $user->canManageBilling(),
            // Manual plan override info
            'is_manually_managed' => $isManuallyManaged,
            'manual_plan_expires_at' => $isManuallyManaged ? $account->manual_plan_expires_at : null,
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
        $account = $user->account;

        // Only owner/admin can manage billing
        if (! $user->canManageBilling()) {
            return response()->json(['error' => 'Only account owner or admin can manage billing'], 403);
        }

        $billing = $request->input('billing', 'monthly');
        $priceId = $this->getPriceId($request->input('plan'), $billing);

        if (! $priceId) {
            return response()->json(['error' => 'Invalid plan or billing period'], 400);
        }

        // If already subscribed, swap to the new plan instead of creating a new subscription
        if ($account->subscribed('default')) {
            $subscription = $account->subscription('default');

            // If on the same price, no change needed
            if ($subscription->stripe_price === $priceId) {
                return response()->json(['error' => 'You are already on this plan'], 400);
            }

            // Swap to the new plan (prorated by default)
            $subscription->swap($priceId);

            return response()->json([
                'message' => 'Plan changed successfully',
                'plan' => $request->input('plan'),
            ]);
        }

        // New subscription - use Stripe Checkout
        $plan = $request->input('plan');
        $checkout = $account->newSubscription('default', $priceId)
            ->checkout([
                'success_url' => config('app.url')."/subscription/result?status=subscribed&plan={$plan}",
                'cancel_url' => config('app.url').'/subscription/result?status=cancelled',
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
        $account = $user->account;

        // Only owner/admin can manage billing
        if (! $user->canManageBilling()) {
            return response()->json(['error' => 'Only account owner or admin can manage billing'], 403);
        }

        $url = $account->billingPortalUrl(
            config('app.url').'/dashboard'
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
        $account = $user->account;

        // Only owner/admin can manage billing
        if (! $user->canManageBilling()) {
            return response()->json(['error' => 'Only account owner or admin can manage billing'], 403);
        }

        if (! $account->subscribed('default')) {
            return response()->json(['error' => 'No active subscription'], 400);
        }

        $account->subscription('default')->cancel();

        return response()->json([
            'message' => 'Subscription cancelled',
            'ends_at' => $account->subscription('default')->ends_at,
        ]);
    }

    /**
     * Resume a cancelled subscription.
     */
    public function resume(Request $request): JsonResponse
    {
        $user = $request->user();
        $account = $user->account;

        // Only owner/admin can manage billing
        if (! $user->canManageBilling()) {
            return response()->json(['error' => 'Only account owner or admin can manage billing'], 403);
        }

        $subscription = $account->subscription('default');

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

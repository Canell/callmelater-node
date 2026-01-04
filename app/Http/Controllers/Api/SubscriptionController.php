<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

        return response()->json([
            'subscribed' => $user->subscribed('default'),
            'plan' => $this->getCurrentPlan($user),
            'on_trial' => $user->onTrial('default'),
            'canceled' => $user->subscription('default')?->canceled() ?? false,
            'ends_at' => $user->subscription('default')?->ends_at,
        ]);
    }

    /**
     * Create a Stripe Checkout session for subscribing.
     */
    public function checkout(Request $request): JsonResponse
    {
        $request->validate([
            'plan' => 'required|in:pro,business',
        ]);

        $user = $request->user();
        $priceId = $this->getPriceId($request->input('plan'));

        if (! $priceId) {
            return response()->json(['error' => 'Invalid plan'], 400);
        }

        $checkout = $user->newSubscription('default', $priceId)
            ->checkout([
                'success_url' => config('app.url') . '/dashboard?subscription=success',
                'cancel_url' => config('app.url') . '/pricing?subscription=cancelled',
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
     * Get the Stripe price ID for a plan.
     */
    private function getPriceId(string $plan): ?string
    {
        return match ($plan) {
            'pro' => config('services.stripe.prices.pro'),
            'business' => config('services.stripe.prices.business'),
            default => null,
        };
    }

    /**
     * Determine the current plan name.
     */
    private function getCurrentPlan(mixed $user): string
    {
        if (! $user->subscribed('default')) {
            return 'free';
        }

        $subscription = $user->subscription('default');
        $priceId = $subscription->stripe_price;

        return match ($priceId) {
            config('services.stripe.prices.pro') => 'pro',
            config('services.stripe.prices.business') => 'business',
            default => 'unknown',
        };
    }
}

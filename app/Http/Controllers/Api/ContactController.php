<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'in:general,support,billing,enterprise,feedback'],
            'message' => ['required', 'string', 'max:5000'],
            'recaptcha_token' => ['nullable', 'string'],
        ]);

        // Verify reCAPTCHA if configured
        if (! $this->verifyRecaptcha($validated['recaptcha_token'] ?? null, $request->ip())) {
            return response()->json([
                'message' => 'Security verification failed. Please try again.',
            ], 422);
        }

        // Check if sender is an existing user
        $existingUser = User::where('email', $validated['email'])->first();
        $userInfo = $this->getUserInfo($existingUser);

        // Send email to support
        Mail::raw($this->formatMessage($validated, $userInfo), function ($message) use ($validated) {
            $message->to(env('MAIL_SUPPORT_ADDRESS', 'support@callmelater.io'))
                ->replyTo($validated['email'], $validated['name'])
                ->subject('[CallMeLater Contact] ' . $this->getSubjectLabel($validated['subject']));
        });

        return response()->json([
            'message' => 'Message sent successfully',
        ]);
    }

    private function verifyRecaptcha(?string $token, ?string $ip): bool
    {
        $secretKey = config('services.recaptcha.secret_key');

        // If reCAPTCHA is not configured, allow the request
        if (empty($secretKey)) {
            return true;
        }

        // If no token provided when reCAPTCHA is configured, reject
        if (empty($token)) {
            Log::warning('Contact form submitted without reCAPTCHA token', ['ip' => $ip]);

            return false;
        }

        try {
            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $secretKey,
                'response' => $token,
                'remoteip' => $ip,
            ]);

            $result = $response->json();

            if (! ($result['success'] ?? false)) {
                Log::warning('reCAPTCHA verification failed', [
                    'ip' => $ip,
                    'error_codes' => $result['error-codes'] ?? [],
                ]);

                return false;
            }

            // Check score (0.0 = bot, 1.0 = human)
            $score = $result['score'] ?? 0;
            $minScore = config('services.recaptcha.min_score', 0.5);

            if ($score < $minScore) {
                Log::warning('reCAPTCHA score too low', [
                    'ip' => $ip,
                    'score' => $score,
                    'min_score' => $minScore,
                ]);

                return false;
            }

            // Verify action matches
            if (($result['action'] ?? '') !== 'contact') {
                Log::warning('reCAPTCHA action mismatch', [
                    'ip' => $ip,
                    'action' => $result['action'] ?? 'unknown',
                ]);

                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('reCAPTCHA verification error', [
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);

            // On error, allow the request (fail open) to not block legitimate users
            return true;
        }
    }

    private function getUserInfo(?User $user): array
    {
        if (! $user) {
            return [
                'is_user' => false,
                'plan' => null,
                'account_name' => null,
                'member_since' => null,
                'subscription_status' => null,
                'subscription_ends_at' => null,
            ];
        }

        $account = $user->account;
        $subscription = $account?->subscription('default');

        // Determine subscription status
        $subscriptionStatus = 'None';
        $subscriptionEndsAt = null;

        if ($subscription) {
            if ($subscription->canceled()) {
                $subscriptionStatus = 'Cancelled';
                $subscriptionEndsAt = $subscription->ends_at?->format('M j, Y');
            } elseif ($subscription->onTrial()) {
                $subscriptionStatus = 'Trial';
                $subscriptionEndsAt = $subscription->trial_ends_at?->format('M j, Y');
            } elseif ($subscription->active()) {
                $subscriptionStatus = 'Active';
                // Get current period end from Stripe
                if ($subscription->asStripeSubscription()) {
                    $stripeSubscription = $subscription->asStripeSubscription();
                    $subscriptionEndsAt = date('M j, Y', $stripeSubscription->current_period_end);
                }
            }
        }

        return [
            'is_user' => true,
            'plan' => ucfirst($user->getPlan()),
            'account_name' => $account?->name,
            'member_since' => $user->created_at?->format('M j, Y'),
            'subscription_status' => $subscriptionStatus,
            'subscription_ends_at' => $subscriptionEndsAt,
        ];
    }

    private function formatMessage(array $data, array $userInfo): string
    {
        $subscriptionLine = '';
        if ($userInfo['is_user'] && $userInfo['subscription_status']) {
            $subscriptionLine = "\nSubscription: {$userInfo['subscription_status']}";
            if ($userInfo['subscription_ends_at']) {
                $label = $userInfo['subscription_status'] === 'Cancelled' ? 'Ends' : 'Renews';
                $subscriptionLine .= " ({$label}: {$userInfo['subscription_ends_at']})";
            }
        }

        $userSection = $userInfo['is_user']
            ? "Existing User: Yes\nPlan: {$userInfo['plan']}\nAccount: {$userInfo['account_name']}\nMember Since: {$userInfo['member_since']}{$subscriptionLine}"
            : "Existing User: No";

        return <<<TEXT
New contact form submission:

Name: {$data['name']}
Email: {$data['email']}
Subject: {$this->getSubjectLabel($data['subject'])}

{$userSection}

Message:
{$data['message']}
TEXT;
    }

    private function getSubjectLabel(string $subject): string
    {
        return match ($subject) {
            'general' => 'General Inquiry',
            'support' => 'Technical Support',
            'billing' => 'Billing Question',
            'enterprise' => 'Enterprise Sales',
            'feedback' => 'Feedback',
            default => $subject,
        };
    }
}

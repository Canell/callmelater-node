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
            ];
        }

        return [
            'is_user' => true,
            'plan' => ucfirst($user->getPlan()),
            'account_name' => $user->account?->name,
            'member_since' => $user->created_at?->format('M j, Y'),
        ];
    }

    private function formatMessage(array $data, array $userInfo): string
    {
        $userSection = $userInfo['is_user']
            ? "Existing User: Yes\nPlan: {$userInfo['plan']}\nAccount: {$userInfo['account_name']}\nMember Since: {$userInfo['member_since']}"
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

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        ]);

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

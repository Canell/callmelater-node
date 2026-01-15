<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

        // Send email to support
        Mail::raw($this->formatMessage($validated), function ($message) use ($validated) {
            $message->to(config('mail.from.address'))
                ->replyTo($validated['email'], $validated['name'])
                ->subject('[CallMeLater Contact] ' . $this->getSubjectLabel($validated['subject']));
        });

        return response()->json([
            'message' => 'Message sent successfully',
        ]);
    }

    private function formatMessage(array $data): string
    {
        return <<<TEXT
New contact form submission:

Name: {$data['name']}
Email: {$data['email']}
Subject: {$this->getSubjectLabel($data['subject'])}

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

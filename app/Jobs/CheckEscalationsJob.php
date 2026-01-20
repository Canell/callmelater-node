<?php

namespace App\Jobs;

use App\Mail\EscalationMail;
use App\Models\ReminderEvent;
use App\Models\ReminderRecipient;
use App\Models\ScheduledAction;
use App\Services\BrevoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CheckEscalationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(BrevoService $brevoService): void
    {
        // Find reminders that need escalation:
        // - Status is awaiting_response
        // - Has escalation_contacts configured
        // - Has escalate_after_hours configured
        // - Sent event exists and escalate_after_hours has passed
        // - No escalated event exists yet

        $reminders = ScheduledAction::query()
            ->where('type', ScheduledAction::TYPE_REMINDER)
            ->where('resolution_status', ScheduledAction::STATUS_AWAITING_RESPONSE)
            ->whereNotNull('escalation_rules')
            ->get();

        $escalatedCount = 0;

        foreach ($reminders as $reminder) {
            if ($this->shouldEscalate($reminder)) {
                $this->escalate($reminder, $brevoService);
                $escalatedCount++;
            }
        }

        if ($escalatedCount > 0) {
            Log::info('Escalations processed', ['count' => $escalatedCount]);
        }
    }

    private function shouldEscalate(ScheduledAction $reminder): bool
    {
        $rules = $reminder->escalation_rules;

        // Check if escalation is configured
        $escalateAfterHours = $rules['escalate_after_hours'] ?? null;
        $escalationContacts = $rules['escalation_contacts'] ?? [];

        if (! $escalateAfterHours || empty($escalationContacts)) {
            return false;
        }

        // Check if already escalated
        $alreadyEscalated = ReminderEvent::query()
            ->where('reminder_id', $reminder->id)
            ->where('event_type', ReminderEvent::TYPE_ESCALATED)
            ->exists();

        if ($alreadyEscalated) {
            return false;
        }

        // Find when the reminder was first sent
        $sentEvent = ReminderEvent::query()
            ->where('reminder_id', $reminder->id)
            ->where('event_type', ReminderEvent::TYPE_SENT)
            ->orderBy('created_at', 'asc')
            ->first();

        if (! $sentEvent) {
            return false;
        }

        // Check if escalation time has passed
        $escalateAt = $sentEvent->created_at->addHours((float) $escalateAfterHours);

        return now()->gte($escalateAt);
    }

    private function escalate(ScheduledAction $reminder, BrevoService $brevoService): void
    {
        $rules = $reminder->escalation_rules;
        $escalationContacts = $rules['escalation_contacts'] ?? [];

        $sentCount = 0;
        $contacts = [];

        foreach ($escalationContacts as $contact) {
            $isPhone = $this->isPhoneNumber($contact);
            $isEmail = filter_var($contact, FILTER_VALIDATE_EMAIL) !== false;

            // Create a recipient record for escalation contacts too
            $recipientRecord = ReminderRecipient::firstOrCreate(
                [
                    'action_id' => $reminder->id,
                    'email' => $contact,
                ],
                [
                    'status' => ReminderRecipient::STATUS_PENDING,
                    'response_token' => Str::random(20),
                ]
            );

            if ($isEmail) {
                $this->sendEscalationEmail($reminder, $recipientRecord, $contact);
                $sentCount++;
                $contacts[] = $contact;
            } elseif ($isPhone) {
                $this->sendEscalationSms($reminder, $recipientRecord, $contact, $brevoService);
                $sentCount++;
                $contacts[] = $contact;
            }
        }

        // Record the escalation event
        ReminderEvent::create([
            'reminder_id' => $reminder->id,
            'event_type' => ReminderEvent::TYPE_ESCALATED,
            'captured_timezone' => $reminder->timezone,
            'notes' => "Escalated to {$sentCount} contact(s): ".implode(', ', $contacts),
        ]);

        Log::info('Reminder escalated', [
            'action_id' => $reminder->id,
            'contacts' => $contacts,
        ]);
    }

    private function sendEscalationEmail(ScheduledAction $reminder, ReminderRecipient $recipient, string $email): void
    {
        try {
            Mail::to($email)->send(new EscalationMail($reminder, $recipient));

            Log::info('Escalation email sent', [
                'action_id' => $reminder->id,
                'recipient' => $email,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send escalation email', [
                'action_id' => $reminder->id,
                'recipient' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendEscalationSms(ScheduledAction $reminder, ReminderRecipient $recipient, string $phone, BrevoService $brevoService): void
    {
        $baseUrl = config('app.url');
        $token = $recipient->response_token;

        // Short URL format for SMS: /r/{token}
        $responseUrl = "{$baseUrl}/r/{$token}";

        // SMS limit is 160 chars. Reserve space for prefix, URL, and formatting
        $prefix = '⚠️ ESCALATION: ';
        $urlPart = "\n👉 {$responseUrl}";
        $maxMessageLength = 160 - strlen($prefix) - strlen($urlPart) - 3;

        // Use message if available, otherwise fall back to name
        $content = $reminder->message ?: $reminder->name;

        if (strlen($content) > $maxMessageLength) {
            $content = substr($content, 0, $maxMessageLength).'...';
        }

        $message = "{$prefix}{$content}{$urlPart}";

        $brevoService->sendSms($phone, $message);
    }

    private function isPhoneNumber(string $value): bool
    {
        return preg_match('/^\+?[\d\s\-\(\)]+$/', $value) === 1 && strlen(preg_replace('/\D/', '', $value)) >= 10;
    }
}

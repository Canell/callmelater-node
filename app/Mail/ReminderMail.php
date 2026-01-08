<?php

namespace App\Mail;

use App\Models\NotificationConsent;
use App\Models\ReminderRecipient;
use App\Models\ScheduledAction;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $confirmUrl;
    public string $declineUrl;
    public string $snoozeUrl;
    public ?string $unsubscribeUrl = null;

    public function __construct(
        public ScheduledAction $action,
        public ReminderRecipient $recipient
    ) {
        $baseUrl = config('app.url');
        $token = $recipient->response_token;

        $this->confirmUrl = "{$baseUrl}/respond?token={$token}&response=confirm";
        $this->declineUrl = "{$baseUrl}/respond?token={$token}&response=decline";
        $this->snoozeUrl = "{$baseUrl}/respond?token={$token}&response=snooze";

        // Get consent token for unsubscribe link
        $consent = NotificationConsent::where('email', NotificationConsent::normalizeEmail($recipient->email))->first();
        if ($consent && $consent->consent_token) {
            $this->unsubscribeUrl = "{$baseUrl}/api/v1/consent/unsubscribe/{$consent->consent_token}";
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Reminder: {$this->action->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reminder',
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}

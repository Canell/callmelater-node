<?php

namespace App\Mail;

use App\Models\NotificationConsent;
use App\Models\ScheduledAction;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OptInRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public NotificationConsent $consent,
        public User $sender,
        public ScheduledAction $action
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You've been invited to receive reminders",
        );
    }

    public function content(): Content
    {
        $baseUrl = config('app.url');
        $token = $this->consent->consent_token;

        return new Content(
            view: 'emails.optin-request',
            with: [
                'senderName' => $this->sender->name,
                'accountName' => $this->action->account->name ?? $this->sender->name,
                'recipientEmail' => $this->consent->email,
                'acceptUrl' => "{$baseUrl}/api/v1/consent/accept/{$token}",
                'declineUrl' => "{$baseUrl}/api/v1/consent/decline/{$token}",
            ],
        );
    }
}

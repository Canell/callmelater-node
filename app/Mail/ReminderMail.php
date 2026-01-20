<?php

namespace App\Mail;

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

    public function __construct(
        public ScheduledAction $action,
        public ReminderRecipient $recipient
    ) {
        $baseUrl = config('app.url');
        $token = $recipient->response_token;

        $this->confirmUrl = "{$baseUrl}/respond?token={$token}&response=confirm";
        $this->declineUrl = "{$baseUrl}/respond?token={$token}&response=decline";
        $this->snoozeUrl = "{$baseUrl}/respond?token={$token}&response=snooze";
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

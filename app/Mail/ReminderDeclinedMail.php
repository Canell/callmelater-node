<?php

namespace App\Mail;

use App\Models\ReminderRecipient;
use App\Models\ScheduledAction;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReminderDeclinedMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $actionUrl;

    public function __construct(
        public ScheduledAction $action,
        public ReminderRecipient $recipient
    ) {
        $this->actionUrl = config('app.url').'/actions/'.$action->id;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Reminder declined: {$this->action->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reminder-declined',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

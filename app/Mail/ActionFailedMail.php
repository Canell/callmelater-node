<?php

namespace App\Mail;

use App\Models\ScheduledAction;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ActionFailedMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $actionUrl;

    public function __construct(
        public ScheduledAction $action
    ) {
        $this->actionUrl = config('app.url').'/actions/'.$action->id;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Action failed: {$this->action->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.action-failed',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

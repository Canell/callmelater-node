<?php

namespace App\Mail;

use App\Models\ScheduledAction;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResponseNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $actionUrl;

    public string $responseLabel;

    public function __construct(
        public ScheduledAction $action,
        public string $response,
        public string $respondentName
    ) {
        $this->actionUrl = config('app.url').'/actions/'.$action->id;
        $this->responseLabel = $this->getResponseLabel($response);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->respondentName} {$this->responseLabel}: {$this->action->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.response-notification',
        );
    }

    public function attachments(): array
    {
        return [];
    }

    private function getResponseLabel(string $response): string
    {
        return match ($response) {
            'confirm' => 'confirmed',
            'decline' => 'declined',
            'snooze' => 'snoozed',
            default => 'responded to',
        };
    }
}

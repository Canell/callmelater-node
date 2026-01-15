<?php

namespace App\Mail;

use App\Models\Incident;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class IncidentAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $statusPageUrl;
    public string $adminUrl;

    public function __construct(
        public Incident $incident,
        public string $componentName,
        public string $reason
    ) {
        $baseUrl = config('app.url');
        $this->statusPageUrl = "{$baseUrl}/status";
        $this->adminUrl = "{$baseUrl}/admin";
    }

    public function envelope(): Envelope
    {
        $impactLabel = strtoupper($this->incident->impact);
        return new Envelope(
            subject: "[{$impactLabel}] {$this->incident->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.incident-alert',
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

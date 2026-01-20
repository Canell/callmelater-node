<?php

namespace App\Mail;

use App\Models\Account;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuotaWarningMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $settingsUrl;

    /**
     * @param  array{actions: array{used: int, limit: int, percentage: float}, sms: array{used: int, limit: int, percentage: float}}  $usage
     */
    public function __construct(
        public Account $account,
        public array $usage
    ) {
        $this->settingsUrl = config('app.url').'/settings';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You're approaching your plan limits",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.quota-warning',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

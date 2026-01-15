<?php

namespace App\Mail;

use App\Models\MagicLinkToken;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SignupMagicLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public MagicLinkToken $magicLink
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Complete your CallMeLater signup',
        );
    }

    public function content(): Content
    {
        $baseUrl = config('app.url');

        return new Content(
            view: 'emails.magic-link-signup',
            with: [
                'signupUrl' => "{$baseUrl}/auth/magic-link/verify/{$this->magicLink->token}",
                'expiresIn' => '24 hours',
            ],
        );
    }
}

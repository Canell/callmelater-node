<?php

namespace App\Mail;

use App\Models\MagicLinkToken;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MagicLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public MagicLinkToken $magicLink
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your CallMeLater login link',
        );
    }

    public function content(): Content
    {
        $baseUrl = config('app.url');

        return new Content(
            view: 'emails.magic-link',
            with: [
                'userName' => $this->user->name,
                'loginUrl' => "{$baseUrl}/auth/magic-link/verify/{$this->magicLink->token}",
                'expiresIn' => '15 minutes',
            ],
        );
    }
}

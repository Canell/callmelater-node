<?php

namespace App\Mail;

use App\Models\TeamInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public TeamInvitation $invitation
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You've been invited to join {$this->invitation->team->name} on CallMeLater",
        );
    }

    public function content(): Content
    {
        $baseUrl = config('app.url');

        return new Content(
            view: 'emails.team-invitation',
            with: [
                'teamName' => $this->invitation->team->name,
                'inviterName' => $this->invitation->inviter->name,
                'acceptUrl' => "{$baseUrl}/invitations/{$this->invitation->token}",
                'expiresIn' => '7 days',
            ],
        );
    }
}

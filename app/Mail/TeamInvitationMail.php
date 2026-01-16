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
        $workspaceName = $this->invitation->team->account->name ?? $this->invitation->team->name;

        return new Envelope(
            subject: "You've been invited to join {$workspaceName} on CallMeLater",
        );
    }

    public function content(): Content
    {
        $baseUrl = config('app.url');
        $workspaceName = $this->invitation->team->account->name ?? $this->invitation->team->name;

        return new Content(
            view: 'emails.team-invitation',
            with: [
                'workspaceName' => $workspaceName,
                'inviterName' => $this->invitation->inviter->name,
                'acceptUrl' => "{$baseUrl}/invitations/{$this->invitation->token}",
                'expiresIn' => '7 days',
            ],
        );
    }
}

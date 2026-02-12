<?php

namespace App\Mail;

use App\Models\ReminderRecipient;
use App\Models\ScheduledAction;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $confirmUrl;

    public string $declineUrl;

    public string $snoozeUrl;

    /**
     * Maximum attachment size in bytes (5MB).
     */
    private const MAX_ATTACHMENT_SIZE = 5 * 1024 * 1024;

    /**
     * Allowed MIME types for attachments.
     */
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'text/plain',
        'text/csv',
        'application/json',
        'application/xml',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

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
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $gateAttachments = $this->action->getGateAttachments();

        if (empty($gateAttachments)) {
            return [];
        }

        $attachments = [];

        foreach ($gateAttachments as $index => $attachment) {
            try {
                $url = $attachment['url'];
                $customName = $attachment['name'] ?? null;

                // Fetch the file with size limit and timeout
                $response = Http::timeout(10)
                    ->withOptions(['stream' => true])
                    ->get($url);

                if (! $response->successful()) {
                    Log::warning('Failed to fetch attachment', [
                        'action_id' => $this->action->id,
                        'url' => $url,
                        'status' => $response->status(),
                    ]);
                    continue;
                }

                $body = $response->body();

                // Check file size
                if (strlen($body) > self::MAX_ATTACHMENT_SIZE) {
                    Log::warning('Attachment too large, skipping', [
                        'action_id' => $this->action->id,
                        'url' => $url,
                        'size' => strlen($body),
                        'max_size' => self::MAX_ATTACHMENT_SIZE,
                    ]);
                    continue;
                }

                // Detect MIME type
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->buffer($body);

                // Check if MIME type is allowed
                if (! in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
                    Log::warning('Attachment MIME type not allowed, skipping', [
                        'action_id' => $this->action->id,
                        'url' => $url,
                        'mime_type' => $mimeType,
                    ]);
                    continue;
                }

                // Determine filename
                $filename = $customName ?? $this->extractFilename($url, $mimeType);

                $attachments[] = Attachment::fromData(
                    fn () => $body,
                    $filename
                )->withMime($mimeType);

            } catch (\Throwable $e) {
                Log::warning('Error fetching attachment', [
                    'action_id' => $this->action->id,
                    'url' => $attachment['url'],
                    'error' => $e->getMessage(),
                ]);
                continue;
            }
        }

        return $attachments;
    }

    /**
     * Extract filename from URL or generate one based on MIME type.
     */
    private function extractFilename(string $url, string $mimeType): string
    {
        // Try to extract from URL path
        $path = parse_url($url, PHP_URL_PATH);
        if ($path) {
            $basename = basename($path);
            // Check if it has a reasonable extension
            if (str_contains($basename, '.') && strlen($basename) <= 255) {
                return $basename;
            }
        }

        // Generate filename based on MIME type
        $extensions = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'text/plain' => 'txt',
            'text/csv' => 'csv',
            'application/json' => 'json',
            'application/xml' => 'xml',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        ];

        $ext = $extensions[$mimeType] ?? 'bin';

        return 'attachment_' . time() . '.' . $ext;
    }
}

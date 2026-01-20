<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BrevoService
{
    private const API_URL = 'https://api.brevo.com/v3/transactionalSMS/sms';

    private ?string $apiKey;

    private string $sender;

    private string $type;

    public function __construct()
    {
        $this->apiKey = config('brevo.api_key');
        $this->sender = config('brevo.sender', 'CallMeLater');
        $this->type = config('brevo.type', 'transactional');
    }

    public function isEnabled(): bool
    {
        return config('brevo.enabled', false) && ! empty($this->apiKey);
    }

    /**
     * Send an SMS message.
     *
     * @return string|null The message ID if successful, null otherwise
     */
    public function sendSms(string $to, string $message): ?string
    {
        if (! $this->isEnabled()) {
            Log::warning('Brevo SMS disabled, would send to: '.$to);

            return null;
        }

        try {
            // Normalize phone number (remove spaces, dashes, etc. but keep +)
            $recipient = preg_replace('/[^\d+]/', '', $to);

            // Remove leading + for Brevo API (they expect just digits with country code)
            $recipient = ltrim($recipient, '+');

            // Debug: Log API key info (first 4 chars only for security)
            $keyPreview = $this->apiKey ? substr($this->apiKey, 0, 4).'...' : 'NULL';
            Log::debug('Brevo SMS attempt', [
                'api_key_preview' => $keyPreview,
                'api_key_length' => $this->apiKey ? strlen($this->apiKey) : 0,
                'sender' => $this->sender,
                'recipient' => $recipient,
            ]);

            $response = Http::withHeaders([
                'accept' => 'application/json',
                'api-key' => $this->apiKey,
                'content-type' => 'application/json',
            ])->post(self::API_URL, [
                'sender' => $this->sender,
                'recipient' => $recipient,
                'content' => $message,
                'type' => $this->type,
            ]);

            if ($response->successful()) {
                $messageId = $response->json('messageId');

                Log::info('SMS sent successfully via Brevo', [
                    'to' => $to,
                    'messageId' => $messageId,
                ]);

                return (string) $messageId;
            }

            Log::error('Failed to send SMS via Brevo', [
                'to' => $to,
                'status' => $response->status(),
                'error' => $response->json(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to send SMS via Brevo', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Send a reminder SMS with a single response link.
     * The message is truncated to fit SMS limits (160 chars).
     */
    public function sendReminderSms(
        string $to,
        string $actionName,
        ?string $actionMessage,
        string $responseUrl
    ): ?string {
        // SMS limit is 160 chars. Reserve space for URL (~50 chars) and formatting
        // Format: "Message preview...\n👉 URL"
        $urlPart = "\n👉 {$responseUrl}";
        $maxMessageLength = 160 - strlen($urlPart) - 3; // -3 for "..."

        // Use message if available, otherwise fall back to action name
        $content = $actionMessage ?: $actionName;

        // Truncate if needed
        if (strlen($content) > $maxMessageLength) {
            $content = substr($content, 0, $maxMessageLength).'...';
        }

        $message = "{$content}{$urlPart}";

        return $this->sendSms($to, $message);
    }
}

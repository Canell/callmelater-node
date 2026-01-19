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
     */
    public function sendReminderSms(
        string $to,
        string $actionName,
        string $responseUrl
    ): ?string {
        $message = "CallMeLater: {$actionName}\n{$responseUrl}";

        return $this->sendSms($to, $message);
    }
}

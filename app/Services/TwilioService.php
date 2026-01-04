<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

class TwilioService
{
    private ?Client $client = null;

    public function __construct()
    {
        $sid = config('twilio.sid');
        $token = config('twilio.auth_token');

        if ($sid && $token) {
            $this->client = new Client($sid, $token);
        }
    }

    public function isEnabled(): bool
    {
        return config('twilio.enabled', false) && $this->client !== null;
    }

    /**
     * Send an SMS message.
     *
     * @return string|null The message SID if successful, null otherwise
     */
    public function sendSms(string $to, string $message): ?string
    {
        if (! $this->isEnabled()) {
            Log::warning('Twilio SMS disabled, would send to: ' . $to);
            return null;
        }

        if (! $this->client) {
            Log::error('Twilio client not initialized');
            return null;
        }

        try {
            $fromNumber = config('twilio.from_number');

            $result = $this->client->messages->create($to, [
                'from' => $fromNumber,
                'body' => $message,
            ]);

            Log::info('SMS sent successfully', [
                'to' => $to,
                'sid' => $result->sid,
            ]);

            return $result->sid;
        } catch (\Exception $e) {
            Log::error('Failed to send SMS', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Send a reminder SMS with response links.
     */
    public function sendReminderSms(
        string $to,
        string $actionName,
        string $confirmUrl,
        string $declineUrl
    ): ?string {
        $message = "Reminder: {$actionName}\n\n"
            . "Confirm: {$confirmUrl}\n"
            . "Decline: {$declineUrl}";

        return $this->sendSms($to, $message);
    }
}

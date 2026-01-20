<?php

namespace App\Jobs;

use App\Models\CallbackAttempt;
use App\Models\ScheduledAction;
use App\Services\HttpRequestService;
use App\Services\UrlValidator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Delivers reminder response callbacks to user-specified webhook URLs.
 *
 * CRITICAL: This is a BEST-EFFORT delivery job.
 * - Callback delivery is a notification, NOT part of the reminder decision
 * - Callback failures NEVER affect the reminder outcome
 * - Retries are capped (max 3 attempts with exponential backoff)
 * - 4xx responses are permanent failures (no retry)
 */
class DeliverReminderCallback implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Only one attempt per job dispatch - we handle retries by re-dispatching.
     */
    public int $tries = 1;

    public int $timeout = 30;

    /**
     * Max callback delivery attempts.
     */
    private const MAX_ATTEMPTS = 3;

    /**
     * Retry delays in seconds: 1min, 5min, 15min
     */
    private const RETRY_DELAYS = [60, 300, 900];

    public function __construct(
        public ScheduledAction $action,
        public string $response,
        public string $responderEmail,
        public int $attemptNumber = 1,
        public ?string $snoozePreset = null,
        public ?string $nextReminderAt = null,
    ) {}

    public function handle(UrlValidator $urlValidator, HttpRequestService $httpService): void
    {
        $callbackUrl = $this->action->callback_url;

        if (! $callbackUrl) {
            return;
        }

        // Validate URL for security (SSRF prevention)
        try {
            $urlValidator->validate($callbackUrl);
        } catch (\InvalidArgumentException $e) {
            $this->logAttempt(CallbackAttempt::STATUS_FAILED, null, $e->getMessage(), 0);
            $this->log('warning', 'callback_blocked', [
                'reason' => 'security_block',
                'detail' => $e->getMessage(),
            ]);

            return; // Security failure - don't retry
        }

        $payload = $this->buildPayload();
        $startTime = microtime(true);

        try {
            /** @var \App\Models\User|null $owner */
            $owner = $this->action->owner;

            $response = $httpService->makeRequest(
                [
                    'url' => $callbackUrl,
                    'method' => 'POST',
                    'headers' => ['Content-Type' => 'application/json'],
                    'body' => $payload,
                ],
                $owner?->webhook_secret,
                $this->action->id,
                'reminder.responded'
            );

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $statusCode = $response->status();

            if ($response->successful()) {
                $this->logAttempt(CallbackAttempt::STATUS_SUCCESS, $statusCode, null, $durationMs);
                $this->log('info', 'callback_delivered', [
                    'status_code' => $statusCode,
                    'duration_ms' => $durationMs,
                    'attempt' => $this->attemptNumber,
                ]);
            } elseif ($response->clientError()) {
                // 4xx - Permanent failure, don't retry
                $this->logAttempt(CallbackAttempt::STATUS_FAILED, $statusCode, "HTTP {$statusCode}", $durationMs);
                $this->log('warning', 'callback_failed', [
                    'status_code' => $statusCode,
                    'duration_ms' => $durationMs,
                    'attempt' => $this->attemptNumber,
                    'reason' => 'client_error_permanent',
                    'retry_scheduled' => false,
                ]);
            } else {
                // 5xx - Server error, retry
                $this->logAttempt(CallbackAttempt::STATUS_FAILED, $statusCode, "HTTP {$statusCode}", $durationMs);
                $this->scheduleRetry("HTTP {$statusCode}");
            }
        } catch (ConnectionException $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->logAttempt(CallbackAttempt::STATUS_FAILED, null, $e->getMessage(), $durationMs);
            $this->scheduleRetry($e->getMessage());
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->logAttempt(CallbackAttempt::STATUS_FAILED, null, $e->getMessage(), $durationMs);
            $this->scheduleRetry($e->getMessage());
        }
    }

    /**
     * Build the callback payload.
     *
     * @return array<string, mixed>
     */
    private function buildPayload(): array
    {
        $payload = [
            'event' => 'reminder.responded',
            'action_id' => $this->action->id,
            'action_name' => $this->action->name,
            'response' => $this->response,
            'responder_email' => $this->responderEmail,
            'responded_at' => now()->toIso8601String(),
            'action_status' => $this->action->resolution_status,
            'timestamp' => now()->unix(),
        ];

        // Add snooze-specific fields
        if ($this->response === 'snooze' && $this->snoozePreset) {
            $payload['snooze_preset'] = $this->snoozePreset;
        }
        if ($this->response === 'snooze' && $this->nextReminderAt) {
            $payload['next_reminder_at'] = $this->nextReminderAt;
        }

        return $payload;
    }

    /**
     * Schedule a retry if attempts remain.
     */
    private function scheduleRetry(string $reason): void
    {
        if ($this->attemptNumber >= self::MAX_ATTEMPTS) {
            $this->log('warning', 'callback_abandoned', [
                'reason' => $reason,
                'attempt' => $this->attemptNumber,
                'max_attempts' => self::MAX_ATTEMPTS,
            ]);

            return;
        }

        $delayIndex = min($this->attemptNumber - 1, count(self::RETRY_DELAYS) - 1);
        $delay = self::RETRY_DELAYS[$delayIndex];

        self::dispatch(
            $this->action,
            $this->response,
            $this->responderEmail,
            $this->attemptNumber + 1,
            $this->snoozePreset,
            $this->nextReminderAt,
        )->delay(now()->addSeconds($delay));

        $this->log('info', 'callback_retry_scheduled', [
            'reason' => $reason,
            'attempt' => $this->attemptNumber,
            'next_attempt' => $this->attemptNumber + 1,
            'delay_seconds' => $delay,
        ]);
    }

    /**
     * Log an attempt to the callback_attempts table.
     */
    private function logAttempt(string $status, ?int $responseCode, ?string $errorMessage, int $durationMs): void
    {
        CallbackAttempt::create([
            'action_id' => $this->action->id,
            'attempt_number' => $this->attemptNumber,
            'status' => $status,
            'response_code' => $responseCode,
            'error_message' => $errorMessage,
            'duration_ms' => $durationMs,
        ]);
    }

    /**
     * Structured logging with consistent context.
     *
     * @param  array<string, mixed>  $extra
     */
    private function log(string $level, string $event, array $extra = []): void
    {
        $context = array_merge([
            'event' => "reminder_callback.{$event}",
            'action_id' => $this->action->id,
            'action_name' => $this->action->name,
            'callback_url' => $this->action->callback_url,
        ], $extra);

        Log::{$level}("Reminder Callback: {$event}", $context);
    }
}

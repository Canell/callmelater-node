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
 * Delivers action lifecycle callbacks to user-specified webhook URLs.
 *
 * Supports all event types:
 * - action.executed: HTTP action completed successfully
 * - action.failed: HTTP action failed after all retries
 * - action.expired: Reminder expired without response
 * - reminder.responded: Someone responded to a reminder (handled by DeliverReminderCallback)
 *
 * CRITICAL: This is a BEST-EFFORT delivery job.
 * - Callback delivery is a notification, NOT part of the action decision
 * - Callback failures NEVER affect the action outcome
 * - Retries are capped (max 3 attempts with exponential backoff)
 * - 4xx responses are permanent failures (no retry)
 */
class DeliverActionCallback implements ShouldQueue
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

    /**
     * Supported event types.
     */
    public const EVENT_EXECUTED = 'action.executed';
    public const EVENT_FAILED = 'action.failed';
    public const EVENT_EXPIRED = 'action.expired';

    /**
     * @param  array<string, mixed>  $metadata  Additional event-specific data
     */
    public function __construct(
        public ScheduledAction $action,
        public string $event,
        public array $metadata = [],
        public int $attemptNumber = 1,
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
                $this->event
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
            'event' => $this->event,
            'action_id' => $this->action->id,
            'action_name' => $this->action->name,
            'action_status' => $this->action->resolution_status,
            'timestamp' => now()->unix(),
            'occurred_at' => now()->toIso8601String(),
        ];

        // Add event-specific metadata
        switch ($this->event) {
            case self::EVENT_EXECUTED:
                $payload['execution'] = [
                    'status_code' => $this->metadata['status_code'] ?? null,
                    'duration_ms' => $this->metadata['duration_ms'] ?? null,
                    'attempt_number' => $this->metadata['attempt_number'] ?? null,
                ];
                break;

            case self::EVENT_FAILED:
                $payload['failure'] = [
                    'reason' => $this->metadata['reason'] ?? 'unknown',
                    'last_status_code' => $this->metadata['status_code'] ?? null,
                    'total_attempts' => $this->metadata['total_attempts'] ?? null,
                    'error_message' => $this->metadata['error_message'] ?? null,
                ];
                break;

            case self::EVENT_EXPIRED:
                $payload['expiration'] = [
                    'expired_at' => $this->metadata['expired_at'] ?? now()->toIso8601String(),
                    'timeout_setting' => $this->action->getGateTimeout(),
                    'recipients_count' => count($this->action->getGateRecipients()),
                ];
                break;
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
            $this->event,
            $this->metadata,
            $this->attemptNumber + 1,
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
            'event_type' => $this->event,
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
    private function log(string $level, string $eventName, array $extra = []): void
    {
        $context = array_merge([
            'log_event' => "action_callback.{$eventName}",
            'action_id' => $this->action->id,
            'action_name' => $this->action->name,
            'callback_event' => $this->event,
            'callback_url' => $this->action->callback_url,
        ], $extra);

        Log::{$level}("Action Callback: {$eventName}", $context);
    }
}

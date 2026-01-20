<?php

namespace App\Jobs;

use App\Models\DeliveryAttempt;
use App\Models\ExecutionCycle;
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

class DeliverHttpAction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    // Failure types for structured logging and retry decisions
    private const FAILURE_DOMAIN_CLIENT = 'domain_client';    // 4xx - client error, don't retry

    private const FAILURE_DOMAIN_SERVER = 'domain_server';    // 5xx - server error, retry

    private const FAILURE_SYSTEM = 'system';                   // Network/timeout, always retry

    public function __construct(
        public ScheduledAction $action
    ) {}

    public function handle(UrlValidator $urlValidator, HttpRequestService $httpService): void
    {
        // CRITICAL: Verify action is still in EXECUTING state
        // This guards against cancellation race conditions
        $this->action->refresh();
        if (! $this->action->isExecuting()) {
            $this->log('info', 'action_skipped', [
                'reason' => 'no_longer_executing',
                'current_status' => $this->action->resolution_status,
            ]);

            return;
        }

        /** @var array<string, mixed>|null $httpRequest */
        $httpRequest = $this->action->http_request;

        if (! is_array($httpRequest) || ! isset($httpRequest['url'])) {
            $this->log('error', 'validation_failed', ['reason' => 'invalid_config']);
            $this->action->markAsFailed('Invalid HTTP request configuration');

            return;
        }

        // Validate URL for security (SSRF prevention)
        try {
            $urlValidator->validate($httpRequest['url']);
        } catch (\InvalidArgumentException $e) {
            $this->log('warning', 'validation_failed', [
                'reason' => 'security_block',
                'detail' => $e->getMessage(),
            ]);
            $this->action->markAsFailed("Security: {$e->getMessage()}");

            return;
        }

        // Check payload size
        $body = $httpRequest['body'] ?? null;
        if ($body !== null) {
            $bodySize = strlen(json_encode($body) ?: '');
            $maxSize = config('callmelater.http.max_body_size', 1048576);
            if ($bodySize > $maxSize) {
                $this->log('warning', 'validation_failed', [
                    'reason' => 'payload_too_large',
                    'size' => $bodySize,
                    'max_size' => $maxSize,
                ]);
                $this->action->markAsFailed("Request body exceeds maximum size ({$maxSize} bytes)");

                return;
            }
        }

        // Record that we're starting an attempt
        $this->action->recordAttempt();

        $startTime = microtime(true);
        $attempt = new DeliveryAttempt([
            'action_id' => $this->action->id,
            'execution_cycle_id' => $this->action->current_execution_cycle_id,
            'execution_id' => $this->action->current_execution_cycle_id ?? $this->action->id,
            'attempt_number' => $this->action->attempt_count,
            'target_domain' => parse_url($httpRequest['url'], PHP_URL_HOST),
        ]);

        try {
            $response = $httpService->makeRequest(
                $httpRequest,
                $this->action->webhook_secret,
                $this->action->id
            );
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $attempt->response_code = $response->status();
            $attempt->response_body = substr($response->body(), 0, 10000);
            $attempt->duration_ms = $durationMs;

            if ($response->successful()) {
                // 2xx - Success
                $attempt->status = DeliveryAttempt::STATUS_SUCCESS;
                $attempt->failure_category = DeliveryAttempt::CATEGORY_SUCCESS;
                $attempt->save();
                $this->handleSuccess($attempt, $response->status(), $durationMs);
            } elseif ($response->clientError()) {
                // 4xx - Client error (customer misconfiguration, don't retry)
                $attempt->status = DeliveryAttempt::STATUS_FAILED;
                $attempt->failure_category = DeliveryAttempt::CATEGORY_CUSTOMER_4XX;
                $attempt->save();
                $this->handleDomainFailure($attempt, $response->status(), $durationMs, isClientError: true);
            } else {
                // 5xx - Server error (customer server issue, retry)
                $attempt->status = DeliveryAttempt::STATUS_FAILED;
                $attempt->failure_category = DeliveryAttempt::CATEGORY_CUSTOMER_5XX;
                $attempt->save();
                $this->handleDomainFailure($attempt, $response->status(), $durationMs, isClientError: false);
            }
        } catch (ConnectionException $e) {
            // Network/connection failure - delivery error (our infrastructure issue)
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $attempt->status = DeliveryAttempt::STATUS_FAILED;
            $attempt->failure_category = DeliveryAttempt::CATEGORY_DELIVERY_ERROR;
            $attempt->error_message = $e->getMessage();
            $attempt->duration_ms = $durationMs;
            $attempt->save();

            $this->handleSystemFailure($attempt, $e->getMessage(), $durationMs);
        } catch (\Throwable $e) {
            // Other exceptions - delivery error (infrastructure issue)
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $attempt->status = DeliveryAttempt::STATUS_FAILED;
            $attempt->failure_category = DeliveryAttempt::CATEGORY_DELIVERY_ERROR;
            $attempt->error_message = $e->getMessage();
            $attempt->duration_ms = $durationMs;
            $attempt->save();

            $this->handleSystemFailure($attempt, $e->getMessage(), $durationMs);
        }
    }

    /**
     * Handle successful HTTP response (2xx).
     */
    private function handleSuccess(DeliveryAttempt $attempt, int $statusCode, int $durationMs): void
    {
        $this->action->markAsExecuted();

        // Update execution cycle if present
        if ($this->action->current_execution_cycle_id) {
            ExecutionCycle::find($this->action->current_execution_cycle_id)
                ?->markAsSuccess();
        }

        $this->log('info', 'delivery_success', [
            'status_code' => $statusCode,
            'duration_ms' => $durationMs,
            'attempt' => $attempt->attempt_number,
        ]);
    }

    /**
     * Handle domain failure (HTTP 4xx/5xx).
     * - 4xx (client error): Terminal failure, don't retry
     * - 5xx (server error): Retry if attempts remain
     */
    private function handleDomainFailure(DeliveryAttempt $attempt, int $statusCode, int $durationMs, bool $isClientError): void
    {
        $failureType = $isClientError ? self::FAILURE_DOMAIN_CLIENT : self::FAILURE_DOMAIN_SERVER;

        if ($isClientError) {
            // 4xx - Client errors are terminal (bad request, not found, unauthorized, etc.)
            $this->action->markAsFailed("HTTP {$statusCode}: Client error (not retryable)");
            $this->updateExecutionCycleOnFailure("HTTP {$statusCode}: Client error (not retryable)");

            $this->log('warning', 'delivery_failed', [
                'failure_type' => $failureType,
                'status_code' => $statusCode,
                'duration_ms' => $durationMs,
                'attempt' => $attempt->attempt_number,
                'retry_scheduled' => false,
                'reason' => 'client_error_not_retryable',
            ]);
        } else {
            // 5xx - Server errors are retryable
            if ($this->action->shouldRetry()) {
                $this->action->scheduleNextRetry();

                $this->log('warning', 'delivery_failed', [
                    'failure_type' => $failureType,
                    'status_code' => $statusCode,
                    'duration_ms' => $durationMs,
                    'attempt' => $attempt->attempt_number,
                    'retry_scheduled' => true,
                    'attempts_remaining' => $this->action->max_attempts - $this->action->attempt_count,
                ]);
            } else {
                $failureReason = "HTTP {$statusCode}: Failed after {$this->action->attempt_count} attempts";
                $this->action->markAsFailed($failureReason);
                $this->updateExecutionCycleOnFailure($failureReason);

                $this->log('error', 'delivery_failed', [
                    'failure_type' => $failureType,
                    'status_code' => $statusCode,
                    'duration_ms' => $durationMs,
                    'attempt' => $attempt->attempt_number,
                    'retry_scheduled' => false,
                    'reason' => 'max_attempts_reached',
                ]);
            }
        }
    }

    /**
     * Handle system failure (network error, timeout, exception).
     * Always retry if attempts remain.
     */
    private function handleSystemFailure(DeliveryAttempt $attempt, string $errorMessage, int $durationMs): void
    {
        if ($this->action->shouldRetry()) {
            $this->action->scheduleNextRetry();

            $this->log('warning', 'delivery_failed', [
                'failure_type' => self::FAILURE_SYSTEM,
                'error' => $errorMessage,
                'duration_ms' => $durationMs,
                'attempt' => $attempt->attempt_number,
                'retry_scheduled' => true,
                'attempts_remaining' => $this->action->max_attempts - $this->action->attempt_count,
            ]);
        } else {
            $failureReason = "System error: {$errorMessage} (after {$this->action->attempt_count} attempts)";
            $this->action->markAsFailed($failureReason);
            $this->updateExecutionCycleOnFailure($failureReason);

            $this->log('error', 'delivery_failed', [
                'failure_type' => self::FAILURE_SYSTEM,
                'error' => $errorMessage,
                'duration_ms' => $durationMs,
                'attempt' => $attempt->attempt_number,
                'retry_scheduled' => false,
                'reason' => 'max_attempts_reached',
            ]);
        }
    }

    /**
     * Structured logging with consistent context.
     *
     * @param  array<string, mixed>  $extra
     */
    private function log(string $level, string $event, array $extra = []): void
    {
        $context = array_merge([
            'event' => "http_executor.{$event}",
            'action_id' => $this->action->id,
            'action_name' => $this->action->name,
        ], $extra);

        Log::{$level}("HTTP Executor: {$event}", $context);
    }

    /**
     * Update the current execution cycle when action fails terminally.
     */
    private function updateExecutionCycleOnFailure(string $reason): void
    {
        if ($this->action->current_execution_cycle_id) {
            ExecutionCycle::find($this->action->current_execution_cycle_id)
                ?->markAsFailed($reason);
        }
    }
}

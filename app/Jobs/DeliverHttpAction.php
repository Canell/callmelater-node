<?php

namespace App\Jobs;

use App\Models\DeliveryAttempt;
use App\Models\ScheduledAction;
use App\Services\ActionService;
use App\Services\UrlValidator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeliverHttpAction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(
        public ScheduledAction $action
    ) {}

    public function handle(ActionService $actionService, UrlValidator $urlValidator): void
    {
        /** @var array<string, mixed>|null $httpRequest */
        $httpRequest = $this->action->http_request;

        if (! is_array($httpRequest) || ! isset($httpRequest['url'])) {
            Log::error("Invalid HTTP request configuration", ['action_id' => $this->action->id]);
            $actionService->markFailed($this->action, 'Invalid HTTP request configuration');
            return;
        }

        // Validate URL for security (SSRF prevention)
        try {
            $urlValidator->validate($httpRequest['url']);
        } catch (\InvalidArgumentException $e) {
            Log::warning("HTTP action blocked for security", [
                'action_id' => $this->action->id,
                'url' => $httpRequest['url'],
                'reason' => $e->getMessage(),
            ]);
            $actionService->markFailed($this->action, "Security: {$e->getMessage()}");
            return;
        }

        // Check payload size
        $body = $httpRequest['body'] ?? null;
        if ($body !== null) {
            $bodySize = strlen(json_encode($body) ?: '');
            $maxSize = config('callmelater.http.max_body_size', 1048576);
            if ($bodySize > $maxSize) {
                $actionService->markFailed($this->action, "Request body exceeds maximum size ({$maxSize} bytes)");
                return;
            }
        }

        $startTime = microtime(true);
        $attempt = new DeliveryAttempt([
            'action_id' => $this->action->id,
            'attempt_number' => $this->action->attempt_count + 1,
        ]);

        try {
            $response = $this->makeRequest($httpRequest);
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $attempt->status = DeliveryAttempt::STATUS_SUCCESS;
            $attempt->response_code = $response->status();
            $attempt->response_body = substr($response->body(), 0, 10000); // Truncate large responses
            $attempt->duration_ms = $durationMs;
            $attempt->save();

            $this->action->attempt_count++;
            $this->action->last_attempt_at = now();
            $this->action->save();

            // Check if response indicates success (2xx status)
            if ($response->successful()) {
                $actionService->markExecuted($this->action);
                Log::info("HTTP action executed successfully", [
                    'action_id' => $this->action->id,
                    'status_code' => $response->status(),
                ]);
            } else {
                $this->handleFailure($actionService, $attempt, "HTTP {$response->status()}");
            }
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $attempt->status = DeliveryAttempt::STATUS_FAILED;
            $attempt->error_message = $e->getMessage();
            $attempt->duration_ms = $durationMs;
            $attempt->save();

            $this->action->attempt_count++;
            $this->action->last_attempt_at = now();
            $this->action->save();

            $this->handleFailure($actionService, $attempt, $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $httpRequest
     */
    private function makeRequest(array $httpRequest): \Illuminate\Http\Client\Response
    {
        $method = strtolower($httpRequest['method'] ?? 'POST');
        $url = $httpRequest['url'];
        $headers = $httpRequest['headers'] ?? [];
        $body = $httpRequest['body'] ?? null;

        // Use configured timeout with sensible limits
        $configTimeout = config('callmelater.http.timeout', 30);
        $timeout = min($httpRequest['timeout'] ?? $configTimeout, 120);

        // Add webhook signature if secret is configured
        if ($this->action->webhook_secret) {
            $headers['X-CallMeLater-Signature'] = $this->generateSignature($body);
            $headers['X-CallMeLater-Action-Id'] = $this->action->id;
            $headers['X-CallMeLater-Timestamp'] = (string) time();
        }

        // Build request with security options
        $request = Http::withHeaders($headers)
            ->timeout($timeout)
            ->connectTimeout(10);

        // Handle redirects based on config
        if (! config('callmelater.http.allow_redirects', false)) {
            $request = $request->withOptions(['allow_redirects' => false]);
        } else {
            $maxRedirects = config('callmelater.http.max_redirects', 3);
            $request = $request->withOptions(['allow_redirects' => ['max' => $maxRedirects]]);
        }

        return match ($method) {
            'get' => $request->get($url),
            'post' => $request->post($url, $body),
            'put' => $request->put($url, $body),
            'patch' => $request->patch($url, $body),
            'delete' => $request->delete($url, $body),
            default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };
    }

    private function generateSignature(?array $body): string
    {
        $payload = $body ? json_encode($body) : '';
        return 'sha256=' . hash_hmac('sha256', $payload, $this->action->webhook_secret);
    }

    private function handleFailure(ActionService $actionService, DeliveryAttempt $attempt, string $reason): void
    {
        Log::warning("HTTP action delivery failed", [
            'action_id' => $this->action->id,
            'attempt' => $attempt->attempt_number,
            'reason' => $reason,
        ]);

        if ($this->action->canRetry()) {
            $actionService->scheduleRetry($this->action);
        } else {
            $actionService->markFailed($this->action, "Failed after {$this->action->attempt_count} attempts: {$reason}");
        }
    }
}

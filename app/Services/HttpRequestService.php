<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class HttpRequestService
{
    public function __construct(
        private UrlValidator $urlValidator
    ) {}

    /**
     * Execute an HTTP request and return the result.
     *
     * @param array<string, mixed> $config
     * @return array{success: bool, status_code: int|null, duration_ms: int, error: string|null, body: string|null}
     */
    public function execute(array $config): array
    {
        $startTime = microtime(true);

        try {
            // Validate URL first
            $this->urlValidator->validate($config['url']);

            $response = $this->makeRequest($config);
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            return [
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'duration_ms' => $durationMs,
                'error' => $response->successful() ? null : "HTTP {$response->status()}",
                'body' => substr($response->body(), 0, 1000),
            ];
        } catch (\InvalidArgumentException $e) {
            // URL validation failed
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            return [
                'success' => false,
                'status_code' => null,
                'duration_ms' => $durationMs,
                'error' => $e->getMessage(),
                'body' => null,
            ];
        } catch (ConnectionException $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            return [
                'success' => false,
                'status_code' => null,
                'duration_ms' => $durationMs,
                'error' => $this->friendlyConnectionError($e->getMessage()),
                'body' => null,
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            return [
                'success' => false,
                'status_code' => null,
                'duration_ms' => $durationMs,
                'error' => $e->getMessage(),
                'body' => null,
            ];
        }
    }

    /**
     * Make the actual HTTP request.
     *
     * @param array<string, mixed> $config
     */
    public function makeRequest(array $config, ?string $webhookSecret = null, ?string $actionId = null): Response
    {
        $method = strtolower($config['method'] ?? 'POST');
        $url = $config['url'];
        $headers = $config['headers'] ?? [];
        $body = $config['body'] ?? null;

        // Use configured timeout with sensible limits
        $configTimeout = config('callmelater.http.timeout', 30);
        $timeout = min($config['timeout'] ?? $configTimeout, 120);

        // Add webhook signature if secret is provided
        if ($webhookSecret) {
            $headers['X-CallMeLater-Signature'] = $this->generateSignature($body, $webhookSecret);
            $headers['X-CallMeLater-Action-Id'] = $actionId ?? 'test';
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

    /**
     * Generate HMAC signature for webhook.
     *
     * @param array<string, mixed>|null $body
     */
    public function generateSignature(?array $body, string $secret): string
    {
        $payload = $body ? json_encode($body) : '';

        return 'sha256=' . hash_hmac('sha256', $payload ?: '', $secret);
    }

    /**
     * Convert connection errors to user-friendly messages.
     */
    private function friendlyConnectionError(string $message): string
    {
        if (str_contains($message, 'Could not resolve host')) {
            return 'DNS resolution failed — hostname not found';
        }
        if (str_contains($message, 'Connection refused')) {
            return 'Connection refused — server not accepting connections';
        }
        if (str_contains($message, 'Connection timed out') || str_contains($message, 'timed out')) {
            return 'Connection timed out — server not responding';
        }
        if (str_contains($message, 'SSL') || str_contains($message, 'certificate')) {
            return 'SSL/TLS error — certificate problem';
        }

        return "Connection failed — {$message}";
    }
}

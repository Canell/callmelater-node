<?php

namespace CallMeLater\Laravel;

use CallMeLater\Laravel\Builders\HttpActionBuilder;
use CallMeLater\Laravel\Builders\ReminderBuilder;
use CallMeLater\Laravel\Exceptions\ApiException;
use CallMeLater\Laravel\Exceptions\ConfigurationException;
use CallMeLater\Laravel\Exceptions\SignatureVerificationException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CallMeLater
{
    protected string $apiToken;

    protected string $apiUrl;

    protected ?string $webhookSecret;

    protected ?string $timezone;

    protected array $retryConfig;

    public function __construct(
        string $apiToken,
        string $apiUrl = 'https://callmelater.io',
        ?string $webhookSecret = null,
        ?string $timezone = null,
        array $retryConfig = []
    ) {
        $this->apiToken = $apiToken;
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->webhookSecret = $webhookSecret;
        $this->timezone = $timezone;
        $this->retryConfig = $retryConfig;
    }

    /**
     * Create a new HTTP action builder.
     */
    public function http(string $url): HttpActionBuilder
    {
        return new HttpActionBuilder($this, $url);
    }

    /**
     * Create a new reminder builder.
     */
    public function reminder(string $name): ReminderBuilder
    {
        return new ReminderBuilder($this, $name);
    }

    /**
     * Get an action by ID.
     */
    public function get(string $id): array
    {
        $response = $this->client()->get("/api/v1/actions/{$id}");

        if (! $response->successful()) {
            throw ApiException::fromResponse($response, 'get action');
        }

        return $response->json('data');
    }

    /**
     * List actions with optional filters.
     */
    public function list(array $filters = []): array
    {
        $response = $this->client()->get('/api/v1/actions', $filters);

        if (! $response->successful()) {
            throw ApiException::fromResponse($response, 'list actions');
        }

        return $response->json();
    }

    /**
     * Cancel an action by ID.
     */
    public function cancel(string $id): array
    {
        $response = $this->client()->delete("/api/v1/actions/{$id}");

        if (! $response->successful()) {
            throw ApiException::fromResponse($response, 'cancel action');
        }

        return $response->json() ?? [];
    }

    /**
     * Verify the signature of an incoming webhook request.
     *
     * @throws ConfigurationException if webhook secret is not configured
     * @throws SignatureVerificationException if signature is missing or invalid
     */
    public function verifySignature(Request $request): void
    {
        if (! $this->webhookSecret) {
            throw new ConfigurationException(
                'Webhook secret not configured. Set CALLMELATER_WEBHOOK_SECRET in your .env file.'
            );
        }

        $signature = $request->header('X-CallMeLater-Signature');

        if (! $signature) {
            throw new SignatureVerificationException('Missing webhook signature header');
        }

        $payload = $request->getContent();
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $this->webhookSecret);

        if (! hash_equals($expectedSignature, $signature)) {
            throw new SignatureVerificationException('Invalid webhook signature');
        }
    }

    /**
     * Check if the webhook signature is valid (returns boolean instead of throwing).
     */
    public function isValidSignature(Request $request): bool
    {
        try {
            $this->verifySignature($request);

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Get a webhook handler instance.
     */
    public function webhooks(): WebhookHandler
    {
        return new WebhookHandler($this);
    }

    /**
     * Send an action to the API.
     *
     * @internal Used by builders
     */
    public function sendAction(array $payload): array
    {
        $response = $this->client()->post('/api/v1/actions', $payload);

        if (! $response->successful()) {
            throw ApiException::fromResponse($response, 'create action');
        }

        return $response->json('data');
    }

    /**
     * Get the configured HTTP client.
     */
    protected function client(): PendingRequest
    {
        return Http::baseUrl($this->apiUrl)
            ->withToken($this->apiToken)
            ->acceptJson()
            ->timeout(30);
    }

    /**
     * Get the default timezone.
     */
    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    /**
     * Get the default retry config.
     */
    public function getRetryConfig(): array
    {
        return $this->retryConfig;
    }
}

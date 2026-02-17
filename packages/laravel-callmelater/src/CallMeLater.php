<?php

namespace CallMeLater\Laravel;

use CallMeLater\Laravel\Builders\ChainBuilder;
use CallMeLater\Laravel\Builders\HttpActionBuilder;
use CallMeLater\Laravel\Builders\ReminderBuilder;
use CallMeLater\Laravel\Builders\TemplateBuilder;
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

    // ─── Chains ──────────────────────────────────────────────

    /**
     * Create a new chain builder.
     */
    public function chain(string $name): ChainBuilder
    {
        return new ChainBuilder($this, $name);
    }

    /**
     * Send a chain payload to the API.
     *
     * @internal Used by ChainBuilder
     */
    public function sendChain(array $payload): array
    {
        $response = $this->client()->post('/api/v1/chains', $payload);

        if (! $response->successful()) {
            throw ApiException::fromResponse($response, 'create chain');
        }

        return $response->json('data');
    }

    /**
     * Get a chain by ID.
     */
    public function getChain(string $id): array
    {
        $response = $this->client()->get("/api/v1/chains/{$id}");

        if (! $response->successful()) {
            throw ApiException::fromResponse($response, 'get chain');
        }

        return $response->json('data');
    }

    /**
     * List chains with optional filters.
     */
    public function listChains(array $filters = []): array
    {
        $response = $this->client()->get('/api/v1/chains', $filters);

        if (! $response->successful()) {
            throw ApiException::fromResponse($response, 'list chains');
        }

        return $response->json();
    }

    /**
     * Cancel a chain by ID.
     */
    public function cancelChain(string $id): array
    {
        $response = $this->client()->delete("/api/v1/chains/{$id}");

        if (! $response->successful()) {
            throw ApiException::fromResponse($response, 'cancel chain');
        }

        return $response->json() ?? [];
    }

    // ─── Templates ────────────────────────────────────────────

    /**
     * Create a new template builder.
     */
    public function template(string $name): TemplateBuilder
    {
        return new TemplateBuilder($this, $name);
    }

    /**
     * Send a template payload to the API.
     *
     * @internal Used by TemplateBuilder
     */
    public function sendTemplate(array $payload): array
    {
        $response = $this->client()->post('/api/v1/templates', $payload);

        if (! $response->successful()) {
            throw ApiException::fromResponse($response, 'create template');
        }

        return $response->json('data');
    }

    /**
     * Update a template by ID.
     *
     * @internal Used by TemplateBuilder
     */
    public function updateTemplate(string $id, array $payload): array
    {
        $response = $this->client()->put("/api/v1/templates/{$id}", $payload);

        if (! $response->successful()) {
            throw ApiException::fromResponse($response, 'update template');
        }

        return $response->json('data');
    }

    /**
     * Get a template by ID.
     */
    public function getTemplate(string $id): array
    {
        $response = $this->client()->get("/api/v1/templates/{$id}");

        if (! $response->successful()) {
            throw ApiException::fromResponse($response, 'get template');
        }

        return $response->json('data');
    }

    /**
     * List templates with optional filters.
     */
    public function listTemplates(array $filters = []): array
    {
        $response = $this->client()->get('/api/v1/templates', $filters);

        if (! $response->successful()) {
            throw ApiException::fromResponse($response, 'list templates');
        }

        return $response->json();
    }

    /**
     * Delete a template by ID.
     */
    public function deleteTemplate(string $id): array
    {
        $response = $this->client()->delete("/api/v1/templates/{$id}");

        if (! $response->successful()) {
            throw ApiException::fromResponse($response, 'delete template');
        }

        return $response->json() ?? [];
    }

    /**
     * Regenerate the trigger token for a template.
     */
    public function regenerateTemplateToken(string $id): array
    {
        $response = $this->client()->post("/api/v1/templates/{$id}/regenerate-token");

        if (! $response->successful()) {
            throw ApiException::fromResponse($response, 'regenerate template token');
        }

        return $response->json('data');
    }

    /**
     * Toggle a template's enabled/disabled state.
     */
    public function toggleTemplate(string $id): array
    {
        $response = $this->client()->post("/api/v1/templates/{$id}/toggle-active");

        if (! $response->successful()) {
            throw ApiException::fromResponse($response, 'toggle template');
        }

        return $response->json('data');
    }

    /**
     * Get template usage limits for the current account.
     */
    public function templateLimits(): array
    {
        $response = $this->client()->get('/api/v1/templates/limits');

        if (! $response->successful()) {
            throw ApiException::fromResponse($response, 'get template limits');
        }

        return $response->json('data') ?? $response->json() ?? [];
    }

    // ─── Trigger ──────────────────────────────────────────────

    /**
     * Trigger a template by its token with placeholder values.
     */
    public function trigger(string $token, array $params = []): array
    {
        $response = $this->client()->post("/t/{$token}", $params);

        if (! $response->successful()) {
            throw ApiException::fromResponse($response, 'trigger template');
        }

        return $response->json('data');
    }

    // ─── Actions ──────────────────────────────────────────────

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

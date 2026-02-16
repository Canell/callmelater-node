<?php

namespace CallMeLater\Laravel\Builders;

use CallMeLater\Laravel\CallMeLater;

class TemplateBuilder
{
    protected CallMeLater $client;

    protected string $name;

    protected ?string $description = null;

    protected ?string $type = null;

    protected ?string $mode = null;

    protected ?string $timezone = null;

    protected ?array $requestConfig = null;

    protected ?array $gateConfig = null;

    protected ?int $maxAttempts = null;

    protected ?string $retryStrategy = null;

    protected array $placeholders = [];

    protected ?array $chainSteps = null;

    protected ?string $chainErrorHandling = null;

    protected ?array $coordinationKeys = null;

    protected ?array $coordinationConfig = null;

    public function __construct(CallMeLater $client, string $name)
    {
        $this->client = $client;
        $this->name = $name;
    }

    /**
     * Set the template description.
     */
    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Set the action type ('http', 'reminder', 'chain').
     */
    public function type(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Set the action mode ('immediate', 'gated').
     */
    public function mode(string $mode): self
    {
        $this->mode = $mode;

        return $this;
    }

    /**
     * Set the default timezone.
     */
    public function timezone(string $timezone): self
    {
        $this->timezone = $timezone;

        return $this;
    }

    /**
     * Set the HTTP request configuration.
     */
    public function requestConfig(array $config): self
    {
        $this->requestConfig = $config;

        return $this;
    }

    /**
     * Set the gate configuration (for gated mode).
     */
    public function gateConfig(array $config): self
    {
        $this->gateConfig = $config;

        return $this;
    }

    /**
     * Set the maximum number of retry attempts.
     */
    public function maxAttempts(int $max): self
    {
        $this->maxAttempts = $max;

        return $this;
    }

    /**
     * Set the retry strategy.
     */
    public function retryStrategy(string $strategy): self
    {
        $this->retryStrategy = $strategy;

        return $this;
    }

    /**
     * Add a single placeholder.
     */
    public function placeholder(string $key, bool $required = false, ?string $description = null, mixed $default = null): self
    {
        $placeholder = ['name' => $key, 'required' => $required];

        if ($description !== null) {
            $placeholder['description'] = $description;
        }

        if ($default !== null) {
            $placeholder['default'] = $default;
        }

        $this->placeholders[] = $placeholder;

        return $this;
    }

    /**
     * Set all placeholders at once.
     */
    public function placeholders(array $placeholders): self
    {
        $this->placeholders = $placeholders;

        return $this;
    }

    /**
     * Set chain steps (for chain templates).
     */
    public function chainSteps(array $steps): self
    {
        $this->chainSteps = $steps;

        return $this;
    }

    /**
     * Set chain error handling strategy.
     */
    public function chainErrorHandling(string $strategy): self
    {
        $this->chainErrorHandling = $strategy;

        return $this;
    }

    /**
     * Set coordination keys.
     */
    public function coordinationKeys(array $keys): self
    {
        $this->coordinationKeys = $keys;

        return $this;
    }

    /**
     * Set coordination configuration.
     */
    public function coordinationConfig(array $config): self
    {
        $this->coordinationConfig = $config;

        return $this;
    }

    /**
     * Get the payload array that would be sent to the API.
     */
    public function toArray(): array
    {
        $payload = [
            'name' => $this->name,
        ];

        if ($this->description !== null) {
            $payload['description'] = $this->description;
        }

        if ($this->type !== null) {
            $payload['type'] = $this->type;
        }

        if ($this->mode !== null) {
            $payload['mode'] = $this->mode;
        }

        if ($this->timezone !== null) {
            $payload['timezone'] = $this->timezone;
        }

        if ($this->requestConfig !== null) {
            $payload['request_config'] = $this->requestConfig;
        }

        if ($this->gateConfig !== null) {
            $payload['gate_config'] = $this->gateConfig;
        }

        if ($this->maxAttempts !== null) {
            $payload['max_attempts'] = $this->maxAttempts;
        }

        if ($this->retryStrategy !== null) {
            $payload['retry_strategy'] = $this->retryStrategy;
        }

        if (! empty($this->placeholders)) {
            $payload['placeholders'] = $this->placeholders;
        }

        if ($this->chainSteps !== null) {
            $payload['chain_steps'] = $this->chainSteps;
        }

        if ($this->chainErrorHandling !== null) {
            $payload['chain_error_handling'] = $this->chainErrorHandling;
        }

        if ($this->coordinationKeys !== null) {
            $payload['default_coordination_keys'] = $this->coordinationKeys;
        }

        if ($this->coordinationConfig !== null) {
            $payload['coordination_config'] = $this->coordinationConfig;
        }

        return $payload;
    }

    /**
     * Dump the payload and die (for debugging).
     */
    public function dd(): never
    {
        dd($this->toArray());
    }

    /**
     * Create the template (send to API).
     */
    public function send(): array
    {
        return $this->client->sendTemplate($this->toArray());
    }

    /**
     * Alias for send().
     */
    public function create(): array
    {
        return $this->send();
    }

    /**
     * Update an existing template.
     */
    public function update(string $id): array
    {
        return $this->client->updateTemplate($id, $this->toArray());
    }
}

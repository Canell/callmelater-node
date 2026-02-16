<?php

namespace CallMeLater\Laravel\Builders\Steps;

use CallMeLater\Laravel\Builders\ChainBuilder;

class HttpStepBuilder
{
    protected ChainBuilder $chain;

    protected string $name;

    protected ?string $url = null;

    protected string $method = 'POST';

    protected array $headers = [];

    protected mixed $body = null;

    protected ?string $condition = null;

    protected ?int $maxAttempts = null;

    protected ?string $retryStrategy = null;

    public function __construct(ChainBuilder $chain, string $name)
    {
        $this->chain = $chain;
        $this->name = $name;
    }

    /**
     * Set the request URL.
     */
    public function url(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Set the HTTP method.
     */
    public function method(string $method): self
    {
        $this->method = strtoupper($method);

        return $this;
    }

    public function get(): self
    {
        return $this->method('GET');
    }

    public function post(): self
    {
        return $this->method('POST');
    }

    public function put(): self
    {
        return $this->method('PUT');
    }

    public function patch(): self
    {
        return $this->method('PATCH');
    }

    public function delete(): self
    {
        return $this->method('DELETE');
    }

    /**
     * Set request headers.
     */
    public function headers(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);

        return $this;
    }

    /**
     * Set the request body.
     */
    public function body(mixed $body): self
    {
        $this->body = $body;

        return $this;
    }

    /**
     * Set a condition expression for this step.
     */
    public function condition(string $condition): self
    {
        $this->condition = $condition;

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
     * Set the retry strategy (e.g., 'exponential', 'linear', 'fixed').
     */
    public function retryStrategy(string $strategy): self
    {
        $this->retryStrategy = $strategy;

        return $this;
    }

    /**
     * Convert this step to an array.
     */
    public function toArray(): array
    {
        $step = [
            'type' => 'http_call',
            'name' => $this->name,
            'url' => $this->url,
            'method' => $this->method,
        ];

        if (! empty($this->headers)) {
            $step['headers'] = $this->headers;
        }

        if ($this->body !== null) {
            $step['body'] = $this->body;
        }

        if ($this->condition !== null) {
            $step['condition'] = $this->condition;
        }

        if ($this->maxAttempts !== null) {
            $step['max_attempts'] = $this->maxAttempts;
        }

        if ($this->retryStrategy !== null) {
            $step['retry_strategy'] = $this->retryStrategy;
        }

        return $step;
    }

    /**
     * Finish configuring this step and return to the chain builder.
     */
    public function done(): ChainBuilder
    {
        $this->chain->pushStep($this->toArray());

        return $this->chain;
    }

    /**
     * Alias for done().
     */
    public function add(): ChainBuilder
    {
        return $this->done();
    }
}

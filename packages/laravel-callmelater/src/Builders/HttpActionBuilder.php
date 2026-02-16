<?php

namespace CallMeLater\Laravel\Builders;

use CallMeLater\Laravel\CallMeLater;
use Carbon\Carbon;
use DateTimeInterface;

class HttpActionBuilder
{
    protected CallMeLater $client;

    protected string $url;

    protected string $method = 'POST';

    protected array $headers = [];

    protected mixed $payload = null;

    protected ?string $name = null;

    protected ?string $idempotencyKey = null;

    protected ?string $timezone = null;

    protected array $intent = [];

    protected array $retry = [];

    protected ?string $callbackUrl = null;

    protected array $metadata = [];

    public function __construct(CallMeLater $client, string $url)
    {
        $this->client = $client;
        $this->url = $url;
        $this->timezone = $client->getTimezone();
        $this->retry = $client->getRetryConfig();
    }

    /**
     * Set the HTTP method.
     */
    public function method(string $method): self
    {
        $this->method = strtoupper($method);

        return $this;
    }

    /**
     * Shortcut for GET request.
     */
    public function get(): self
    {
        return $this->method('GET');
    }

    /**
     * Shortcut for POST request.
     */
    public function post(): self
    {
        return $this->method('POST');
    }

    /**
     * Shortcut for PUT request.
     */
    public function put(): self
    {
        return $this->method('PUT');
    }

    /**
     * Shortcut for PATCH request.
     */
    public function patch(): self
    {
        return $this->method('PATCH');
    }

    /**
     * Shortcut for DELETE request.
     */
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
     * Set a single header.
     */
    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * Set the request payload/body.
     */
    public function payload(mixed $payload): self
    {
        $this->payload = $payload;

        return $this;
    }

    /**
     * Alias for payload().
     */
    public function body(mixed $body): self
    {
        return $this->payload($body);
    }

    /**
     * Set a friendly name for the action.
     */
    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Set an idempotency key.
     */
    public function idempotencyKey(string $key): self
    {
        $this->idempotencyKey = $key;

        return $this;
    }

    /**
     * Set the timezone for scheduling.
     */
    public function timezone(string $timezone): self
    {
        $this->timezone = $timezone;

        return $this;
    }

    /**
     * Schedule at a specific time.
     */
    public function at(DateTimeInterface|Carbon|string $time): self
    {
        if ($time instanceof DateTimeInterface) {
            $this->intent['type'] = 'datetime';
            $this->intent['value'] = $time->format('Y-m-d H:i:s');
        } elseif (is_string($time)) {
            // Check if it's a preset
            $presets = ['tomorrow', 'next_week', 'next_monday', 'next_tuesday',
                'next_wednesday', 'next_thursday', 'next_friday', 'end_of_day',
                'end_of_week', 'end_of_month'];

            if (in_array($time, $presets)) {
                $this->intent['type'] = 'preset';
                $this->intent['value'] = $time;
            } else {
                // Try to parse as datetime string
                $this->intent['type'] = 'datetime';
                $this->intent['value'] = $time;
            }
        }

        return $this;
    }

    /**
     * Schedule after a delay.
     */
    public function delay(int $amount, string $unit = 'minutes'): self
    {
        $this->intent['type'] = 'relative';
        $this->intent['value'] = $amount;
        $this->intent['unit'] = $unit;

        return $this;
    }

    /**
     * Schedule in X minutes.
     */
    public function inMinutes(int $minutes): self
    {
        return $this->delay($minutes, 'minutes');
    }

    /**
     * Schedule in X hours.
     */
    public function inHours(int $hours): self
    {
        return $this->delay($hours, 'hours');
    }

    /**
     * Schedule in X days.
     */
    public function inDays(int $days): self
    {
        return $this->delay($days, 'days');
    }

    /**
     * Set the retry policy.
     */
    public function retry(int $maxAttempts, string $backoff = 'exponential', int $initialDelay = 60): self
    {
        $this->retry = [
            'max_attempts' => $maxAttempts,
            'backoff' => $backoff,
            'initial_delay' => $initialDelay,
        ];

        return $this;
    }

    /**
     * Disable retries.
     */
    public function noRetry(): self
    {
        $this->retry = ['max_attempts' => 1];

        return $this;
    }

    /**
     * Set a callback URL for completion/failure notifications.
     */
    public function callback(string $url): self
    {
        $this->callbackUrl = $url;

        return $this;
    }

    /**
     * Alias for callback().
     */
    public function onComplete(string $url): self
    {
        return $this->callback($url);
    }

    /**
     * Add metadata.
     */
    public function metadata(array $metadata): self
    {
        $this->metadata = array_merge($this->metadata, $metadata);

        return $this;
    }

    /**
     * Add a single metadata key.
     */
    public function meta(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;

        return $this;
    }

    /**
     * Send the action to CallMeLater.
     */
    public function send(): array
    {
        $payload = [
            'type' => 'http',
            'http' => [
                'url' => $this->url,
                'method' => $this->method,
            ],
        ];

        if (! empty($this->headers)) {
            $payload['http']['headers'] = $this->headers;
        }

        if ($this->payload !== null) {
            $payload['http']['body'] = $this->payload;
        }

        if ($this->name) {
            $payload['name'] = $this->name;
        }

        if ($this->idempotencyKey) {
            $payload['idempotency_key'] = $this->idempotencyKey;
        }

        if (! empty($this->intent)) {
            $payload['intent'] = $this->intent;
            if ($this->timezone) {
                $payload['intent']['timezone'] = $this->timezone;
            }
        }

        if (! empty($this->retry)) {
            $payload['retry'] = $this->retry;
        }

        if ($this->callbackUrl) {
            $payload['callback_url'] = $this->callbackUrl;
        }

        if (! empty($this->metadata)) {
            $payload['metadata'] = $this->metadata;
        }

        return $this->client->sendAction($payload);
    }

    /**
     * Alias for send().
     */
    public function dispatch(): array
    {
        return $this->send();
    }
}

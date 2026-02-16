<?php

namespace CallMeLater\Laravel\Builders\Steps;

use CallMeLater\Laravel\Builders\ChainBuilder;

class GateStepBuilder
{
    protected ChainBuilder $chain;

    protected string $name;

    protected ?string $message = null;

    protected array $recipients = [];

    protected ?int $maxSnoozes = null;

    protected ?string $confirmationMode = null;

    protected ?string $timeout = null;

    protected ?string $onTimeout = null;

    protected ?string $condition = null;

    public function __construct(ChainBuilder $chain, string $name)
    {
        $this->chain = $chain;
        $this->name = $name;
    }

    /**
     * Set the gate message.
     */
    public function message(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    /**
     * Add an email recipient.
     */
    public function to(string $email): self
    {
        $this->recipients[] = "email:{$email}";

        return $this;
    }

    /**
     * Add multiple email recipients.
     */
    public function toMany(array $emails): self
    {
        foreach ($emails as $email) {
            $this->to($email);
        }

        return $this;
    }

    /**
     * Add a raw recipient URI.
     */
    public function toRecipient(string $recipientUri): self
    {
        $this->recipients[] = $recipientUri;

        return $this;
    }

    /**
     * Set the maximum number of snoozes.
     */
    public function maxSnoozes(int $max): self
    {
        $this->maxSnoozes = $max;

        return $this;
    }

    /**
     * Require all recipients to respond.
     */
    public function requireAll(): self
    {
        $this->confirmationMode = 'all_required';

        return $this;
    }

    /**
     * Complete on first response.
     */
    public function firstResponse(): self
    {
        $this->confirmationMode = 'first_response';

        return $this;
    }

    /**
     * Set the timeout duration (e.g., '2d', '24h').
     */
    public function timeout(string $timeout): self
    {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * Set what happens when the gate times out ('cancel', 'continue', 'fail').
     */
    public function onTimeout(string $action): self
    {
        $this->onTimeout = $action;

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
     * Convert this step to an array.
     */
    public function toArray(): array
    {
        $step = [
            'type' => 'gated',
            'name' => $this->name,
            'gate' => [],
        ];

        if ($this->message !== null) {
            $step['gate']['message'] = $this->message;
        }

        if (! empty($this->recipients)) {
            $step['gate']['recipients'] = $this->recipients;
        }

        if ($this->maxSnoozes !== null) {
            $step['gate']['max_snoozes'] = $this->maxSnoozes;
        }

        if ($this->confirmationMode !== null) {
            $step['gate']['confirmation_mode'] = $this->confirmationMode;
        }

        if ($this->timeout !== null) {
            $step['gate']['timeout'] = $this->timeout;
        }

        if ($this->onTimeout !== null) {
            $step['gate']['on_timeout'] = $this->onTimeout;
        }

        if ($this->condition !== null) {
            $step['condition'] = $this->condition;
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

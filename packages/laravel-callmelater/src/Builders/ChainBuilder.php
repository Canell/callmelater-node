<?php

namespace CallMeLater\Laravel\Builders;

use CallMeLater\Laravel\Builders\Steps\DelayStepBuilder;
use CallMeLater\Laravel\Builders\Steps\GateStepBuilder;
use CallMeLater\Laravel\Builders\Steps\HttpStepBuilder;
use CallMeLater\Laravel\CallMeLater;

class ChainBuilder
{
    protected CallMeLater $client;

    protected string $name;

    protected array $input = [];

    protected array $steps = [];

    protected ?string $errorHandling = null;

    public function __construct(CallMeLater $client, string $name)
    {
        $this->client = $client;
        $this->name = $name;
    }

    /**
     * Set chain input data (available as {{input.*}} in steps).
     */
    public function input(array $input): self
    {
        $this->input = $input;

        return $this;
    }

    /**
     * Set the error handling strategy ('fail_chain', 'skip_step', 'continue').
     */
    public function errorHandling(string $strategy): self
    {
        $this->errorHandling = $strategy;

        return $this;
    }

    /**
     * Add an HTTP step to the chain.
     */
    public function addHttpStep(string $name): HttpStepBuilder
    {
        return new HttpStepBuilder($this, $name);
    }

    /**
     * Add a gate (approval/reminder) step to the chain.
     */
    public function addGateStep(string $name): GateStepBuilder
    {
        return new GateStepBuilder($this, $name);
    }

    /**
     * Add a delay step to the chain.
     */
    public function addDelayStep(string $name): DelayStepBuilder
    {
        return new DelayStepBuilder($this, $name);
    }

    /**
     * Push a completed step array onto the steps list.
     *
     * @internal Called by step builders' done() method.
     */
    public function pushStep(array $step): void
    {
        $this->steps[] = $step;
    }

    /**
     * Get the payload array that would be sent to the API.
     */
    public function toArray(): array
    {
        $payload = [
            'name' => $this->name,
            'steps' => $this->steps,
        ];

        if (! empty($this->input)) {
            $payload['input'] = $this->input;
        }

        if ($this->errorHandling !== null) {
            $payload['error_handling'] = $this->errorHandling;
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
     * Send the chain to CallMeLater.
     */
    public function send(): array
    {
        return $this->client->sendChain($this->toArray());
    }
}

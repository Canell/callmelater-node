<?php

namespace CallMeLater\Laravel\Builders\Steps;

use CallMeLater\Laravel\Builders\ChainBuilder;

class DelayStepBuilder
{
    protected ChainBuilder $chain;

    protected string $name;

    protected ?string $duration = null;

    protected ?string $condition = null;

    public function __construct(ChainBuilder $chain, string $name)
    {
        $this->chain = $chain;
        $this->name = $name;
    }

    /**
     * Set a raw duration string (e.g., '30m', '2h', '1d').
     */
    public function duration(string $duration): self
    {
        $this->duration = $duration;

        return $this;
    }

    /**
     * Set delay in minutes.
     */
    public function minutes(int $minutes): self
    {
        $this->duration = "{$minutes}m";

        return $this;
    }

    /**
     * Set delay in hours.
     */
    public function hours(int $hours): self
    {
        $this->duration = "{$hours}h";

        return $this;
    }

    /**
     * Set delay in days.
     */
    public function days(int $days): self
    {
        $this->duration = "{$days}d";

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
            'type' => 'delay',
            'name' => $this->name,
        ];

        if ($this->duration !== null) {
            $step['delay'] = $this->duration;
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

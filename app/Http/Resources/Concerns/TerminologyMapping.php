<?php

namespace App\Http\Resources\Concerns;

/**
 * Shared terminology mapping for API resources.
 *
 * Maps internal terms to user-friendly terms while maintaining
 * backwards compatibility by outputting both old and new terms.
 */
trait TerminologyMapping
{
    /**
     * Map internal action mode to user-friendly type.
     *
     * @param  string  $mode  Internal mode (immediate, gated)
     * @return string User-friendly type (webhook, approval)
     */
    protected function mapModeToType(string $mode): string
    {
        return match ($mode) {
            'immediate' => 'webhook',
            'gated' => 'approval',
            default => $mode,
        };
    }

    /**
     * Map internal step type to user-friendly type.
     *
     * @param  string  $type  Internal type (http_call, gated, delay)
     * @return string User-friendly type (webhook, approval, wait)
     */
    protected function mapStepType(string $type): string
    {
        return match ($type) {
            'http_call' => 'webhook',
            'gated' => 'approval',
            'delay' => 'wait',
            default => $type,
        };
    }

    /**
     * Map internal resolution status to user-friendly status.
     *
     * @param  string  $status  Internal status
     * @return string User-friendly status
     */
    protected function mapStatus(string $status): string
    {
        return match ($status) {
            'pending_resolution' => 'scheduled',
            default => $status,
        };
    }
}

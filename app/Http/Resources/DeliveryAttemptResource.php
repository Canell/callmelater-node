<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\DeliveryAttempt
 */
class DeliveryAttemptResource extends JsonResource
{
    private const MAX_RESPONSE_SIZE = 10000;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $responseBody = $this->response_body;
        $isTruncated = $responseBody !== null && strlen($responseBody) >= self::MAX_RESPONSE_SIZE;

        return [
            'id' => $this->id,
            'attempt_number' => $this->attempt_number,
            'status' => $this->status,
            'response_code' => $this->response_code,
            'response_preview' => $responseBody,
            'response_truncated' => $isTruncated,
            'error_message' => $this->error_message,
            'duration_ms' => $this->duration_ms,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}

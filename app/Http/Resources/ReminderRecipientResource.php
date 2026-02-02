<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ReminderRecipient
 */
class ReminderRecipientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'status' => $this->status,
            'responded_at' => $this->responded_at?->toIso8601String(),
            'response_comment' => $this->response_comment,
            'created_at' => $this->created_at->toIso8601String(),
            'display_name' => $this->display_name,
            'contact' => $this->whenLoaded('contact', fn () => $this->contact ? [
                'id' => $this->contact->id,
                'full_name' => $this->contact->full_name,
                'email' => $this->contact->email,
                'phone' => $this->contact->phone,
            ] : null),
        ];
    }
}

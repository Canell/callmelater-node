<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ReminderRecipient
 */
class ResponseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action_id' => $this->action_id,
            'action_name' => $this->action->name,
            'responder' => $this->display_name,
            'responder_email' => $this->email,
            'response_type' => $this->status,
            'comment' => $this->response_comment,
            'responded_at' => $this->responded_at?->toIso8601String(),
            'contact' => $this->whenLoaded('contact', fn () => $this->contact ? [
                'id' => $this->contact->id,
                'full_name' => $this->contact->full_name,
            ] : null),
        ];
    }
}

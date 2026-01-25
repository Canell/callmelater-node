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
            'created_at' => $this->created_at->toIso8601String(),
            'display_name' => $this->display_name,
            'team_member' => $this->whenLoaded('teamMember', fn () => $this->teamMember ? [
                'id' => $this->teamMember->id,
                'full_name' => $this->teamMember->full_name,
                'email' => $this->teamMember->email,
                'phone' => $this->teamMember->phone,
            ] : null),
        ];
    }
}

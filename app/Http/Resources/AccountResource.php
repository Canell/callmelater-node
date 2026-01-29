<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Account
 */
class AccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'plan' => $this->getPlan(),
            'logo_url' => $this->logo_url,
            'brand_color' => $this->brand_color,
            'owner' => [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
                'email' => $this->owner->email,
            ],
            'members' => $this->whenLoaded('members', function () {
                return $this->members->map(fn ($member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => $member->pivot->role, // @phpstan-ignore-line
                    'joined_at' => $member->pivot->created_at?->toIso8601String(), // @phpstan-ignore-line
                ]);
            }),
            'member_count' => $this->members->count(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}

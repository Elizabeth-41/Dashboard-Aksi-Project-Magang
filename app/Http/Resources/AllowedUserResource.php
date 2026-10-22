<?php

namespace App\Http\Resources;

use App\Models\AllowedUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AllowedUser
 */
class AllowedUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'dinas_id' => $this->dinas_id,
            'dinas' => DinasResource::make($this->whenLoaded('dinas')),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'invited_by' => $this->invited_by,
            'inviter' => UserResource::make($this->whenLoaded('inviter')),
            'invited_at' => $this->invited_at?->toIso8601String(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

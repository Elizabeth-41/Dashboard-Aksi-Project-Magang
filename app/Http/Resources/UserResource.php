<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'dinas_id' => $this->dinas_id,
            'dinas' => DinasResource::make($this->whenLoaded('dinas')),
            'two_factor_enabled' => $this->hasTwoFactorEnabled(),
            'two_factor_required' => $this->role->requiresTwoFactor(),
        ];
    }
}

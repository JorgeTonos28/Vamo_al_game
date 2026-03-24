<?php

namespace App\Http\Resources\V1;

use App\Enums\AccountRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'name' => $this->name,
            'document_id' => $this->document_id,
            'phone' => $this->phone,
            'address' => $this->address,
            'email' => $this->email,
            'avatar' => $this->avatar,
            'account_role' => $this->account_role?->value,
            'account_role_label' => $this->account_role?->label(),
            'is_general_admin' => $this->account_role === AccountRole::GeneralAdmin,
            'active_league_id' => $this->active_league_id,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'invited_at' => $this->invited_at?->toIso8601String(),
            'onboarded_at' => $this->onboarded_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

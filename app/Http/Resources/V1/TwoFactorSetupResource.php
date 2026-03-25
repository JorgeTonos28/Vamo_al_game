<?php

namespace App\Http\Resources\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

/** @mixin User */
class TwoFactorSetupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'enabled' => $this->hasEnabledTwoFactorAuthentication(),
            'confirmed' => $this->two_factor_confirmed_at !== null,
            'pending_setup' => $this->two_factor_secret !== null
                && ! $this->hasEnabledTwoFactorAuthentication(),
            'can_manage' => Features::canManageTwoFactorAuthentication(),
            'requires_confirmation' => Features::optionEnabled(
                Features::twoFactorAuthentication(),
                'confirm',
            ),
            'qr_code_svg' => $this->twoFactorQrCodeSvg(),
            'qr_code_url' => $this->twoFactorQrCodeUrl(),
            'secret_key' => Fortify::currentEncrypter()->decrypt($this->two_factor_secret),
        ];
    }
}

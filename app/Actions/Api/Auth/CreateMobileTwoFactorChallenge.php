<?php

namespace App\Actions\Api\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CreateMobileTwoFactorChallenge
{
    public function handle(User $user, string $deviceName): string
    {
        $challenge = Str::random(64);

        Cache::put($this->cacheKey($challenge), [
            'user_id' => $user->id,
            'device_name' => $deviceName,
        ], now()->addMinutes(5));

        return $challenge;
    }

    private function cacheKey(string $challenge): string
    {
        return "mobile-two-factor-challenge:{$challenge}";
    }
}

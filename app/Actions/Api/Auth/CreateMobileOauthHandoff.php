<?php

namespace App\Actions\Api\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CreateMobileOauthHandoff
{
    public function handle(User $user, string $deviceName): string
    {
        $handoff = Str::random(64);

        Cache::put($this->cacheKey($handoff), [
            'user_id' => $user->id,
            'device_name' => $deviceName,
        ], now()->addMinutes(5));

        return $handoff;
    }

    private function cacheKey(string $handoff): string
    {
        return "mobile-oauth-handoff:{$handoff}";
    }
}

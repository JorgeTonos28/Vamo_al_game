<?php

namespace App\Actions\Api\Auth;

use Illuminate\Support\Facades\Cache;

class GetMobileTwoFactorChallenge
{
    /**
     * @return array{user_id: int, device_name: string}|null
     */
    public function handle(string $challenge): ?array
    {
        $payload = Cache::get($this->cacheKey($challenge));

        return is_array($payload) ? $payload : null;
    }

    private function cacheKey(string $challenge): string
    {
        return "mobile-two-factor-challenge:{$challenge}";
    }
}

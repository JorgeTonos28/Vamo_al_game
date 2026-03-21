<?php

namespace App\Actions\Api\Auth;

use Illuminate\Support\Facades\Cache;

class ForgetMobileTwoFactorChallenge
{
    public function handle(string $challenge): void
    {
        Cache::forget($this->cacheKey($challenge));
    }

    private function cacheKey(string $challenge): string
    {
        return "mobile-two-factor-challenge:{$challenge}";
    }
}

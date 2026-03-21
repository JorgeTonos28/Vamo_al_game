<?php

namespace App\Actions\Api\Auth;

use Illuminate\Support\Facades\Cache;

class ConsumeMobileOauthHandoff
{
    /**
     * @return array{user_id: int, device_name: string}|null
     */
    public function handle(string $handoff): ?array
    {
        $key = $this->cacheKey($handoff);
        $payload = Cache::get($key);

        if (! is_array($payload)) {
            return null;
        }

        Cache::forget($key);

        return $payload;
    }

    private function cacheKey(string $handoff): string
    {
        return "mobile-oauth-handoff:{$handoff}";
    }
}

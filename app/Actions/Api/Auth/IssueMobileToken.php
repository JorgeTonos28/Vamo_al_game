<?php

namespace App\Actions\Api\Auth;

use App\Models\User;
use Laravel\Sanctum\NewAccessToken;

class IssueMobileToken
{
    public function handle(User $user, string $deviceName): NewAccessToken
    {
        $expiresAt = config('sanctum.expiration')
            ? now()->addMinutes((int) config('sanctum.expiration'))
            : null;

        return $user->createToken($deviceName, ['*'], $expiresAt);
    }
}

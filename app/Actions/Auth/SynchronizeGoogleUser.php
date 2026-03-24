<?php

namespace App\Actions\Auth;

use App\Enums\AccountRole;
use App\Models\User;
use App\Support\UserName;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class SynchronizeGoogleUser
{
    public function handle(SocialiteUser $googleUser): User
    {
        $parsedName = UserName::fromFullName(
            $googleUser->getName() ?: $googleUser->getNickname() ?: 'Google User',
        );

        $user = User::query()
            ->where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if (! $user) {
            return User::create([
                'first_name' => $parsedName['first_name'],
                'last_name' => $parsedName['last_name'],
                'name' => $parsedName['name'],
                'email' => $googleUser->getEmail(),
                'password' => Str::password(32),
                'account_role' => AccountRole::Guest,
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
                'invited_at' => now(),
                'onboarded_at' => now(),
            ]);
        }

        $user->forceFill([
            'google_id' => $googleUser->getId(),
            'avatar' => $googleUser->getAvatar() ?: $user->avatar,
            'first_name' => $user->first_name ?: $parsedName['first_name'],
            'last_name' => $user->last_name ?: $parsedName['last_name'],
            'name' => $user->name ?: $parsedName['name'],
        ])->save();

        return $user;
    }
}

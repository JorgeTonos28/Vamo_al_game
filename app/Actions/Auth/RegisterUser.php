<?php

namespace App\Actions\Auth;

use App\Enums\AccountRole;
use App\Models\User;
use App\Support\UserName;

class RegisterUser
{
    /**
     * @param  array{name: string, email: string, password: string}  $validated
     */
    public function handle(array $validated): User
    {
        $parsedName = UserName::fromFullName($validated['name']);

        return User::create([
            'first_name' => $parsedName['first_name'],
            'last_name' => $parsedName['last_name'],
            'name' => $parsedName['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'account_role' => AccountRole::Guest,
            'invited_at' => now(),
            'onboarded_at' => now(),
        ]);
    }
}

<?php

namespace App\Actions\Auth;

use App\Models\User;

class RegisterUser
{
    /**
     * @param  array{name: string, email: string, password: string}  $validated
     */
    public function handle(array $validated): User
    {
        return User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);
    }
}

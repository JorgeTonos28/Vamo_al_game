<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Support\UserName;

class UpdateUserProfile
{
    /**
     * @param  array{name: string, email: string}  $validated
     */
    public function handle(User $user, array $validated): User
    {
        $parsedName = UserName::fromFullName($validated['name']);

        $user->fill([
            'first_name' => $parsedName['first_name'],
            'last_name' => $parsedName['last_name'],
            'name' => $parsedName['name'],
            'email' => $validated['email'],
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return $user->fresh();
    }
}

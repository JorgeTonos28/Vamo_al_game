<?php

namespace App\Actions\Auth;

use App\Models\User;

class UpdateUserProfile
{
    /**
     * @param  array{name: string, email: string}  $validated
     */
    public function handle(User $user, array $validated): User
    {
        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return $user->fresh();
    }
}

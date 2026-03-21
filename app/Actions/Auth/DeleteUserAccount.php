<?php

namespace App\Actions\Auth;

use App\Models\User;

class DeleteUserAccount
{
    public function handle(User $user): void
    {
        $user->tokens()->delete();
        $user->delete();
    }
}

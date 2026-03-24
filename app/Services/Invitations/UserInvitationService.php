<?php

namespace App\Services\Invitations;

use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Support\Str;

class UserInvitationService
{
    /**
     * @return array{invitation: UserInvitation, token: string}
     */
    public function issue(User $user): array
    {
        $plainToken = Str::random(64);

        $invitation = UserInvitation::query()->updateOrCreate([
            'user_id' => $user->id,
        ], [
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
            'last_sent_at' => now(),
        ]);

        return [
            'invitation' => $invitation,
            'token' => $plainToken,
        ];
    }

    public function isValid(UserInvitation $invitation, string $token): bool
    {
        if ($token === '') {
            return false;
        }

        if ($invitation->accepted_at !== null || $invitation->expires_at->isPast()) {
            return false;
        }

        return hash_equals($invitation->token_hash, hash('sha256', $token));
    }

    public function accept(UserInvitation $invitation): void
    {
        $invitation->forceFill([
            'accepted_at' => now(),
        ])->save();
    }
}

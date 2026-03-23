<?php

namespace App\Actions\Admin;

use App\Enums\AccountRole;
use App\Models\User;
use App\Notifications\AppInvitationNotification;
use App\Services\Invitations\UserInvitationService;
use App\Support\UserName;
use Illuminate\Support\Str;

class InviteUser
{
    public function __construct(
        private readonly UserInvitationService $userInvitationService,
    ) {}

    /**
     * @param  array{
     *     first_name: string,
     *     last_name: string,
     *     document_id?: string|null,
     *     phone?: string|null,
     *     address?: string|null,
     *     email: string,
     *     account_role?: string|null
     * }  $validated
     */
    public function handle(User $inviter, array $validated): User
    {
        $role = filled($validated['account_role'] ?? null)
            ? AccountRole::from($validated['account_role'])
            : AccountRole::Guest;

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'name' => UserName::displayName(
                $validated['first_name'],
                $validated['last_name'],
            ),
            'document_id' => $validated['document_id'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'email' => $validated['email'],
            'password' => Str::password(32),
            'account_role' => $role,
            'invited_by_user_id' => $inviter->id,
            'invited_at' => now(),
            'onboarded_at' => null,
        ]);

        $issuedInvitation = $this->userInvitationService->issue($user);
        $user->notify(new AppInvitationNotification(
            $issuedInvitation['invitation'],
            $issuedInvitation['token'],
        ));

        return $user->fresh();
    }
}

<?php

namespace App\Actions\Admin;

use App\Enums\AccountRole;
use App\Enums\LeagueMembershipRole;
use App\Models\League;
use App\Models\User;
use App\Notifications\AppInvitationNotification;
use App\Services\Invitations\UserInvitationService;
use App\Services\LeagueMemberships\LeagueMembershipManager;
use App\Support\UserName;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InviteUser
{
    public function __construct(
        private readonly UserInvitationService $userInvitationService,
        private readonly LeagueMembershipManager $membershipManager,
    ) {}

    /**
     * @param  array{
     *     first_name: string,
     *     last_name: string,
     *     document_id?: string|null,
     *     phone?: string|null,
     *     address?: string|null,
     *     email: string,
     *     account_role?: string|null,
     *     league_id?: int|null
     * }  $validated
     */
    public function handle(User $inviter, array $validated): User
    {
        $role = filled($validated['account_role'] ?? null)
            ? AccountRole::from($validated['account_role'])
            : AccountRole::Guest;

        return DB::transaction(function () use ($inviter, $role, $validated): User {
            $user = User::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'name' => UserName::displayName(
                    $validated['first_name'],
                    $validated['last_name'],
                ),
                'document_id' => blank($validated['document_id'] ?? null) ? null : $validated['document_id'],
                'phone' => blank($validated['phone'] ?? null) ? null : $validated['phone'],
                'address' => blank($validated['address'] ?? null) ? null : $validated['address'],
                'email' => $validated['email'],
                'password' => Str::password(32),
                'account_role' => $role,
                'invited_by_user_id' => $inviter->id,
                'invited_at' => now(),
                'onboarded_at' => null,
            ]);

            if (filled($validated['league_id'] ?? null) && $role !== AccountRole::Guest && $role !== AccountRole::GeneralAdmin) {
                $membershipRole = $role === AccountRole::LeagueAdmin
                    ? LeagueMembershipRole::Admin
                    : LeagueMembershipRole::Member;

                /** @var League $league */
                $league = League::query()->findOrFail((int) $validated['league_id']);

                $this->membershipManager->assign(
                    $user,
                    $league,
                    $membershipRole,
                    $inviter,
                );
            }

            $issuedInvitation = $this->userInvitationService->issue($user);
            $user->notify(new AppInvitationNotification(
                $issuedInvitation['invitation'],
                $issuedInvitation['token'],
            ));

            return $user->fresh(['invitation']);
        });
    }
}

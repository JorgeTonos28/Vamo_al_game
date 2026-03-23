<?php

namespace App\Services\CommandCenter;

use App\Enums\AccountRole;
use App\Models\User;

class CommandCenterUserDirectoryService
{
    /**
     * @return array{
     *     role_options: array<int, array{value: string, label: string}>,
     *     users: array<int, array{
     *         id: int,
     *         name: string,
     *         email: string,
     *         account_role: string|null,
     *         account_role_label: string|null,
     *         league_memberships_count: int,
     *         has_completed_onboarding: bool,
     *         invited_at: string|null,
     *         created_at: string|null
     *     }>
     * }
     */
    public function payload(): array
    {
        return [
            'role_options' => collect(AccountRole::cases())
                ->map(fn (AccountRole $role): array => [
                    'value' => $role->value,
                    'label' => $role->label(),
                ])
                ->values()
                ->all(),
            'users' => User::query()
                ->withCount('leagueMemberships')
                ->latest()
                ->get()
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'account_role' => $user->account_role?->value,
                    'account_role_label' => $user->account_role?->label(),
                    'league_memberships_count' => $user->league_memberships_count,
                    'has_completed_onboarding' => $user->hasCompletedOnboarding(),
                    'invited_at' => $user->invited_at?->toDateTimeString(),
                    'created_at' => $user->created_at?->toDateTimeString(),
                ])
                ->all(),
        ];
    }
}

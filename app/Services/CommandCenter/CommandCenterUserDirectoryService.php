<?php

namespace App\Services\CommandCenter;

use App\Enums\AccountRole;
use App\Enums\LeagueMembershipRole;
use App\Models\League;
use App\Models\User;

class CommandCenterUserDirectoryService
{
    /**
     * @return array{
     *     role_options: array<int, array{value: string, label: string}>,
     *     league_role_options: array<int, array{value: string, label: string}>,
     *     league_options: array<int, array{id: int, name: string, slug: string}>,
     *     users: array<int, array{
     *         id: int,
     *         name: string,
     *         email: string,
     *         account_role: string|null,
     *         account_role_label: string|null,
     *         league_memberships_count: int,
     *         active_league_id: int|null,
     *         memberships: array<int, array{id: int, league_id: int, league_name: string, league_slug: string, role: string, role_label: string, is_active: bool}>,
     *         can_assign_leagues: bool,
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
            'league_role_options' => collect([
                LeagueMembershipRole::Admin,
                LeagueMembershipRole::Member,
            ])
                ->map(fn (LeagueMembershipRole $role): array => [
                    'value' => $role->value,
                    'label' => $role->label(),
                ])
                ->values()
                ->all(),
            'league_options' => League::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'slug'])
                ->map(fn (League $league): array => [
                    'id' => $league->id,
                    'name' => $league->name,
                    'slug' => $league->slug,
                ])
                ->all(),
            'users' => User::query()
                ->with(['leagueMemberships.league'])
                ->withCount('leagueMemberships')
                ->latest()
                ->get()
                ->map(fn (User $user): array => $this->userRow($user))
                ->all(),
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     email: string,
     *     account_role: string|null,
     *     account_role_label: string|null,
     *     league_memberships_count: int,
     *     active_league_id: int|null,
     *     memberships: array<int, array{id: int, league_id: int, league_name: string, league_slug: string, role: string, role_label: string, is_active: bool}>,
     *     can_assign_leagues: bool,
     *     has_completed_onboarding: bool,
     *     invited_at: string|null,
     *     created_at: string|null
     * }
     */
    public function userRow(User $user): array
    {
        $user->loadMissing('leagueMemberships.league');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'account_role' => $user->account_role?->value,
            'account_role_label' => $user->account_role?->label(),
            'league_memberships_count' => $user->leagueMemberships->count(),
            'active_league_id' => $user->active_league_id,
            'memberships' => $user->leagueMemberships
                ->sortBy(fn ($membership) => $membership->role?->sortOrder() ?? 99)
                ->map(fn ($membership): array => [
                    'id' => $membership->id,
                    'league_id' => $membership->league_id,
                    'league_name' => $membership->league?->name ?? 'Liga sin nombre',
                    'league_slug' => $membership->league?->slug ?? '',
                    'role' => $membership->role->value,
                    'role_label' => $membership->role->label(),
                    'is_active' => (bool) ($membership->league?->is_active ?? false),
                ])
                ->values()
                ->all(),
            'can_assign_leagues' => ! $user->isGeneralAdmin(),
            'has_completed_onboarding' => $user->hasCompletedOnboarding(),
            'invited_at' => $user->invited_at?->toDateTimeString(),
            'created_at' => $user->created_at?->toDateTimeString(),
        ];
    }
}

<?php

namespace App\Services\Tenancy;

use App\Enums\LeagueMembershipRole;
use App\Models\League;
use App\Models\User;
use Illuminate\Support\Collection;

class LeagueContextResolver
{
    /**
     * @return Collection<int, array{id: int, name: string, slug: string, role: string, role_label: string, is_active: bool}>
     */
    public function leaguesFor(User $user): Collection
    {
        if ($user->isGeneralAdmin()) {
            return collect();
        }

        return League::query()
            ->select([
                'leagues.id',
                'leagues.name',
                'leagues.slug',
                'leagues.is_active',
                'league_memberships.role',
            ])
            ->join('league_memberships', 'league_memberships.league_id', '=', 'leagues.id')
            ->where('league_memberships.user_id', $user->id)
            ->orderByRaw(
                'case when league_memberships.role = ? then 0 else 1 end',
                [LeagueMembershipRole::Admin->value],
            )
            ->orderByDesc('leagues.is_active')
            ->orderBy('leagues.name')
            ->get()
            ->map(fn (League $league): array => [
                'id' => $league->id,
                'name' => $league->name,
                'slug' => $league->slug,
                'role' => (string) $league->getAttribute('role'),
                'role_label' => LeagueMembershipRole::from((string) $league->getAttribute('role'))->label(),
                'is_active' => (bool) $league->getAttribute('is_active'),
            ]);
    }

    /**
     * @return array{id: int, name: string, slug: string, role: string, role_label: string, is_active: bool}|null
     */
    public function activeLeagueFor(User $user): ?array
    {
        $leagues = $this->leaguesFor($user);

        if ($leagues->isEmpty()) {
            return null;
        }

        return $leagues->firstWhere('id', $user->active_league_id)
            ?? $leagues->firstWhere('is_active', true)
            ?? $leagues->first();
    }

    public function hasBlockedAccess(User $user): bool
    {
        if ($user->isGeneralAdmin()) {
            return false;
        }

        if (! $user->leagueMemberships()->exists()) {
            return false;
        }

        $activeLeague = $this->activeLeagueFor($user);

        return $activeLeague !== null && ! $activeLeague['is_active'];
    }

    public function switchActiveLeague(User $user, int $leagueId): bool
    {
        $league = $this->leaguesFor($user)->firstWhere('id', $leagueId);

        if ($league === null) {
            return false;
        }

        $user->forceFill([
            'active_league_id' => $leagueId,
        ])->save();

        return true;
    }

    /**
     * @return array{
     *     available_leagues: array<int, array{id: int, name: string, slug: string, role: string, role_label: string, is_active: bool}>,
     *     active_league: array{id: int, name: string, slug: string, role: string, role_label: string, is_active: bool}|null,
     *     can_switch: bool,
     *     has_memberships: bool,
     *     has_blocked_access: bool,
     *     guest_mode: bool
     * }
     */
    public function contextFor(User $user): array
    {
        $availableLeagues = $this->leaguesFor($user)->values()->all();
        $hasMemberships = $user->leagueMemberships()->exists();
        $activeLeague = $this->activeLeagueFor($user);

        return [
            'available_leagues' => $availableLeagues,
            'active_league' => $activeLeague,
            'can_switch' => count($availableLeagues) > 1,
            'has_memberships' => $hasMemberships,
            'has_blocked_access' => $hasMemberships && $activeLeague !== null && ! $activeLeague['is_active'],
            'guest_mode' => ! $hasMemberships,
        ];
    }
}

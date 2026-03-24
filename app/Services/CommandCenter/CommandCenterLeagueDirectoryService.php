<?php

namespace App\Services\CommandCenter;

use App\Enums\LeagueMembershipRole;
use App\Models\League;
use App\Models\LeagueMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CommandCenterLeagueDirectoryService
{
    /**
     * @return array<int, array{
     *     id: int,
     *     name: string,
     *     slug: string,
     *     is_active: bool,
     *     admins: array<int, array{id: int|null, name: string|null}>,
     *     members_count: int,
     *     created_at: string|null
     * }>
     */
    public function leagues(): array
    {
        return League::query()
            ->with([
                'adminMemberships.user:id,name',
                'memberMemberships',
            ])
            ->withCount('memberMemberships')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (League $league): array => $this->toArray($league))
            ->all();
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     slug: string,
     *     is_active: bool,
     *     admins: array<int, array{id: int|null, name: string|null}>,
     *     members_count: int,
     *     created_at: string|null
     * }
     */
    public function toggle(League $league): array
    {
        DB::transaction(function () use ($league): void {
            $league->update([
                'is_active' => ! $league->is_active,
            ]);

            if (! $league->is_active) {
                $this->reassignUsersFromInactiveLeague($league);
            }
        });

        return $this->toArray(
            $league->fresh([
                'adminMemberships.user:id,name',
                'memberMemberships',
            ])->loadCount('memberMemberships')
        );
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     slug: string,
     *     is_active: bool,
     *     admins: array<int, array{id: int|null, name: string|null}>,
     *     members_count: int,
     *     created_at: string|null
     * }
     */
    private function toArray(League $league): array
    {
        return [
            'id' => $league->id,
            'name' => $league->name,
            'slug' => $league->slug,
            'is_active' => $league->is_active,
            'admins' => $league->adminMemberships
                ->map(fn ($membership): array => [
                    'id' => $membership->user?->id,
                    'name' => $membership->user?->name,
                ])
                ->filter(fn (array $admin): bool => filled($admin['name']))
                ->values()
                ->all(),
            'members_count' => (int) $league->member_memberships_count,
            'created_at' => $league->created_at?->toDateTimeString(),
        ];
    }

    private function reassignUsersFromInactiveLeague(League $league): void
    {
        User::query()
            ->where('active_league_id', $league->id)
            ->get()
            ->each(function (User $user) use ($league): void {
                $fallbackLeagueId = $this->fallbackActiveLeagueIdFor($user, $league->id);

                if ($fallbackLeagueId === null) {
                    return;
                }

                $user->forceFill([
                    'active_league_id' => $fallbackLeagueId,
                ])->save();
            });
    }

    private function fallbackActiveLeagueIdFor(User $user, int $excludedLeagueId): ?int
    {
        return LeagueMembership::query()
            ->join('leagues', 'leagues.id', '=', 'league_memberships.league_id')
            ->where('league_memberships.user_id', $user->id)
            ->where('league_memberships.league_id', '!=', $excludedLeagueId)
            ->where('leagues.is_active', true)
            ->orderByRaw(
                'case when league_memberships.role = ? then 0 else 1 end',
                [LeagueMembershipRole::Admin->value],
            )
            ->orderBy('leagues.name')
            ->value('leagues.id');
    }
}

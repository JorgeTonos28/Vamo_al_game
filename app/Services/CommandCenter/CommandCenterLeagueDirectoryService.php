<?php

namespace App\Services\CommandCenter;

use App\Enums\LeagueMembershipRole;
use App\Models\League;
use App\Models\LeagueMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
            ])
            ->withCount([
                'memberships as operational_memberships_count' => fn ($query) => $query->whereIn('role', [
                    LeagueMembershipRole::Admin->value,
                    LeagueMembershipRole::Member->value,
                ]),
            ])
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
    public function create(User $creator, string $name, ?string $emoji = null): array
    {
        $normalizedName = preg_replace('/\s+/', ' ', trim($name)) ?? trim($name);
        $normalizedEmoji = filled($emoji) ? trim($emoji) : null;

        $alreadyExists = League::query()
            ->pluck('name')
            ->contains(fn ($existingName): bool => $existingName === $normalizedName);

        if ($alreadyExists) {
            throw ValidationException::withMessages([
                'name' => 'Ya existe una liga con ese nombre.',
            ]);
        }

        $league = League::query()->create([
            'name' => $normalizedName,
            'emoji' => $normalizedEmoji,
            'incoming_team_guest_limit' => 2,
            'slug' => $this->uniqueSlugFor($normalizedName),
            'is_active' => true,
            'created_by_user_id' => $creator->id,
        ]);

        return $this->toArray(
            $league->fresh([
                'adminMemberships.user:id,name',
            ])->loadCount([
                'memberships as operational_memberships_count' => fn ($query) => $query->whereIn('role', [
                    LeagueMembershipRole::Admin->value,
                    LeagueMembershipRole::Member->value,
                ]),
            ])
        );
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     emoji: string|null,
     *     slug: string,
     *     is_active: bool,
     *     admins: array<int, array{id: int|null, name: string|null}>,
     *     members_count: int,
     *     created_at: string|null
     * }
     */
    public function updateName(League $league, string $name): array
    {
        $normalizedName = preg_replace('/\s+/', ' ', trim($name)) ?? trim($name);

        $alreadyExists = League::query()
            ->whereKeyNot($league->id)
            ->pluck('name')
            ->contains(fn ($existingName): bool => $existingName === $normalizedName);

        if ($alreadyExists) {
            throw ValidationException::withMessages([
                'name' => 'Ya existe una liga con ese nombre.',
            ]);
        }

        if ($league->name !== $normalizedName) {
            $league->forceFill([
                'name' => $normalizedName,
                'slug' => $this->uniqueSlugFor($normalizedName, $league->id),
            ])->save();
        }

        return $this->toArray(
            $league->fresh([
                'adminMemberships.user:id,name',
            ])->loadCount([
                'memberships as operational_memberships_count' => fn ($query) => $query->whereIn('role', [
                    LeagueMembershipRole::Admin->value,
                    LeagueMembershipRole::Member->value,
                ]),
            ])
        );
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     emoji: string|null,
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
            ])->loadCount([
                'memberships as operational_memberships_count' => fn ($query) => $query->whereIn('role', [
                    LeagueMembershipRole::Admin->value,
                    LeagueMembershipRole::Member->value,
                ]),
            ])
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
            'emoji' => $league->emoji,
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
            'members_count' => (int) $league->operational_memberships_count,
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

    private function uniqueSlugFor(string $name, ?int $ignoreLeagueId = null): string
    {
        $baseSlug = Str::slug($name);
        $baseSlug = $baseSlug !== '' ? $baseSlug : 'liga';
        $slug = $baseSlug;
        $suffix = 2;

        while (League::query()
            ->when($ignoreLeagueId !== null, fn ($query) => $query->whereKeyNot($ignoreLeagueId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}

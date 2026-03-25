<?php

namespace App\Services\LeagueMemberships;

use App\Enums\AccountRole;
use App\Enums\LeagueMembershipRole;
use App\Models\League;
use App\Models\LeagueMembership;
use App\Models\LeaguePlayer;
use App\Models\User;
use App\Support\UserName;
use Illuminate\Support\Facades\DB;

class LeagueMembershipManager
{
    public function assign(
        User $user,
        League $league,
        LeagueMembershipRole $role,
        ?User $actor = null,
    ): LeagueMembership {
        return DB::transaction(function () use ($user, $league, $role, $actor): LeagueMembership {
            /** @var LeagueMembership $membership */
            $membership = LeagueMembership::query()->updateOrCreate(
                [
                    'league_id' => $league->id,
                    'user_id' => $user->id,
                ],
                [
                    'role' => $role,
                ],
            );

            if ($role->canAccessOperationalModules()) {
                $this->ensureOperationalPlayer($league, $user, $actor);
            }

            if ($user->active_league_id === null || ! $user->leagueMemberships()
                ->where('league_id', $user->active_league_id)
                ->exists()) {
                $user->forceFill([
                    'active_league_id' => $league->id,
                ])->save();
            }

            $this->syncPrimaryAccountRole($user);

            return $membership->fresh(['league', 'user']);
        });
    }

    private function ensureOperationalPlayer(League $league, User $user, ?User $actor = null): LeaguePlayer
    {
        $displayName = $this->baseDisplayName($user);

        /** @var LeaguePlayer|null $player */
        $player = LeaguePlayer::query()
            ->where('league_id', $league->id)
            ->where('user_id', $user->id)
            ->first();

        if ($player === null) {
            $player = LeaguePlayer::query()
                ->where('league_id', $league->id)
                ->whereRaw('lower(display_name) = ?', [mb_strtolower($displayName)])
                ->orderByRaw('case when user_id is null then 0 else 1 end')
                ->first();
        }

        if ($player === null) {
            return LeaguePlayer::query()->create([
                'league_id' => $league->id,
                'user_id' => $user->id,
                'display_name' => $this->uniqueDisplayName($league, $displayName),
                'status' => 'active',
                'created_by_user_id' => $actor?->id,
                'updated_by_user_id' => $actor?->id,
                'joined_at' => now(),
            ]);
        }

        $resolvedDisplayName = $player->display_name;

        if (
            ! $player->user_id ||
            strcasecmp($player->display_name, $displayName) === 0
        ) {
            $resolvedDisplayName = $this->uniqueDisplayName($league, $displayName, $player->id);
        }

        $player->forceFill([
            'user_id' => $user->id,
            'display_name' => $resolvedDisplayName,
            'status' => 'active',
            'removed_at' => null,
            'updated_by_user_id' => $actor?->id,
            'joined_at' => $player->joined_at ?? now(),
        ])->save();

        return $player->fresh();
    }

    private function syncPrimaryAccountRole(User $user): void
    {
        if ($user->isGeneralAdmin()) {
            return;
        }

        $roles = $user->leagueMemberships()
            ->pluck('role')
            ->map(fn ($value) => $value instanceof LeagueMembershipRole
                ? $value
                : LeagueMembershipRole::from((string) $value));

        if ($roles->isEmpty()) {
            return;
        }

        $accountRole = $roles->contains(LeagueMembershipRole::Admin)
            ? AccountRole::LeagueAdmin
            : ($roles->contains(LeagueMembershipRole::Member)
                ? AccountRole::Member
                : AccountRole::Guest);

        if ($user->account_role !== $accountRole) {
            $user->forceFill([
                'account_role' => $accountRole,
            ])->save();
        }
    }

    private function baseDisplayName(User $user): string
    {
        return trim($user->name) !== ''
            ? trim($user->name)
            : UserName::displayName($user->first_name, $user->last_name);
    }

    private function uniqueDisplayName(League $league, string $baseName, ?int $ignorePlayerId = null): string
    {
        $candidate = $baseName;
        $suffix = 2;

        while ($league->players()
            ->when(
                $ignorePlayerId !== null,
                fn ($query) => $query->whereKeyNot($ignorePlayerId),
            )
            ->whereRaw('lower(display_name) = ?', [mb_strtolower($candidate)])
            ->exists()) {
            $candidate = sprintf('%s (%d)', $baseName, $suffix);
            $suffix++;
        }

        return $candidate;
    }
}

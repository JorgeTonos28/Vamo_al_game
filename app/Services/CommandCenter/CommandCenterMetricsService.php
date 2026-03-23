<?php

namespace App\Services\CommandCenter;

use App\Enums\AccountRole;
use App\Enums\LeagueMembershipRole;
use App\Models\League;
use App\Models\LeagueMembership;
use App\Models\User;

class CommandCenterMetricsService
{
    /**
     * @return array<string, int>
     */
    public function totals(): array
    {
        return [
            'total_users' => User::query()->count(),
            'active_leagues' => League::query()->where('is_active', true)->count(),
            'inactive_leagues' => League::query()->where('is_active', false)->count(),
            'league_admins' => LeagueMembership::query()
                ->where('role', LeagueMembershipRole::Admin)
                ->distinct('user_id')
                ->count('user_id'),
            'members' => LeagueMembership::query()
                ->where('role', LeagueMembershipRole::Member)
                ->distinct('user_id')
                ->count('user_id'),
            'guests' => User::query()
                ->where('account_role', '!=', AccountRole::GeneralAdmin)
                ->whereDoesntHave('leagueMemberships')
                ->count(),
            'pending_invitations' => User::query()
                ->whereNotNull('invited_at')
                ->whereNull('onboarded_at')
                ->count(),
        ];
    }
}

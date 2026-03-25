<?php

namespace App\Services\LeagueOperations;

use App\Models\League;
use App\Models\LeagueSeason;
use App\Models\LeagueSession;
use App\Models\User;
use Illuminate\Support\Carbon;

class LeagueSeasonService
{
    public function activeSeasonForLeague(League $league, ?User $user = null): LeagueSeason
    {
        /** @var LeagueSeason|null $season */
        $season = $league->seasons()
            ->where('status', 'active')
            ->orderByDesc('starts_on')
            ->first();

        if ($season !== null) {
            return $season;
        }

        $today = Carbon::now()->startOfDay();

        /** @var LeagueSeason $season */
        $season = $league->seasons()->create([
            'label' => sprintf('Temporada %s', $today->format('Y')),
            'starts_on' => $today->toDateString(),
            'status' => 'active',
            'created_by_user_id' => $user?->id,
        ]);

        return $season;
    }

    public function attachSessionToActiveSeason(LeagueSession $session, League $league, ?User $user = null): LeagueSession
    {
        if ($session->league_season_id !== null) {
            return $session;
        }

        $season = $this->activeSeasonForLeague($league, $user);

        $session->forceFill([
            'league_season_id' => $season->id,
        ])->save();

        return $session->fresh();
    }
}

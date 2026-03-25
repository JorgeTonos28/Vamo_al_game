<?php

namespace App\Services\LeagueOperations;

use App\Models\LeaguePlayer;
use App\Models\User;

class LeagueHomeService
{
    public function __construct(
        private readonly LeagueOperationsService $operations,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function pageData(User $user): array
    {
        $context = $this->operations->activeContextFor($user);

        if ($context === null) {
            return [
                'mode' => 'no_league',
                'league' => null,
                'role' => null,
                'requires_league_selection' => false,
                'summary' => null,
            ];
        }

        $league = $context['league'];
        $requiresSelection = $user->leagueMemberships()->count() > 1;

        if (! $context['role']->canAccessOperationalModules()) {
            return [
                'mode' => 'guest',
                'league' => [
                    'id' => $league->id,
                    'name' => $league->name,
                    'emoji' => $league->emoji,
                    'slug' => $league->slug,
                ],
                'role' => [
                    'value' => $context['role']->value,
                    'label' => $context['role']->label(),
                    'can_manage' => false,
                ],
                'requires_league_selection' => $requiresSelection,
                'summary' => null,
            ];
        }

        $cut = $this->operations->activeCutForLeague($league);
        $session = $this->operations->currentSessionForLeague($league, $cut);
        $players = $league->activePlayers()->orderBy('display_name')->get();
        $paidCount = $players
            ->filter(fn (LeaguePlayer $player): bool => $this->operations->balanceForPlayer($cut, $player)->status === 'paid')
            ->count();

        return [
            'mode' => 'operational',
            'league' => [
                'id' => $league->id,
                'name' => $league->name,
                'emoji' => $league->emoji,
                'slug' => $league->slug,
            ],
            'role' => [
                'value' => $context['role']->value,
                'label' => $context['role']->label(),
                'can_manage' => $context['role']->canManageLeague(),
            ],
            'requires_league_selection' => $requiresSelection,
            'summary' => [
                'cut_label' => $cut->label,
                'is_past_due' => $this->operations->hasPastDue($cut),
                'players_count' => $players->count(),
                'paid_players_count' => $paidCount,
                'pending_players_count' => max(0, $players->count() - $paidCount),
                'today_arrivals_count' => $session?->entries()->where('entry_type', 'player')->count() ?? 0,
                'today_guests_count' => $session?->entries()->where('entry_type', 'guest')->count() ?? 0,
                'session_status' => $session?->status ?? 'arrival_open',
            ],
        ];
    }
}

<?php

namespace App\Services\LeagueOperations;

use App\Models\LeaguePlayer;
use App\Models\LeaguePlayerScoutProfile;
use App\Models\LeagueSession;
use App\Models\LeagueSessionActionLog;
use App\Models\LeagueSessionEntry;
use App\Models\LeagueSessionGame;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeagueCompetitionService
{
    private const SCOUT_POSITIONS = ['Base', 'Escolta', 'Alero', 'Power Forward', 'Centro'];

    private const SCOUT_ROLES = ['Anotador', 'Defensivo', 'Equilibrado'];

    private const SCOUT_CONSISTENCIES = ['Constante', 'Inconsistente'];

    private const SCOUT_ATTRS = [
        'speed_rating',
        'dribbling_rating',
        'scoring_rating',
        'team_play_rating',
        'court_knowledge_rating',
        'defense_rating',
        'triples_rating',
    ];

    public function __construct(
        private readonly LeagueOperationsService $operations,
        private readonly LeagueSeasonService $seasons,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function gamePageData(User $user): array
    {
        $context = $this->operationalContext($user);
        $session = $context['session'];
        $openGame = $this->openGame($session);
        $completedGames = $session->games
            ->where('status', 'completed')
            ->sortByDesc('game_number')
            ->values();

        return array_merge($this->basePayload($context), [
            'game' => [
                'state' => $this->gameState($session, $openGame),
                'draft' => [
                    'entries' => $this->pendingPoolEntries($session)
                        ->map(fn (LeagueSessionEntry $entry): array => $this->entryCard($entry))
                        ->values()
                        ->all(),
                    'can_start' => $openGame === null && $this->pendingPoolEntries($session)->count() === 10,
                ],
                'current' => $openGame === null ? null : [
                    'id' => $openGame->id,
                    'game_number' => $openGame->game_number,
                    'score' => [
                        'team_a' => $openGame->team_a_score,
                        'team_b' => $openGame->team_b_score,
                    ],
                    'streak' => $this->streakPayload($session),
                    'team_a' => $this->currentTeamPayload($session, $openGame, 'A'),
                    'team_b' => $this->currentTeamPayload($session, $openGame, 'B'),
                ],
                'history' => $completedGames->map(fn (LeagueSessionGame $game): array => [
                    'id' => $game->id,
                    'game_number' => $game->game_number,
                    'score' => "{$game->team_a_score} - {$game->team_b_score}",
                    'winner_side' => $game->winner_side,
                    'summary' => sprintf(
                        'Eq. %s gano %s-%s',
                        $game->winner_side,
                        $game->winner_side === 'A' ? $game->team_a_score : $game->team_b_score,
                        $game->winner_side === 'A' ? $game->team_b_score : $game->team_a_score,
                    ),
                ])->all(),
                'summary' => $this->queueSummary($session, $context['cut']),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function queuePageData(User $user): array
    {
        $context = $this->operationalContext($user);
        $session = $context['session'];
        $openGame = $this->openGame($session);

        return array_merge($this->basePayload($context), [
            'queue' => [
                'on_court' => $this->onCourtEntries($session)
                    ->sortBy([
                        ['team_side', 'asc'],
                        ['arrival_order', 'asc'],
                    ])
                    ->values()
                    ->map(fn (LeagueSessionEntry $entry): array => [
                        ...$this->entryCard($entry),
                        'team_side' => $entry->team_side,
                        'games_played' => $this->completedGamesForEntry($session, $entry) + 1,
                        'points_scored' => $this->entryPointsToday($session, $entry, true),
                    ])->all(),
                'waiting' => $this->queueEntries($session)
                    ->map(fn (LeagueSessionEntry $entry): array => [
                        ...$this->entryCard($entry),
                        'position' => $entry->queue_position,
                        'games_played' => $this->completedGamesForEntry($session, $entry),
                        'points_scored' => $this->entryPointsToday($session, $entry, false),
                    ])->all(),
                'summary' => $this->queueSummary($session, $context['cut']),
                'live_game' => $openGame === null ? null : [
                    'game_number' => $openGame->game_number,
                    'score' => "{$openGame->team_a_score} - {$openGame->team_b_score}",
                ],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function statsPageData(User $user): array
    {
        $context = $this->operationalContext($user);
        $session = $context['session'];
        $stats = $this->sessionStats($session);

        return array_merge($this->basePayload($context), [
            'stats' => [
                'games_count' => $session->games->where('status', 'completed')->count(),
                'points_leaders' => $stats
                    ->filter(fn (array $row): bool => $row['points'] > 0 || $row['games'] > 0)
                    ->sortByDesc('points')
                    ->values()
                    ->map(fn (array $row): array => [
                        'identity' => $row['identity'],
                        'points' => $row['points'],
                        'games' => $row['games'],
                        'shots' => $row['shots'],
                    ])->all(),
                'games_leaders' => $stats
                    ->filter(fn (array $row): bool => $row['games'] > 0)
                    ->sortByDesc('games')
                    ->values()
                    ->map(fn (array $row): array => [
                        'identity' => $row['identity'],
                        'games' => $row['games'],
                        'wins' => $row['wins'],
                        'losses' => $row['losses'],
                    ])->all(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function tablePageData(User $user): array
    {
        $context = $this->operationalContext($user);
        $session = $context['session'];
        $stats = $this->sessionStats($session)
            ->filter(fn (array $row): bool => $row['games'] > 0 || $row['points'] > 0)
            ->values();

        return array_merge($this->basePayload($context), [
            'table' => [
                'banner' => [
                    'games' => $session->games->where('status', 'completed')->count(),
                    'points' => $stats->sum('points'),
                    'players' => $session->entries->count(),
                ],
                'standings' => $stats
                    ->sortBy([
                        ['wins', 'desc'],
                        ['points', 'desc'],
                    ])
                    ->values()
                    ->map(fn (array $row): array => [
                        'identity' => $row['identity'],
                        'games' => $row['games'],
                        'wins' => $row['wins'],
                        'losses' => $row['losses'],
                        'win_rate' => $row['games'] > 0
                            ? (int) round(($row['wins'] / $row['games']) * 100)
                            : 0,
                    ])->all(),
                'top_scorers' => $stats
                    ->sortByDesc('points')
                    ->take(5)
                    ->values()
                    ->map(fn (array $row): array => [
                        'identity' => $row['identity'],
                        'points' => $row['points'],
                        'points_per_game' => $row['games'] > 0
                            ? round($row['points'] / $row['games'], 1)
                            : 0,
                    ])->all(),
                'top_games' => $stats
                    ->sortByDesc('games')
                    ->take(5)
                    ->values()
                    ->map(fn (array $row): array => [
                        'identity' => $row['identity'],
                        'games' => $row['games'],
                        'wins' => $row['wins'],
                        'losses' => $row['losses'],
                    ])->all(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function seasonPageData(User $user): array
    {
        $context = $this->operationalContext($user);
        $season = $context['season'];
        $stats = $this->seasonStats($season->sessions);
        $sessions = $season->sessions
            ->sortByDesc('session_date')
            ->values();
        $sessionsData = $sessions->map(function (LeagueSession $session): array {
            $completed = $session->games->where('status', 'completed')->values();
            $topScorer = $this->sessionStats($session)
                ->sortByDesc('points')
                ->first();

            return [
                'id' => $session->id,
                'date' => $session->session_date?->toDateString(),
                'total_games' => $completed->count(),
                'total_points' => $completed->sum(fn (LeagueSessionGame $game): int => $game->team_a_score + $game->team_b_score),
                'players' => $session->entries->count(),
                'top_scorer' => $topScorer === null ? null : [
                    'name' => $topScorer['identity']['name'],
                    'points' => $topScorer['points'],
                ],
            ];
        });
        $totalRevenueCents = $this->seasonRevenueCents($season->sessions);

        return array_merge($this->basePayload($context), [
            'season' => [
                'season' => [
                    'id' => $season->id,
                    'label' => $season->label,
                    'starts_on' => $season->starts_on?->toDateString(),
                    'sessions_count' => $sessionsData->count(),
                    'totals' => [
                        'games' => $sessionsData->sum('total_games'),
                        'points' => $sessionsData->sum('total_points'),
                        'revenue_cents' => $totalRevenueCents,
                        'show_revenue' => $context['role']->canManageLeague(),
                    ],
                ],
                'leaders' => [
                    'points' => $stats->sortByDesc('points')->take(5)->values()->all(),
                    'wins' => $stats->sortByDesc('wins')->take(5)->values()->all(),
                    'games' => $stats->sortByDesc('games')->take(5)->values()->all(),
                ],
                'sessions' => $sessionsData->all(),
                'profiles' => $stats->sortByDesc('points')->values()->all(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function scoutPageData(User $user): array
    {
        $context = $this->operationalContext($user);
        $league = $context['league'];
        $session = $context['session'];
        $season = $context['season'];
        $seasonStats = $this->seasonStats($context['season']->sessions)->keyBy('season_key');
        $players = $league->activePlayers()
            ->with('scoutProfile')
            ->orderBy('display_name')
            ->get();

        $rows = $players->map(function (LeaguePlayer $player) use ($seasonStats): array {
            $profile = $player->scoutProfile;
            $seasonKey = $this->seasonKeyForIdentity([
                'player_id' => $player->id,
                'name' => $player->display_name,
                'is_guest' => false,
            ]);
            $seasonRow = $seasonStats->get($seasonKey);
            $combined = $this->combinedScoutRating($profile, $seasonRow);

            return [
                'player' => [
                    'id' => $player->id,
                    'name' => $player->display_name,
                    'jersey_number' => $player->jersey_number,
                ],
                'profile' => [
                    'position' => $profile?->position,
                    'role' => $profile?->role,
                    'offensive_consistency' => $profile?->offensive_consistency,
                    'speed_rating' => $profile?->speed_rating ?? 0,
                    'dribbling_rating' => $profile?->dribbling_rating ?? 0,
                    'scoring_rating' => $profile?->scoring_rating ?? 0,
                    'team_play_rating' => $profile?->team_play_rating ?? 0,
                    'court_knowledge_rating' => $profile?->court_knowledge_rating ?? 0,
                    'defense_rating' => $profile?->defense_rating ?? 0,
                    'triples_rating' => $profile?->triples_rating ?? 0,
                ],
                'season_stats' => $seasonRow,
                'combined_rating' => $combined['rating'],
                'manual_rating' => $combined['manual_rating'],
                'stat_rating' => $combined['stat_rating'],
                'has_stats' => $combined['has_stats'],
            ];
        })->values();

        $profiledPlayers = $rows
            ->filter(fn (array $row): bool => $row['combined_rating'] > 0)
            ->count();
        $autoPreviewPool = $this->pendingPoolEntries($session);
        $autoPreview = $autoPreviewPool->count() === 10
            ? $this->scoutAutoPreview($autoPreviewPool, $season, $seasonStats)
            : null;

        return array_merge($this->basePayload($context), [
            'scout' => [
                'meta' => [
                    'positions' => self::SCOUT_POSITIONS,
                    'roles' => self::SCOUT_ROLES,
                    'consistencies' => self::SCOUT_CONSISTENCIES,
                ],
                'summary' => [
                    'profiled_players' => $profiledPlayers,
                    'total_players' => $players->count(),
                    'auto_preview_ready' => $autoPreview !== null,
                    'auto_preview_pool_count' => $autoPreviewPool->count(),
                ],
                'players' => $rows->all(),
                'ranking' => $rows
                    ->sortByDesc('combined_rating')
                    ->values()
                    ->map(fn (array $row): array => [
                        'player' => $row['player'],
                        'combined_rating' => $row['combined_rating'],
                        'profile' => $row['profile'],
                        'has_stats' => $row['has_stats'],
                    ])->all(),
                'auto_preview' => $autoPreview,
            ],
        ]);
    }

    public function confirmDraft(User $user, string $mode, array $assignments = []): void
    {
        $context = $this->adminContext($user);
        $session = $context['session'];

        if ($this->openGame($session) !== null) {
            throw ValidationException::withMessages([
                'session' => 'Ya existe un juego activo en esta jornada.',
            ]);
        }

        $pool = $this->pendingPoolEntries($session);

        if ($pool->count() !== 10) {
            throw ValidationException::withMessages([
                'session' => 'Se necesitan 10 jugadores listos para repartir los equipos.',
            ]);
        }

        $teams = match ($mode) {
            'auto' => $this->autoDraft($pool, $context['season']),
            'arrival' => $this->arrivalDraft($pool),
            'manual' => $this->manualDraft($pool, $assignments),
            default => throw ValidationException::withMessages([
                'mode' => 'Modo de reparto invalido.',
            ]),
        };

        DB::transaction(function () use ($session, $user, $mode, $teams): void {
            foreach ($teams['A'] as $entry) {
                $entry->forceFill([
                    'session_state' => 'on_court',
                    'team_side' => 'A',
                    'queue_position' => null,
                ])->save();
            }

            foreach ($teams['B'] as $entry) {
                $entry->forceFill([
                    'session_state' => 'on_court',
                    'team_side' => 'B',
                    'queue_position' => null,
                ])->save();
            }

            $session->forceFill([
                'status' => 'in_progress',
            ])->save();

            $this->createOpenGameFromCurrentCourt($session->fresh(['entries.player', 'games']), $user, $mode, 'standard');
        });
    }

    public function addTeamPoint(User $user, string $teamSide): void
    {
        $context = $this->adminContext($user);
        $game = $this->requireOpenGame($context['session']);
        $this->storeBeforeState($context['session'], $game, 'team_point', $user);

        $game->forceFill([
            $teamSide === 'A' ? 'team_a_score' : 'team_b_score' => ($teamSide === 'A'
                ? $game->team_a_score
                : $game->team_b_score) + 1,
        ])->save();
    }

    public function addPlayerPoint(User $user, LeagueSessionEntry $entry, int $points): void
    {
        $context = $this->adminContext($user);
        $session = $context['session'];
        $game = $this->requireOpenGame($session);
        $this->guardEntryBelongsToSession($session, $entry);
        $this->guardEntryOnCourt($entry);
        $this->guardPointsValue($points);
        $this->storeBeforeState($session, $game, 'player_point', $user);

        $playerPoints = $game->player_points ?? [];
        $playerShots = $game->player_shots ?? [];
        $entryKey = (string) $entry->id;
        $playerPoints[$entryKey] = (int) ($playerPoints[$entryKey] ?? 0) + $points;
        $shots = $playerShots[$entryKey] ?? ['1' => 0, '2' => 0, '3' => 0];
        $shots[(string) $points] = (int) ($shots[(string) $points] ?? 0) + 1;
        $playerShots[$entryKey] = $shots;

        $game->forceFill([
            'player_points' => $playerPoints,
            'player_shots' => $playerShots,
            $entry->team_side === 'A' ? 'team_a_score' : 'team_b_score' => ($entry->team_side === 'A'
                ? $game->team_a_score
                : $game->team_b_score) + $points,
        ])->save();
    }

    public function revertPlayerPoints(User $user, LeagueSessionEntry $entry, int $points): void
    {
        $context = $this->adminContext($user);
        $session = $context['session'];
        $game = $this->requireOpenGame($session);
        $this->guardEntryBelongsToSession($session, $entry);
        $this->guardEntryOnCourt($entry);
        $this->guardPointsValue($points);
        $this->storeBeforeState($session, $game, 'player_point_reversal', $user);

        $entryKey = (string) $entry->id;
        $playerPoints = $game->player_points ?? [];
        $playerShots = $game->player_shots ?? [];
        $shots = $playerShots[$entryKey] ?? ['1' => 0, '2' => 0, '3' => 0];

        if ((int) ($playerPoints[$entryKey] ?? 0) < $points || (int) ($shots[(string) $points] ?? 0) < 1) {
            throw ValidationException::withMessages([
                'points' => 'Ese jugador no tiene esa jugada registrada para revertir.',
            ]);
        }

        $playerPoints[$entryKey] = max(0, (int) $playerPoints[$entryKey] - $points);
        $shots[(string) $points] = max(0, (int) ($shots[(string) $points] ?? 0) - 1);
        $playerShots[$entryKey] = $shots;

        $game->forceFill([
            'player_points' => $playerPoints,
            'player_shots' => $playerShots,
            $entry->team_side === 'A' ? 'team_a_score' : 'team_b_score' => max(
                0,
                ($entry->team_side === 'A' ? $game->team_a_score : $game->team_b_score) - $points,
            ),
        ])->save();
    }

    public function removePlayer(User $user, LeagueSessionEntry $entry): void
    {
        $context = $this->adminContext($user);
        $session = $context['session'];
        $game = $this->requireOpenGame($session);
        $this->guardEntryBelongsToSession($session, $entry);
        $this->guardEntryOnCourt($entry);
        $this->storeBeforeState($session, $game, 'player_removed', $user);

        $replacement = $this->nextQueueReplacement($session, (string) $entry->team_side);

        DB::transaction(function () use ($entry, $replacement, $session, $game): void {
            $teamSide = $entry->team_side;

            $entry->forceFill([
                'session_state' => 'removed',
                'team_side' => null,
                'queue_position' => null,
            ])->save();

            if ($replacement !== null) {
                $replacement->forceFill([
                    'session_state' => 'on_court',
                    'team_side' => $teamSide,
                    'queue_position' => null,
                ])->save();

                $this->resequenceQueue($session->fresh('entries'));
            }

            $this->syncGameSnapshots($game->fresh(), $session->fresh(['entries.player']));
        });
    }

    public function undoLastAction(User $user): void
    {
        $context = $this->adminContext($user);
        $session = $context['session'];
        $game = $this->requireOpenGame($session);

        /** @var LeagueSessionActionLog|null $log */
        $log = $session->actionLogs()
            ->where('league_session_game_id', $game->id)
            ->whereNull('undone_at')
            ->orderByDesc('sequence')
            ->first();

        if ($log === null) {
            throw ValidationException::withMessages([
                'session' => 'No hay acciones para deshacer en el juego actual.',
            ]);
        }

        DB::transaction(function () use ($session, $game, $log): void {
            $this->restoreSnapshot($session, $game, $log->before_state);
            $log->forceFill([
                'undone_at' => now(),
            ])->save();
        });
    }

    public function finishCurrentGame(User $user, ?string $winnerSide = null): void
    {
        $context = $this->adminContext($user);
        $session = $context['session'];
        $game = $this->requireOpenGame($session);
        $resolvedWinner = $winnerSide;

        if ($resolvedWinner === null) {
            if ($game->team_a_score === $game->team_b_score) {
                throw ValidationException::withMessages([
                    'winner_side' => 'Debes indicar el ganador cuando el marcador termina empatado.',
                ]);
            }

            $resolvedWinner = $game->team_a_score > $game->team_b_score ? 'A' : 'B';
        }

        if (! in_array($resolvedWinner, ['A', 'B'], true)) {
            throw ValidationException::withMessages([
                'winner_side' => 'Equipo ganador invalido.',
            ]);
        }

        DB::transaction(function () use ($session, $game, $resolvedWinner, $user): void {
            $this->syncGameSnapshots($game, $session);
            $game->forceFill([
                'winner_side' => $resolvedWinner,
                'status' => 'completed',
                'ended_at' => now(),
                'finished_by_user_id' => $user->id,
            ])->save();

            $this->applyRotationAfterGame($session->fresh(['entries.player', 'games']), $resolvedWinner, $user);
        });
    }

    public function endSession(User $user): void
    {
        $context = $this->adminContext($user);
        $session = $context['session'];
        $completedGames = $session->games->where('status', 'completed');

        if ($completedGames->isEmpty()) {
            throw ValidationException::withMessages([
                'session' => 'No hay juegos terminados para cerrar la jornada.',
            ]);
        }

        $openGame = $this->openGame($session);

        if ($openGame !== null && (($openGame->team_a_score + $openGame->team_b_score) > 0 || ! empty($openGame->player_points))) {
            throw ValidationException::withMessages([
                'session' => 'Termina o limpia el juego actual antes de cerrar la jornada.',
            ]);
        }

        DB::transaction(function () use ($session, $openGame): void {
            if ($openGame !== null) {
                $openGame->delete();
            }

            $session->forceFill([
                'status' => 'completed',
                'ended_at' => now(),
            ])->save();
        });
    }

    public function resetCurrentGame(User $user): void
    {
        $context = $this->adminContext($user);
        $session = $context['session'];
        $game = $this->requireOpenGame($session);

        DB::transaction(function () use ($session, $game): void {
            $game->forceFill([
                'team_a_score' => 0,
                'team_b_score' => 0,
                'player_points' => [],
                'player_shots' => [],
            ])->save();

            $session->actionLogs()
                ->where('league_session_game_id', $game->id)
                ->delete();
        });
    }

    public function updateScoutProfile(User $user, LeaguePlayer $player, array $payload): void
    {
        $context = $this->adminContext($user);

        if ($player->league_id !== $context['league']->id) {
            throw ValidationException::withMessages([
                'player_id' => 'Ese jugador no pertenece a la liga activa.',
            ]);
        }

        $player->scoutProfile()->updateOrCreate(
            ['league_player_id' => $player->id],
            [
                ...$payload,
                'updated_by_user_id' => $user->id,
                'last_reviewed_at' => now(),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function operationalContext(User $user): array
    {
        $context = $this->operations->requireOperationalContext($user);
        $cut = $this->operations->activeCutForLeague($context['league']);
        $session = $this->seasons->attachSessionToActiveSeason(
            $this->operations->currentSessionForLeague($context['league'], $cut),
            $context['league'],
            $user,
        );

        $session->loadMissing([
            'entries.player.scoutProfile',
            'games',
            'season.sessions.games',
            'season.sessions.entries.player',
        ]);

        $session = $this->ensurePreparedEntryState($session);

        return [
            ...$context,
            'cut' => $cut,
            'session' => $session,
            'season' => $session->season,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function adminContext(User $user): array
    {
        $context = $this->operations->requireAdminContext($user);
        $cut = $this->operations->activeCutForLeague($context['league']);
        $session = $this->seasons->attachSessionToActiveSeason(
            $this->operations->currentSessionForLeague($context['league'], $cut),
            $context['league'],
            $user,
        );

        $session->loadMissing([
            'entries.player.scoutProfile',
            'games',
            'actionLogs',
            'season.sessions.games',
            'season.sessions.entries.player',
        ]);

        $session = $this->ensurePreparedEntryState($session);

        return [
            ...$context,
            'cut' => $cut,
            'session' => $session,
            'season' => $session->season,
        ];
    }

    private function ensurePreparedEntryState(LeagueSession $session): LeagueSession
    {
        if (! in_array($session->status, ['prepared', 'in_progress', 'completed'], true)) {
            return $session;
        }

        if ($session->entries->where('session_state', '!=', 'arrival')->isNotEmpty()) {
            return $session;
        }

        DB::transaction(function () use ($session): void {
            $poolIds = collect($session->initial_pool ?? [])
                ->pluck('id')
                ->all();
            $queueIds = collect($session->initial_queue ?? [])
                ->pluck('id')
                ->values()
                ->all();

            foreach ($session->entries as $entry) {
                if (in_array($entry->id, $poolIds, true)) {
                    $entry->forceFill([
                        'session_state' => 'pool',
                        'queue_position' => null,
                    ])->save();

                    continue;
                }

                $queueIndex = array_search($entry->id, $queueIds, true);

                if ($queueIndex !== false) {
                    $entry->forceFill([
                        'session_state' => 'queued',
                        'queue_position' => $queueIndex + 1,
                    ])->save();
                }
            }
        });

        return $session->fresh([
            'entries.player.scoutProfile',
            'games',
            'season.sessions.games',
            'season.sessions.entries.player',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function basePayload(array $context): array
    {
        /** @var LeagueSession $session */
        $session = $context['session'];

        return [
            'league' => [
                'id' => $context['league']->id,
                'name' => $context['league']->name,
                'emoji' => $context['league']->emoji,
                'slug' => $context['league']->slug,
            ],
            'role' => [
                'value' => $context['role']->value,
                'label' => $context['role']->label(),
                'can_manage' => $context['role']->canManageLeague(),
            ],
            'session' => [
                'id' => $session->id,
                'status' => $session->status,
                'session_date' => $session->session_date?->toDateString(),
                'current_game_number' => $session->current_game_number,
                'streak' => $this->streakPayload($session),
                'participants_count' => $session->entries->count(),
                'pending_pool_count' => $this->pendingPoolEntries($session)->count(),
                'queue_count' => $this->queueEntries($session)->count(),
            ],
        ];
    }

    private function gameState(LeagueSession $session, ?LeagueSessionGame $game): string
    {
        if ($game !== null) {
            return 'live';
        }

        if ($this->pendingPoolEntries($session)->isNotEmpty()) {
            return 'draft';
        }

        return $session->status === 'completed' ? 'completed' : 'idle';
    }

    /**
     * @return array<string, mixed>
     */
    private function streakPayload(LeagueSession $session): array
    {
        $state = $this->rotationState($session);

        return [
            'team' => $state['streak_team'],
            'count' => $state['streak_count'],
            'double_rotation_mode' => $state['double_rotation_mode'],
            'waiting_champion_team' => $state['waiting_champion_team'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function queueSummary(LeagueSession $session, $cut): array
    {
        $completedGames = $session->games->where('status', 'completed');
        $totalRevenueCents = $session->entries->sum(function (LeagueSessionEntry $entry) use ($cut): int {
            if ($entry->entry_type === 'guest') {
                return $entry->guest_fee_paid ? (int) $cut->guest_fee_amount_cents : 0;
            }

            return $entry->current_cut_paid ? (int) $cut->member_fee_amount_cents : 0;
        });

        return [
            'games' => $completedGames->count(),
            'streak_label' => $this->formatStreakLabel($session),
            'active_players' => $session->entries->count(),
            'guests' => $session->entries->where('entry_type', 'guest')->count(),
            'cash_collected_cents' => $totalRevenueCents,
            'unpaid_members_count' => $session->entries
                ->where('entry_type', 'player')
                ->where('current_cut_paid', false)
                ->count(),
        ];
    }

    private function formatStreakLabel(LeagueSession $session): string
    {
        $state = $this->rotationState($session);

        if ($state['streak_team'] === null || $state['streak_count'] < 1) {
            return '-';
        }

        return sprintf('EQ.%s (%d)', $state['streak_team'], $state['streak_count']);
    }

    /**
     * @return Collection<int, LeagueSessionEntry>
     */
    private function pendingPoolEntries(LeagueSession $session): Collection
    {
        return $session->entries
            ->where('session_state', 'pool')
            ->sortBy('arrival_order')
            ->values();
    }

    /**
     * @return Collection<int, LeagueSessionEntry>
     */
    private function onCourtEntries(LeagueSession $session): Collection
    {
        return $session->entries
            ->where('session_state', 'on_court')
            ->values();
    }

    /**
     * @return Collection<int, LeagueSessionEntry>
     */
    private function queueEntries(LeagueSession $session): Collection
    {
        return $session->entries
            ->where('session_state', 'queued')
            ->sortBy('queue_position')
            ->values();
    }

    /**
     * @return Collection<int, LeagueSessionEntry>
     */
    private function waitingChampionEntries(LeagueSession $session): Collection
    {
        return $session->entries
            ->where('session_state', 'waiting_champion')
            ->values();
    }

    private function openGame(LeagueSession $session): ?LeagueSessionGame
    {
        /** @var LeagueSessionGame|null $game */
        $game = $session->games
            ->sortByDesc('game_number')
            ->firstWhere('status', 'open');

        return $game;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function currentTeamPayload(LeagueSession $session, LeagueSessionGame $game, string $teamSide): array
    {
        $points = $game->player_points ?? [];
        $shots = $game->player_shots ?? [];

        return $this->onCourtEntries($session)
            ->where('team_side', $teamSide)
            ->sortBy('arrival_order')
            ->values()
            ->map(function (LeagueSessionEntry $entry) use ($points, $shots): array {
                $entryKey = (string) $entry->id;

                return [
                    ...$this->entryCard($entry),
                    'points' => (int) ($points[$entryKey] ?? 0),
                    'shots' => $shots[$entryKey] ?? ['1' => 0, '2' => 0, '3' => 0],
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function entryCard(LeagueSessionEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'name' => $entry->entry_type === 'guest' ? $entry->guest_name : $entry->player?->display_name,
            'is_guest' => $entry->entry_type === 'guest',
            'jersey_number' => $entry->player?->jersey_number,
            'arrival_order' => $entry->arrival_order,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $seasonStats
     * @return array<string, mixed>
     */
    private function scoutAutoPreview(Collection $pool, $season, Collection $seasonStats): array
    {
        $teams = $this->autoDraft($pool, $season);
        $teamA = $teams['A']->map(
            fn (LeagueSessionEntry $entry): array => $this->scoutPreviewCard($entry, $seasonStats)
        )->values();
        $teamB = $teams['B']->map(
            fn (LeagueSessionEntry $entry): array => $this->scoutPreviewCard($entry, $seasonStats)
        )->values();

        return [
            'mode' => 'auto',
            'source' => 'pending_pool',
            'team_a' => $teamA->all(),
            'team_b' => $teamB->all(),
            'team_a_rating' => round($teamA->sum('combined_rating'), 1),
            'team_b_rating' => round($teamB->sum('combined_rating'), 1),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $seasonStats
     * @return array<string, mixed>
     */
    private function scoutPreviewCard(LeagueSessionEntry $entry, Collection $seasonStats): array
    {
        $seasonKey = $this->seasonKeyForIdentity([
            'player_id' => $entry->player?->id,
            'name' => $entry->entry_type === 'guest' ? $entry->guest_name : $entry->player?->display_name,
            'is_guest' => $entry->entry_type === 'guest',
        ]);
        $seasonRow = $seasonStats->get($seasonKey);
        $combined = $this->combinedScoutRating($entry->player?->scoutProfile, $seasonRow);

        return [
            ...$this->entryCard($entry),
            'combined_rating' => $combined['rating'],
            'role' => $entry->player?->scoutProfile?->role,
            'position' => $entry->player?->scoutProfile?->position,
            'offensive_consistency' => $entry->player?->scoutProfile?->offensive_consistency,
            'has_stats' => $combined['has_stats'],
        ];
    }

    /**
     * @return array<string, Collection<int, LeagueSessionEntry>>
     */
    private function manualDraft(Collection $pool, array $assignments): array
    {
        if (count($assignments) !== $pool->count()) {
            throw ValidationException::withMessages([
                'assignments' => 'Debes repartir todos los jugadores del draft manual.',
            ]);
        }

        $teamA = collect();
        $teamB = collect();

        foreach ($pool as $entry) {
            $team = $assignments[$entry->id] ?? null;

            if (! in_array($team, ['A', 'B'], true)) {
                throw ValidationException::withMessages([
                    'assignments' => 'Cada jugador debe pertenecer a un equipo valido.',
                ]);
            }

            if ($team === 'A') {
                $teamA->push($entry);
            } else {
                $teamB->push($entry);
            }
        }

        if ($teamA->count() !== 5 || $teamB->count() !== 5) {
            throw ValidationException::withMessages([
                'assignments' => 'Cada equipo debe tener exactamente 5 jugadores.',
            ]);
        }

        if ($teamA->where('entry_type', 'guest')->count() > 2 || $teamB->where('entry_type', 'guest')->count() > 2) {
            throw ValidationException::withMessages([
                'assignments' => 'Cada equipo admite como maximo 2 invitados.',
            ]);
        }

        return ['A' => $teamA, 'B' => $teamB];
    }

    /**
     * @return array<string, Collection<int, LeagueSessionEntry>>
     */
    private function arrivalDraft(Collection $pool): array
    {
        $ordered = $pool->sortBy('arrival_order')->values();

        return [
            'A' => $ordered->take(5)->values(),
            'B' => $ordered->slice(5, 5)->values(),
        ];
    }

    /**
     * @return array<string, Collection<int, LeagueSessionEntry>>
     */
    private function autoDraft(Collection $pool, $season): array
    {
        $seasonStats = $this->seasonStats($season->sessions)->keyBy('season_key');
        $ordered = $pool->sortByDesc(function (LeagueSessionEntry $entry) use ($seasonStats): float {
            $seasonKey = $this->seasonKeyForIdentity([
                'player_id' => $entry->player?->id,
                'name' => $entry->entry_type === 'guest' ? $entry->guest_name : $entry->player?->display_name,
                'is_guest' => $entry->entry_type === 'guest',
            ]);
            $seasonRow = $seasonStats->get($seasonKey);
            $combined = $this->combinedScoutRating($entry->player?->scoutProfile, $seasonRow);

            return $combined['rating'];
        })->values();

        $teamA = collect();
        $teamB = collect();
        $scoreA = 0.0;
        $scoreB = 0.0;

        foreach ($ordered as $entry) {
            $seasonKey = $this->seasonKeyForIdentity([
                'player_id' => $entry->player?->id,
                'name' => $entry->entry_type === 'guest' ? $entry->guest_name : $entry->player?->display_name,
                'is_guest' => $entry->entry_type === 'guest',
            ]);
            $seasonRow = $seasonStats->get($seasonKey);
            $combined = $this->combinedScoutRating($entry->player?->scoutProfile, $seasonRow);
            $weight = $combined['rating'];

            $guestCountA = $teamA->where('entry_type', 'guest')->count();
            $guestCountB = $teamB->where('entry_type', 'guest')->count();
            $canA = $teamA->count() < 5 && ($entry->entry_type !== 'guest' || $guestCountA < 2);
            $canB = $teamB->count() < 5 && ($entry->entry_type !== 'guest' || $guestCountB < 2);

            if ($canA && (! $canB || $scoreA <= $scoreB)) {
                $teamA->push($entry);
                $scoreA += $weight;
            } else {
                $teamB->push($entry);
                $scoreB += $weight;
            }
        }

        return ['A' => $teamA->values(), 'B' => $teamB->values()];
    }

    private function createOpenGameFromCurrentCourt(LeagueSession $session, User $user, ?string $draftMode, string $phase): ?LeagueSessionGame
    {
        $teamA = $this->onCourtEntries($session)->where('team_side', 'A')->sortBy('arrival_order')->values();
        $teamB = $this->onCourtEntries($session)->where('team_side', 'B')->sortBy('arrival_order')->values();

        if ($teamA->isEmpty() || $teamB->isEmpty()) {
            return null;
        }

        return $session->games()->create([
            'game_number' => $session->current_game_number,
            'draft_mode' => $draftMode,
            'status' => 'open',
            'phase' => $phase,
            'team_a_snapshot' => $teamA->map(fn (LeagueSessionEntry $entry): array => $this->entrySnapshot($entry))->all(),
            'team_b_snapshot' => $teamB->map(fn (LeagueSessionEntry $entry): array => $this->entrySnapshot($entry))->all(),
            'player_points' => [],
            'player_shots' => [],
            'started_at' => now(),
            'created_by_user_id' => $user->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function entrySnapshot(LeagueSessionEntry $entry): array
    {
        return [
            'entry_id' => $entry->id,
            'player_id' => $entry->player?->id,
            'name' => $entry->entry_type === 'guest' ? $entry->guest_name : $entry->player?->display_name,
            'is_guest' => $entry->entry_type === 'guest',
            'jersey_number' => $entry->player?->jersey_number,
            'arrival_order' => $entry->arrival_order,
        ];
    }

    private function syncGameSnapshots(LeagueSessionGame $game, LeagueSession $session): void
    {
        $teamA = $this->onCourtEntries($session)->where('team_side', 'A')->sortBy('arrival_order')->values();
        $teamB = $this->onCourtEntries($session)->where('team_side', 'B')->sortBy('arrival_order')->values();

        $game->forceFill([
            'team_a_snapshot' => $teamA->map(fn (LeagueSessionEntry $entry): array => $this->entrySnapshot($entry))->all(),
            'team_b_snapshot' => $teamB->map(fn (LeagueSessionEntry $entry): array => $this->entrySnapshot($entry))->all(),
        ])->save();
    }

    private function requireOpenGame(LeagueSession $session): LeagueSessionGame
    {
        $game = $this->openGame($session);

        if ($game === null) {
            throw ValidationException::withMessages([
                'session' => 'No hay un juego activo en la jornada actual.',
            ]);
        }

        return $game;
    }

    private function guardEntryBelongsToSession(LeagueSession $session, LeagueSessionEntry $entry): void
    {
        if ($entry->league_session_id !== $session->id) {
            throw ValidationException::withMessages([
                'entry_id' => 'Ese jugador no pertenece a la jornada activa.',
            ]);
        }
    }

    private function guardEntryOnCourt(LeagueSessionEntry $entry): void
    {
        if ($entry->session_state !== 'on_court' || $entry->team_side === null) {
            throw ValidationException::withMessages([
                'entry_id' => 'Solo puedes actuar sobre jugadores que esten en cancha.',
            ]);
        }
    }

    private function guardPointsValue(int $points): void
    {
        if (! in_array($points, [1, 2, 3], true)) {
            throw ValidationException::withMessages([
                'points' => 'La jugada debe valer 1, 2 o 3 puntos.',
            ]);
        }
    }

    private function storeBeforeState(LeagueSession $session, LeagueSessionGame $game, string $actionType, User $user): void
    {
        $session->actionLogs()->create([
            'league_session_game_id' => $game->id,
            'sequence' => ((int) $session->actionLogs()
                ->where('league_session_game_id', $game->id)
                ->max('sequence')) + 1,
            'action_type' => $actionType,
            'before_state' => $this->snapshotState($session, $game),
            'recorded_by_user_id' => $user->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotState(LeagueSession $session, LeagueSessionGame $game): array
    {
        return [
            'session' => [
                'status' => $session->status,
                'current_game_number' => $session->current_game_number,
                'rotation_state' => $session->rotation_state,
            ],
            'entries' => $session->entries
                ->map(fn (LeagueSessionEntry $entry): array => [
                    'id' => $entry->id,
                    'session_state' => $entry->session_state,
                    'team_side' => $entry->team_side,
                    'queue_position' => $entry->queue_position,
                ])
                ->values()
                ->all(),
            'game' => [
                'team_a_score' => $game->team_a_score,
                'team_b_score' => $game->team_b_score,
                'player_points' => $game->player_points ?? [],
                'player_shots' => $game->player_shots ?? [],
                'team_a_snapshot' => $game->team_a_snapshot ?? [],
                'team_b_snapshot' => $game->team_b_snapshot ?? [],
            ],
        ];
    }

    private function restoreSnapshot(LeagueSession $session, LeagueSessionGame $game, array $snapshot): void
    {
        $session->forceFill([
            'status' => $snapshot['session']['status'] ?? $session->status,
            'current_game_number' => $snapshot['session']['current_game_number'] ?? $session->current_game_number,
            'rotation_state' => $snapshot['session']['rotation_state'] ?? $session->rotation_state,
        ])->save();

        $entries = $session->entries()->get()->keyBy('id');

        foreach ($snapshot['entries'] ?? [] as $row) {
            /** @var LeagueSessionEntry|null $entry */
            $entry = $entries->get($row['id']);

            if ($entry === null) {
                continue;
            }

            $entry->forceFill([
                'session_state' => $row['session_state'],
                'team_side' => $row['team_side'],
                'queue_position' => $row['queue_position'],
            ])->save();
        }

        $game->forceFill([
            'team_a_score' => $snapshot['game']['team_a_score'] ?? 0,
            'team_b_score' => $snapshot['game']['team_b_score'] ?? 0,
            'player_points' => $snapshot['game']['player_points'] ?? [],
            'player_shots' => $snapshot['game']['player_shots'] ?? [],
            'team_a_snapshot' => $snapshot['game']['team_a_snapshot'] ?? [],
            'team_b_snapshot' => $snapshot['game']['team_b_snapshot'] ?? [],
        ])->save();
    }

    private function nextQueueReplacement(LeagueSession $session, string $teamSide): ?LeagueSessionEntry
    {
        $guestCount = $this->onCourtEntries($session)
            ->where('team_side', $teamSide)
            ->where('entry_type', 'guest')
            ->count();

        /** @var LeagueSessionEntry|null $entry */
        $entry = $this->queueEntries($session)
            ->first(fn (LeagueSessionEntry $candidate): bool => $candidate->entry_type !== 'guest' || $guestCount < 2);

        return $entry;
    }

    private function resequenceQueue(LeagueSession $session): void
    {
        $this->queueEntries($session)
            ->values()
            ->each(function (LeagueSessionEntry $entry, int $index): void {
                $entry->forceFill([
                    'queue_position' => $index + 1,
                ])->save();
            });
    }

    private function applyRotationAfterGame(LeagueSession $session, string $winnerSide, User $user): void
    {
        $state = $this->rotationState($session);
        $loserSide = $winnerSide === 'A' ? 'B' : 'A';
        $winnerEntries = $this->onCourtEntries($session)->where('team_side', $winnerSide)->values();
        $loserEntries = $this->onCourtEntries($session)->where('team_side', $loserSide)->values();

        if ($state['double_rotation_mode']) {
            $this->sendLosersToQueue($session, $loserEntries);
            $this->waitingChampionEntries($session)
                ->each(function (LeagueSessionEntry $entry) use ($winnerSide): void {
                    $entry->forceFill([
                        'session_state' => 'on_court',
                        'team_side' => $winnerSide === 'A' ? 'B' : 'A',
                        'queue_position' => null,
                    ])->save();
                });

            $session->forceFill([
                'current_game_number' => $session->current_game_number + 1,
                'rotation_state' => [
                    'streak_team' => $winnerSide,
                    'streak_count' => 1,
                    'double_rotation_mode' => false,
                    'waiting_champion_team' => null,
                ],
            ])->save();

            $this->createOpenGameFromCurrentCourt($session->fresh(['entries.player', 'games']), $user, null, 'champion_return');

            return;
        }

        $streakCount = $state['streak_team'] === $winnerSide
            ? $state['streak_count'] + 1
            : 1;
        $fullRotation = $session->entries->count() >= 20;
        $this->sendLosersToQueue($session, $loserEntries);

        if ($streakCount >= 2 && $fullRotation) {
            $winnerEntries->each(function (LeagueSessionEntry $entry): void {
                $entry->forceFill([
                    'session_state' => 'waiting_champion',
                    'team_side' => null,
                    'queue_position' => null,
                ])->save();
            });

            $this->pullNextTenToPool($session);
            $session->forceFill([
                'current_game_number' => $session->current_game_number + 1,
                'rotation_state' => [
                    'streak_team' => null,
                    'streak_count' => 0,
                    'double_rotation_mode' => true,
                    'waiting_champion_team' => $winnerSide,
                ],
            ])->save();

            return;
        }

        $allowedGuestSlots = max(0, 2 - $winnerEntries->where('entry_type', 'guest')->count());
        $incoming = $this->pullNextFiveToCourt($session, $loserSide, $allowedGuestSlots);
        $session->forceFill([
            'current_game_number' => $session->current_game_number + 1,
            'rotation_state' => [
                'streak_team' => $winnerSide,
                'streak_count' => $streakCount,
                'double_rotation_mode' => false,
                'waiting_champion_team' => null,
            ],
        ])->save();

        if ($incoming->isNotEmpty()) {
            $this->createOpenGameFromCurrentCourt(
                $session->fresh(['entries.player', 'games']),
                $user,
                null,
                $streakCount >= 2 ? 'champion_stays' : 'standard',
            );
        }
    }

    private function sendLosersToQueue(LeagueSession $session, Collection $entries): void
    {
        $nextPosition = (int) $this->queueEntries($session)->max('queue_position');

        foreach ($entries as $entry) {
            if ($entry->entry_type === 'guest') {
                $entry->forceFill([
                    'session_state' => 'removed',
                    'team_side' => null,
                    'queue_position' => null,
                ])->save();

                continue;
            }

            $nextPosition++;
            $entry->forceFill([
                'session_state' => 'queued',
                'team_side' => null,
                'queue_position' => $nextPosition,
            ])->save();
        }
    }

    private function pullNextTenToPool(LeagueSession $session): void
    {
        $this->queueEntries($session)
            ->take(10)
            ->values()
            ->each(function (LeagueSessionEntry $entry): void {
                $entry->forceFill([
                    'session_state' => 'pool',
                    'team_side' => null,
                    'queue_position' => null,
                ])->save();
            });

        $this->resequenceQueue($session->fresh('entries'));
    }

    /**
     * @return Collection<int, LeagueSessionEntry>
     */
    private function pullNextFiveToCourt(LeagueSession $session, string $teamSide, int $allowedGuestSlots): Collection
    {
        $selected = collect();

        foreach ($this->queueEntries($session) as $entry) {
            if ($selected->count() >= 5) {
                break;
            }

            if ($entry->entry_type === 'guest' && $allowedGuestSlots <= 0) {
                continue;
            }

            if ($entry->entry_type === 'guest') {
                $allowedGuestSlots--;
            }

            $selected->push($entry);
        }

        $selected->each(function (LeagueSessionEntry $entry) use ($teamSide): void {
            $entry->forceFill([
                'session_state' => 'on_court',
                'team_side' => $teamSide,
                'queue_position' => null,
            ])->save();
        });

        $this->resequenceQueue($session->fresh('entries'));

        return $selected;
    }

    /**
     * @return array<string, mixed>
     */
    private function rotationState(LeagueSession $session): array
    {
        return array_replace([
            'streak_team' => null,
            'streak_count' => 0,
            'double_rotation_mode' => false,
            'waiting_champion_team' => null,
        ], $session->rotation_state ?? []);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function sessionStats(LeagueSession $session): Collection
    {
        return $this->metricsFromGames($session->games->where('status', 'completed')->values())
            ->sortByDesc('points')
            ->values();
    }

    /**
     * @param  Collection<int, LeagueSession>  $sessions
     * @return Collection<int, array<string, mixed>>
     */
    private function seasonStats(Collection $sessions): Collection
    {
        $metrics = collect();

        foreach ($sessions as $session) {
            $sessionMetrics = $this->metricsFromGames($session->games->where('status', 'completed')->values());

            foreach ($sessionMetrics as $row) {
                $seasonKey = $row['season_key'];
                $existing = $metrics->get($seasonKey, [
                    'season_key' => $seasonKey,
                    'identity' => $row['identity'],
                    'points' => 0,
                    'games' => 0,
                    'wins' => 0,
                    'losses' => 0,
                    'shots' => ['1' => 0, '2' => 0, '3' => 0],
                    'points_allowed' => 0,
                    'games_defended' => 0,
                    'sessions_attended' => 0,
                ]);

                $existing['points'] += $row['points'];
                $existing['games'] += $row['games'];
                $existing['wins'] += $row['wins'];
                $existing['losses'] += $row['losses'];
                $existing['points_allowed'] += $row['points_allowed'];
                $existing['games_defended'] += $row['games_defended'];
                foreach (['1', '2', '3'] as $shotType) {
                    $existing['shots'][$shotType] += (int) ($row['shots'][$shotType] ?? 0);
                }

                $metrics->put($seasonKey, $existing);
            }

            foreach ($session->entries as $entry) {
                $identity = [
                    'player_id' => $entry->player?->id,
                    'name' => $entry->entry_type === 'guest' ? $entry->guest_name : $entry->player?->display_name,
                    'is_guest' => $entry->entry_type === 'guest',
                    'jersey_number' => $entry->player?->jersey_number,
                ];
                $seasonKey = $this->seasonKeyForIdentity($identity);
                $existing = $metrics->get($seasonKey, [
                    'season_key' => $seasonKey,
                    'identity' => $identity,
                    'points' => 0,
                    'games' => 0,
                    'wins' => 0,
                    'losses' => 0,
                    'shots' => ['1' => 0, '2' => 0, '3' => 0],
                    'points_allowed' => 0,
                    'games_defended' => 0,
                    'sessions_attended' => 0,
                ]);
                $existing['sessions_attended']++;
                $metrics->put($seasonKey, $existing);
            }
        }

        return $metrics
            ->values()
            ->map(function (array $row): array {
                $row['points_per_game'] = $row['games'] > 0
                    ? round($row['points'] / $row['games'], 1)
                    : 0;
                $row['win_rate'] = $row['games'] > 0
                    ? (int) round(($row['wins'] / $row['games']) * 100)
                    : 0;
                $row['points_allowed_per_game'] = $row['games_defended'] > 0
                    ? round($row['points_allowed'] / $row['games_defended'], 1)
                    : null;

                return $row;
            })
            ->sortByDesc('points')
            ->values();
    }

    /**
     * @param  Collection<int, LeagueSessionGame>  $games
     * @return Collection<int, array<string, mixed>>
     */
    private function metricsFromGames(Collection $games): Collection
    {
        $stats = collect();

        foreach ($games as $game) {
            $teamA = collect($game->team_a_snapshot ?? []);
            $teamB = collect($game->team_b_snapshot ?? []);
            $points = $game->player_points ?? [];
            $shots = $game->player_shots ?? [];

            foreach ($teamA as $participant) {
                $this->accumulateGameStats($stats, $participant, $points, $shots, $game->winner_side === 'A', $game->team_b_score);
            }

            foreach ($teamB as $participant) {
                $this->accumulateGameStats($stats, $participant, $points, $shots, $game->winner_side === 'B', $game->team_a_score);
            }
        }

        return $stats->values();
    }

    /**
     * @param  array<string, int>  $points
     * @param  array<string, array<string, int>>  $shots
     */
    private function accumulateGameStats(Collection $stats, array $participant, array $points, array $shots, bool $won, int $pointsAllowed): void
    {
        $seasonKey = $this->seasonKeyForIdentity($participant);
        $entryKey = (string) $participant['entry_id'];
        $existing = $stats->get($seasonKey, [
            'season_key' => $seasonKey,
            'identity' => [
                'player_id' => $participant['player_id'],
                'name' => $participant['name'],
                'is_guest' => $participant['is_guest'],
                'jersey_number' => $participant['jersey_number'],
            ],
            'points' => 0,
            'games' => 0,
            'wins' => 0,
            'losses' => 0,
            'shots' => ['1' => 0, '2' => 0, '3' => 0],
            'points_allowed' => 0,
            'games_defended' => 0,
        ]);

        $existing['points'] += (int) ($points[$entryKey] ?? 0);
        $existing['games']++;
        $existing['wins'] += $won ? 1 : 0;
        $existing['losses'] += $won ? 0 : 1;
        $existing['points_allowed'] += $pointsAllowed;
        $existing['games_defended']++;
        foreach (['1', '2', '3'] as $shotType) {
            $existing['shots'][$shotType] += (int) ($shots[$entryKey][$shotType] ?? 0);
        }

        $stats->put($seasonKey, $existing);
    }

    /**
     * @return array{rating: float, manual_rating: float, stat_rating: ?float, has_stats: bool}
     */
    private function combinedScoutRating(?LeaguePlayerScoutProfile $profile, ?array $seasonRow): array
    {
        $manualRating = $this->manualScoutRating($profile);
        $statRating = $this->seasonScoutRating($seasonRow);

        if ($statRating === null) {
            return ['rating' => $manualRating, 'manual_rating' => $manualRating, 'stat_rating' => null, 'has_stats' => false];
        }

        if ($manualRating <= 0) {
            return ['rating' => $statRating, 'manual_rating' => 0, 'stat_rating' => $statRating, 'has_stats' => true];
        }

        $games = (int) ($seasonRow['games'] ?? 0);
        $statWeight = $games < 5 ? 0.2 : ($games < 15 ? 0.4 : 0.6);

        return [
            'rating' => round(($manualRating * (1 - $statWeight)) + ($statRating * $statWeight), 1),
            'manual_rating' => $manualRating,
            'stat_rating' => $statRating,
            'has_stats' => true,
        ];
    }

    private function manualScoutRating(?LeaguePlayerScoutProfile $profile): float
    {
        if ($profile === null) {
            return 0.0;
        }

        $total = collect(self::SCOUT_ATTRS)->sum(fn (string $field): int => (int) $profile->{$field});

        return round($total / count(self::SCOUT_ATTRS), 1);
    }

    private function seasonScoutRating(?array $seasonRow): ?float
    {
        if ($seasonRow === null || ($seasonRow['games'] ?? 0) < 3) {
            return null;
        }

        $pointsPerGame = (float) $seasonRow['points_per_game'];
        $winRate = (float) ($seasonRow['win_rate'] ?? 0);
        $pointsAllowed = (float) ($seasonRow['points_allowed_per_game'] ?? 18);
        $shots = $seasonRow['shots'] ?? ['1' => 0, '2' => 0, '3' => 0];
        $totalShots = (int) $shots['1'] + (int) $shots['2'] + (int) $shots['3'];
        $tripleRate = $totalShots > 0 ? ((int) $shots['3'] / $totalShots) * 5 : 0;
        $diversity = $totalShots > 0
            ? min(5.0, round((((int) $shots['1'] > 0 ? 1 : 0) + ((int) $shots['2'] > 0 ? 1 : 0) + ((int) $shots['3'] > 0 ? 1 : 0)) * 1.6, 1))
            : 0;
        $offense = min(5.0, round($pointsPerGame, 1));
        $teamPlay = min(5.0, round(($winRate / 100) * 5, 1));
        $defense = max(0.5, min(5.0, round((18 - $pointsAllowed) / 3, 1) + 2.5));

        return round((($teamPlay * 0.25) + ($offense * 0.2) + ($defense * 0.2) + ($tripleRate * 0.2) + ($diversity * 0.15)), 1);
    }

    /**
     * @param  array<string, mixed>  $identity
     */
    private function seasonKeyForIdentity(array $identity): string
    {
        if (($identity['player_id'] ?? null) !== null) {
            return 'player:'.$identity['player_id'];
        }

        return 'guest:'.mb_strtolower((string) $identity['name']);
    }

    private function completedGamesForEntry(LeagueSession $session, LeagueSessionEntry $entry): int
    {
        return $session->games
            ->where('status', 'completed')
            ->sum(function (LeagueSessionGame $game) use ($entry): int {
                return collect(array_merge($game->team_a_snapshot ?? [], $game->team_b_snapshot ?? []))
                    ->contains(fn (array $participant): bool => (int) ($participant['entry_id'] ?? 0) === $entry->id)
                    ? 1
                    : 0;
            });
    }

    private function entryPointsToday(LeagueSession $session, LeagueSessionEntry $entry, bool $includeLive): int
    {
        $points = $session->games
            ->where('status', 'completed')
            ->sum(fn (LeagueSessionGame $game): int => (int) (($game->player_points ?? [])[(string) $entry->id] ?? 0));

        if (! $includeLive) {
            return $points;
        }

        $openGame = $this->openGame($session);

        if ($openGame === null) {
            return $points;
        }

        return $points + (int) (($openGame->player_points ?? [])[(string) $entry->id] ?? 0);
    }

    /**
     * @param  Collection<int, LeagueSession>  $sessions
     */
    private function seasonRevenueCents(Collection $sessions): int
    {
        return $sessions->sum(function (LeagueSession $session): int {
            $memberRevenue = $session->entries
                ->where('entry_type', 'player')
                ->where('current_cut_paid', true)
                ->count() * (int) ($session->cut?->member_fee_amount_cents ?? 0);
            $guestRevenue = $session->entries
                ->where('entry_type', 'guest')
                ->where('guest_fee_paid', true)
                ->count() * (int) ($session->cut?->guest_fee_amount_cents ?? 0);

            return $memberRevenue + $guestRevenue;
        });
    }
}

<?php

namespace App\Services\LeagueOperations;

use App\Models\LeaguePlayer;
use App\Models\LeaguePlayerScoutProfile;
use App\Models\LeagueSession;
use App\Models\LeagueSessionActionLog;
use App\Models\LeagueSessionEntry;
use App\Models\LeagueSessionGame;
use App\Models\User;
use Carbon\CarbonImmutable;
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
        $context = $this->operationalContext($user, null, false);
        $session = $context['session'] ?? $this->emptyCompetitionSession($context['cut']);
        $context['session'] = $session;
        $session = $context['session'];
        $openGame = $this->openGame($session);
        $seasonStats = $context['season'] === null
            ? collect()
            : $this->seasonStats($context['season']->sessions)->keyBy('season_key');
        $scoutStatBaseline = $this->scoutStatBaseline($seasonStats);
        $completedGames = $session->games
            ->where('status', 'completed')
            ->sortByDesc('game_number')
            ->values();
        $summary = $this->queueSummary($session, $context['cut']);

        if (! $context['role']->canManageLeague()) {
            $summary['cash_collected_cents'] = null;
        }

        return array_merge($this->basePayload($context), [
            'game' => [
                'state' => $this->gameState($session, $openGame),
                'draft' => [
                    'entries' => $this->pendingPoolEntries($session)
                        ->map(fn (LeagueSessionEntry $entry): array => $this->draftEntryCard($entry, $seasonStats, $scoutStatBaseline))
                        ->values()
                        ->all(),
                    'can_start' => $openGame === null && $this->pendingPoolEntries($session)->count() === 10,
                ],
                'clock' => $this->clockPayload($session),
                'rotation_notice' => $this->rotationNoticePayload($session),
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
                        'Eq. %s ganó %s-%s',
                        $game->winner_side,
                        $game->winner_side === 'A' ? $game->team_a_score : $game->team_b_score,
                        $game->winner_side === 'A' ? $game->team_b_score : $game->team_a_score,
                    ),
                ])->all(),
                'summary' => $summary,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function queuePageData(User $user, ?int $selectedSessionId = null): array
    {
        $context = $this->operationalContext($user, $selectedSessionId, false);
        $context['session'] = $context['session'] ?? $this->emptyCompetitionSession($context['cut']);
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
                'can_reorder' => false,
            ],
        ]);
    }

    /**
     * @param  array<int, int>  $orderedEntryIds
     */
    public function reorderQueue(User $user, array $orderedEntryIds): void
    {
        throw ValidationException::withMessages([
            'session' => 'La cola operativa no se reordena desde este modulo. Usa Llegada antes del primer juego.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function statsPageData(User $user, ?int $selectedSessionId = null): array
    {
        $context = $this->operationalContext($user, $selectedSessionId);
        $session = $context['session'];
        $stats = $session === null ? collect() : $this->sessionStats($session);

        return array_merge($this->basePayload($context), [
            'stats' => [
                'games_count' => $session?->games->where('status', 'completed')->count() ?? 0,
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
    public function tablePageData(User $user, ?int $selectedSessionId = null): array
    {
        $context = $this->operationalContext($user, $selectedSessionId);
        $session = $context['session'];
        $stats = $session === null
            ? collect()
            : $this->sessionStats($session)
                ->filter(fn (array $row): bool => $row['games'] > 0 || $row['points'] > 0)
                ->values();

        return array_merge($this->basePayload($context), [
            'table' => [
                'banner' => [
                    'games' => $session?->games->where('status', 'completed')->count() ?? 0,
                    'points' => $stats->sum('points'),
                    'players' => $session?->entries->count() ?? 0,
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

        if ($season === null) {
            return array_merge($this->basePayload($context), [
                'season' => [
                    'season' => [
                        'id' => null,
                        'label' => 'Sin temporada activa',
                        'starts_on' => null,
                        'sessions_count' => 0,
                        'totals' => [
                            'games' => 0,
                            'points' => 0,
                            'revenue_cents' => 0,
                            'show_revenue' => $context['role']->canManageLeague(),
                        ],
                    ],
                    'leaders' => [
                        'points' => [],
                        'wins' => [],
                        'games' => [],
                    ],
                    'sessions' => [],
                    'profiles' => [],
                ],
            ]);
        }

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
        $seasonStats = $context['season'] === null
            ? collect()
            : $this->seasonStats($context['season']->sessions)->keyBy('season_key');
        $scoutStatBaseline = $this->scoutStatBaseline($seasonStats);
        $players = $this->operations->activePlayablePlayersQuery($league)
            ->with('scoutProfile')
            ->orderBy('display_name')
            ->get();

        $rows = $players->map(function (LeaguePlayer $player) use ($seasonStats, $scoutStatBaseline): array {
            $profile = $player->scoutProfile;
            $seasonKey = $this->seasonKeyForIdentity([
                'player_id' => $player->id,
                'name' => $player->display_name,
                'is_guest' => false,
            ]);
            $seasonRow = $seasonStats->get($seasonKey);
            $combined = $this->combinedScoutRating($profile, $seasonRow, $scoutStatBaseline);

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

        $profiledPlayers = $players
            ->filter(fn (LeaguePlayer $player): bool => $player->scoutProfile !== null)
            ->count();
        $autoPreviewPool = $session === null
            ? collect()
            : $this->pendingPoolEntries($session);
        $autoPreview = $autoPreviewPool->count() === 10
            ? $this->scoutAutoPreview($autoPreviewPool, $seasonStats, $scoutStatBaseline)
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
                    ->filter(fn (array $row): bool => $row['manual_rating'] > 0 || $row['has_stats'])
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

    /**
     * @return array<string, mixed>
     */
    public function destroySession(User $user, LeagueSession $session): array
    {
        $context = $this->operations->requireAdminContext($user);

        if ($session->league_id !== $context['league']->id) {
            throw ValidationException::withMessages([
                'session_id' => 'La jornada seleccionada no existe dentro de la liga activa.',
            ]);
        }

        DB::transaction(function () use ($session): void {
            $session->delete();
        });

        return $this->statsPageData($user);
    }

    public function confirmDraft(
        User $user,
        string $mode,
        array $assignments = [],
        string $captainMode = 'arrival',
        array $captains = [],
    ): void
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

        $seasonStats = $this->seasonStats($context['season']->sessions)->keyBy('season_key');
        $randomSeed = $this->deterministicDraftSeed($session, $mode);
        $teams = match ($mode) {
            'auto' => $this->autoDraft(
                $pool,
                $seasonStats,
            ),
            'arrival' => $this->arrivalDraft($pool),
            'random' => $this->randomDraft($pool, $randomSeed),
            'manual' => $this->manualDraft($pool, $assignments),
            default => throw ValidationException::withMessages([
                'mode' => 'Modo de reparto invalido.',
            ]),
        };
        $captainIds = $this->resolveDraftCaptains($teams, $captainMode, $captains, $randomSeed);
        $orderedTeams = $this->orderDraftTeamsForGame($teams, $captainIds);

        DB::transaction(function () use ($session, $user, $mode, $orderedTeams, $captainIds): void {
            foreach ($orderedTeams['A'] as $entry) {
                $entry->forceFill([
                    'session_state' => 'on_court',
                    'team_side' => 'A',
                    'queue_position' => null,
                ])->save();
            }

            foreach ($orderedTeams['B'] as $entry) {
                $entry->forceFill([
                    'session_state' => 'on_court',
                    'team_side' => 'B',
                    'queue_position' => null,
                ])->save();
            }

            $session->forceFill([
                'status' => 'in_progress',
            ])->save();

            $this->createOpenGame(
                $session->fresh(['entries.player.scoutProfile', 'games']),
                $user,
                $mode,
                'standard',
                $orderedTeams['A'],
                $orderedTeams['B'],
                $captainIds,
            );
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
            $this->resetClockState($session->fresh());
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
                'clock_remaining_seconds' => $session->clock_duration_seconds,
                'clock_state' => 'paused',
                'clock_started_at' => null,
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

            $this->resetClockState($session);
        });
    }

    public function configureClock(User $user, int $durationSeconds): void
    {
        $context = $this->adminContext($user);
        $session = $context['session'];

        if ($durationSeconds < 60 || $durationSeconds > 7200) {
            throw ValidationException::withMessages([
                'duration_seconds' => 'El cronómetro debe estar entre 1 y 120 minutos.',
            ]);
        }

        if (! $this->canConfigureClock($session)) {
            throw ValidationException::withMessages([
                'clock' => 'Solo puedes cambiar el cronómetro cuando está reiniciado.',
            ]);
        }

        $session->forceFill([
            'clock_duration_seconds' => $durationSeconds,
            'clock_remaining_seconds' => $durationSeconds,
            'clock_state' => 'paused',
            'clock_started_at' => null,
        ])->save();
    }

    public function startClock(User $user): void
    {
        $context = $this->adminContext($user);
        $session = $context['session'];

        if ($session->clock_duration_seconds === null || $session->clock_remaining_seconds === null) {
            throw ValidationException::withMessages([
                'clock' => 'Configura primero la duración del cronómetro.',
            ]);
        }

        $this->requireOpenGame($session);

        if ($this->clockRemainingSeconds($session) === 0) {
            $this->resetClockState($session);
        }

        $session->forceFill([
            'clock_remaining_seconds' => $this->clockRemainingSeconds($session) ?? $session->clock_duration_seconds,
            'clock_state' => 'running',
            'clock_started_at' => now(),
        ])->save();
    }

    public function pauseClock(User $user): void
    {
        $context = $this->adminContext($user);
        $session = $context['session'];

        $session->forceFill([
            'clock_remaining_seconds' => $this->clockRemainingSeconds($session),
            'clock_state' => $this->clockRemainingSeconds($session) === 0 ? 'finished' : 'paused',
            'clock_started_at' => null,
        ])->save();
    }

    public function resetClock(User $user): void
    {
        $context = $this->adminContext($user);
        $this->resetClockState($context['session']);
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
    private function operationalContext(User $user, ?int $selectedSessionId = null, bool $requireSession = false): array
    {
        $context = $this->operations->requireOperationalContext($user);
        $activeCut = $this->operations->activeCutForLeague($context['league']);
        $session = $this->loadCompetitionSession(
            $context['league'],
            $activeCut,
            $selectedSessionId,
            $user,
            false,
            $requireSession,
        );
        $currentSession = $this->operations->currentSessionForLeague($context['league'], $activeCut, false);

        return [
            ...$context,
            'cut' => $session?->cut ?? $activeCut,
            'session' => $session,
            'season' => $this->contextSeasonForLeague($context['league'], $session, $user),
            'session_selector' => $this->sessionSelectorPayload(
                $context['league'],
                $session,
                $currentSession,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function adminContext(User $user): array
    {
        $context = $this->operations->requireAdminContext($user);
        $activeCut = $this->operations->activeCutForLeague($context['league']);
        $session = $this->loadCompetitionSession(
            $context['league'],
            $activeCut,
            null,
            $user,
            true,
            true,
        );
        $currentSession = $this->operations->currentSessionForLeague($context['league'], $activeCut, false);

        return [
            ...$context,
            'cut' => $session->cut ?? $activeCut,
            'session' => $session,
            'season' => $this->contextSeasonForLeague($context['league'], $session, $user),
            'session_selector' => $this->sessionSelectorPayload(
                $context['league'],
                $session,
                $currentSession ?? $session,
            ),
        ];
    }

    private function loadCompetitionSession(
        $league,
        $activeCut,
        ?int $selectedSessionId,
        User $user,
        bool $withActionLogs = false,
        bool $requireSession = false,
    ): ?LeagueSession {
        $session = $selectedSessionId === null
            ? $this->operations->currentSessionForLeague($league, $activeCut, false)
            : $this->operations->findSessionForLeague($league, $selectedSessionId);

        if ($session === null && $selectedSessionId !== null) {
            throw ValidationException::withMessages([
                'session_id' => 'La jornada seleccionada no existe dentro de la liga activa.',
            ]);
        }

        if ($session === null) {
            if ($requireSession) {
                throw ValidationException::withMessages([
                    'session' => 'No existe una jornada activa para la liga seleccionada.',
                ]);
            }

            return null;
        }

        $session = $this->seasons->attachSessionToActiveSeason($session, $league, $user);

        $relations = [
            'cut',
            'entries.player.scoutProfile',
            'games',
            'season.sessions.games',
            'season.sessions.entries.player',
        ];

        if ($withActionLogs) {
            $relations[] = 'actionLogs';
        }

        $session->loadMissing($relations);
        $session = $this->ensurePreparedEntryState($session);

        return $this->filterOperationalEntries($session, $league);
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

    private function filterOperationalEntries(LeagueSession $session, $league): LeagueSession
    {
        $allowedPlayerIds = $this->operations->activePlayablePlayersQuery($league)
            ->pluck('league_players.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $session->setRelation(
            'entries',
            $session->entries
                ->filter(fn (LeagueSessionEntry $entry): bool => $entry->entry_type === 'guest'
                    || in_array((int) $entry->league_player_id, $allowedPlayerIds, true))
                ->values(),
        );

        return $session;
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
            'session_selector' => $context['session_selector'],
            'session' => [
                'id' => $session?->id,
                'status' => $session?->status ?? 'idle',
                'session_date' => $session?->session_date?->toDateString(),
                'current_game_number' => $session?->current_game_number,
                'streak' => $session === null ? [
                    'team' => null,
                    'count' => 0,
                    'double_rotation_mode' => false,
                    'waiting_champion_team' => null,
                ] : $this->streakPayload($session),
                'participants_count' => $session?->entries->count() ?? 0,
                'pending_pool_count' => $session === null ? 0 : $this->pendingPoolEntries($session)->count(),
                'queue_count' => $session === null ? 0 : $this->queueEntries($session)->count(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionSelectorPayload($league, ?LeagueSession $selectedSession, ?LeagueSession $currentSession): array
    {
        return [
            'selected_session_id' => $selectedSession?->id,
            'sessions' => $this->operations->sessionHistoryForLeague($league)
                ->map(fn (LeagueSession $session): array => [
                    'id' => $session->id,
                    'session_date' => $session->session_date?->toDateString(),
                    'status' => $session->status,
                    'entries_count' => (int) ($session->entries_count ?? 0),
                    'completed_games_count' => (int) ($session->completed_games_count ?? 0),
                    'is_current' => $currentSession !== null && $session->is($currentSession),
                ])
                ->all(),
        ];
    }

    private function latestSessionForLeague($league): ?LeagueSession
    {
        $sessionId = $this->operations->sessionHistoryForLeague($league, 1)
            ->first()?->id;

        if ($sessionId === null) {
            return null;
        }

        return $this->operations->findSessionForLeague($league, (int) $sessionId);
    }

    private function contextSeasonForLeague($league, ?LeagueSession $session, User $user)
    {
        $season = $session?->season;

        if ($season === null) {
            $latestSession = $this->latestSessionForLeague($league);

            if ($latestSession !== null) {
                $latestSession = $this->seasons->attachSessionToActiveSeason($latestSession, $league, $user);
                $season = $latestSession->season;
            }
        }

        if ($season === null) {
            $season = $league->seasons()
                ->orderByDesc('starts_on')
                ->orderByDesc('id')
                ->first();
        }

        if ($season !== null) {
            $season->loadMissing([
                'sessions.games',
                'sessions.entries.player',
            ]);
        }

        return $season;
    }

    private function emptyCompetitionSession($cut): LeagueSession
    {
        $duration = LeagueOperationsService::DEFAULT_GAME_CLOCK_SECONDS;
        $session = new LeagueSession([
            'league_cut_id' => $cut->id,
            'session_date' => $this->operations->today()->toDateString(),
            'status' => 'idle',
            'current_game_number' => 0,
            'clock_duration_seconds' => $duration,
            'clock_remaining_seconds' => $duration,
            'clock_state' => 'paused',
            'initial_pool' => [],
            'initial_queue' => [],
            'rotation_state' => [],
        ]);

        $session->setRelation('cut', $cut);
        $session->setRelation('entries', collect());
        $session->setRelation('games', collect());

        return $session;
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
     * @return array<string, mixed>|null
     */
    private function rotationNoticePayload(LeagueSession $session): ?array
    {
        $notice = $this->rotationState($session)['notice'] ?? null;

        if (! is_array($notice)) {
            return null;
        }

        return [
            'key' => (string) ($notice['key'] ?? 'rotation'),
            'title' => (string) ($notice['title'] ?? 'Rotación'),
            'body' => array_values(array_map(
                static fn ($line): string => (string) $line,
                is_array($notice['body'] ?? null) ? $notice['body'] : [],
            )),
            'tone' => (string) ($notice['tone'] ?? 'warning'),
            'icon' => (string) ($notice['icon'] ?? 'rotate'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function clockPayload(LeagueSession $session): array
    {
        $durationSeconds = $session->clock_duration_seconds;
        $remainingSeconds = $this->clockRemainingSeconds($session);
        $state = $session->clock_state;

        if ($durationSeconds === null) {
            return [
                'duration_seconds' => null,
                'remaining_seconds' => null,
                'state' => 'unconfigured',
                'started_at' => null,
            ];
        }

        if ($state === 'running' && $remainingSeconds === 0) {
            $state = 'finished';
        } elseif ($remainingSeconds === 0) {
            $state = 'finished';
        } else {
            $state = $state === 'running' ? 'running' : 'paused';
        }

        return [
            'duration_seconds' => $durationSeconds,
            'remaining_seconds' => $remainingSeconds,
            'state' => $state,
            'started_at' => $session->clock_started_at?->toIso8601String(),
        ];
    }

    private function clockRemainingSeconds(LeagueSession $session): ?int
    {
        if ($session->clock_duration_seconds === null || $session->clock_remaining_seconds === null) {
            return null;
        }

        if ($session->clock_state !== 'running' || $session->clock_started_at === null) {
            return max(0, (int) $session->clock_remaining_seconds);
        }

        $elapsed = CarbonImmutable::parse($session->clock_started_at)
            ->diffInSeconds(now(), false);

        return max(0, (int) $session->clock_remaining_seconds - max(0, $elapsed));
    }

    private function resetClockState(LeagueSession $session): void
    {
        $session->forceFill([
            'clock_remaining_seconds' => $session->clock_duration_seconds,
            'clock_state' => 'paused',
            'clock_started_at' => null,
        ])->save();
    }

    private function canConfigureClock(LeagueSession $session): bool
    {
        if ($session->clock_duration_seconds === null || $session->clock_remaining_seconds === null) {
            return true;
        }

        return $session->clock_state !== 'running'
            && $this->clockRemainingSeconds($session) === $session->clock_duration_seconds;
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

            return $entry->current_cut_paid
                ? (int) $cut->member_fee_amount_cents
                : 0;
        });

        return [
            'games' => $completedGames->count(),
            'streak_label' => $this->formatStreakLabel($session),
            'current_streak' => $this->formatStreakLabel($session),
            'active_players' => $session->entries->count(),
            'guests' => $session->entries->where('entry_type', 'guest')->count(),
            'today_guests' => $session->entries->where('entry_type', 'guest')->count(),
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
        $snapshotMeta = collect($teamSide === 'A' ? ($game->team_a_snapshot ?? []) : ($game->team_b_snapshot ?? []))
            ->values()
            ->mapWithKeys(fn (array $row, int $index): array => [
                (int) ($row['entry_id'] ?? 0) => [
                    'order' => $index,
                    'is_captain' => (bool) ($row['is_captain'] ?? false),
                ],
            ]);

        return $this->onCourtEntries($session)
            ->where('team_side', $teamSide)
            ->sort(function (LeagueSessionEntry $left, LeagueSessionEntry $right) use ($snapshotMeta): int {
                $leftMeta = $snapshotMeta->get($left->id);
                $rightMeta = $snapshotMeta->get($right->id);

                if ($leftMeta !== null && $rightMeta !== null) {
                    return ($leftMeta['order'] ?? PHP_INT_MAX) <=> ($rightMeta['order'] ?? PHP_INT_MAX);
                }

                return ($left->arrival_order ?? PHP_INT_MAX) <=> ($right->arrival_order ?? PHP_INT_MAX);
            })
            ->values()
            ->map(function (LeagueSessionEntry $entry) use ($points, $shots, $snapshotMeta): array {
                $entryKey = (string) $entry->id;
                $meta = $snapshotMeta->get($entry->id, [
                    'is_captain' => false,
                ]);

                return [
                    ...$this->entryCard($entry),
                    'is_captain' => (bool) ($meta['is_captain'] ?? false),
                    'points' => (int) ($points[$entryKey] ?? 0),
                    'shots' => $shots[$entryKey] ?? ['1' => 0, '2' => 0, '3' => 0],
                ];
            })
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $seasonStats
     * @param  array<string, mixed>  $scoutStatBaseline
     * @return array<string, mixed>
     */
    private function draftEntryCard(LeagueSessionEntry $entry, Collection $seasonStats, array $scoutStatBaseline): array
    {
        $candidate = $this->scoutDraftCandidate($entry, $seasonStats, $scoutStatBaseline);

        return [
            ...$this->entryCard($entry),
            'preferred_position' => $entry->player?->scoutProfile?->position,
            'scout_role' => $candidate['role'],
            'auto_draft_rating' => $candidate['rating'],
        ];
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
            'preferred_position' => $entry->player?->scoutProfile?->position,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $seasonStats
     * @return array<string, mixed>
     */
    private function scoutAutoPreview(Collection $pool, Collection $seasonStats, array $scoutStatBaseline): array
    {
        $teams = $this->autoDraft($pool, $seasonStats, $scoutStatBaseline);
        $teamA = $teams['A']->map(
            fn (LeagueSessionEntry $entry): array => $this->scoutPreviewCard($entry, $seasonStats, $scoutStatBaseline)
        )->values();
        $teamB = $teams['B']->map(
            fn (LeagueSessionEntry $entry): array => $this->scoutPreviewCard($entry, $seasonStats, $scoutStatBaseline)
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
    private function scoutPreviewCard(LeagueSessionEntry $entry, Collection $seasonStats, array $scoutStatBaseline): array
    {
        $seasonKey = $this->seasonKeyForIdentity([
            'player_id' => $entry->player?->id,
            'name' => $entry->entry_type === 'guest' ? $entry->guest_name : $entry->player?->display_name,
            'is_guest' => $entry->entry_type === 'guest',
        ]);
        $seasonRow = $seasonStats->get($seasonKey);
        $combined = $this->combinedScoutRating($entry->player?->scoutProfile, $seasonRow, $scoutStatBaseline);

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
    private function randomDraft(Collection $pool, string $seed): array
    {
        $ordered = $pool
            ->sort(function (LeagueSessionEntry $left, LeagueSessionEntry $right) use ($seed): int {
                $leftWeight = $this->deterministicEntryOrderValue("{$seed}:draft", $left->id);
                $rightWeight = $this->deterministicEntryOrderValue("{$seed}:draft", $right->id);

                if ($leftWeight !== $rightWeight) {
                    return $leftWeight <=> $rightWeight;
                }

                return ($left->arrival_order ?? PHP_INT_MAX) <=> ($right->arrival_order ?? PHP_INT_MAX);
            })
            ->values();

        return [
            'A' => $ordered->take(5)->values(),
            'B' => $ordered->slice(5, 5)->values(),
        ];
    }

    /**
     * @return array<string, Collection<int, LeagueSessionEntry>>
     */
    private function autoDraft(Collection $pool, Collection $seasonStats, ?array $scoutStatBaseline = null): array
    {
        $scoutStatBaseline ??= $this->scoutStatBaseline($seasonStats);
        $ordered = $pool
            ->map(fn (LeagueSessionEntry $entry): array => $this->scoutDraftCandidate($entry, $seasonStats, $scoutStatBaseline))
            ->sort(function (array $left, array $right): int {
                $ratingComparison = $right['rating'] <=> $left['rating'];

                if ($ratingComparison !== 0) {
                    return $ratingComparison;
                }

                return $left['entry']->arrival_order <=> $right['entry']->arrival_order;
            })
            ->values();

        $teamA = collect();
        $teamB = collect();
        $scoreA = 0.0;
        $scoreB = 0.0;

        foreach ($ordered as $candidate) {
            /** @var LeagueSessionEntry $entry */
            $entry = $candidate['entry'];
            $weight = $candidate['rating'];

            $canA = $teamA->count() < 5;
            $canB = $teamB->count() < 5;

            if (! $canA && ! $canB) {
                throw ValidationException::withMessages([
                    'session' => 'No se puede balancear el draft automático con más de 10 jugadores.',
                ]);
            }

            if ($canA && ! $canB) {
                $teamA->push($candidate);
                $scoreA += $weight;

                continue;
            }

            if ($canB && ! $canA) {
                $teamB->push($candidate);
                $scoreB += $weight;

                continue;
            }

            $assignToA = false;

            if ($teamA->count() < 5 && $teamB->count() < 5) {
                if ($candidate['role'] === 'Anotador') {
                    $scorersA = $teamA->filter(fn (array $item): bool => $item['role'] === 'Anotador')->count();
                    $scorersB = $teamB->filter(fn (array $item): bool => $item['role'] === 'Anotador')->count();
                    $assignToA = $scorersA === $scorersB ? $scoreA <= $scoreB : $scorersA <= $scorersB;
                } else {
                    $assignToA = $scoreA <= $scoreB;
                }
            } else {
                $assignToA = $teamA->count() < 5;
            }

            if ($assignToA) {
                $teamA->push($candidate);
                $scoreA += $weight;

                continue;
            }

            $teamB->push($candidate);
            $scoreB += $weight;
        }

        return [
            'A' => $teamA->pluck('entry')->values(),
            'B' => $teamB->pluck('entry')->values(),
        ];
    }

    private function deterministicDraftSeed(LeagueSession $session, string $mode): string
    {
        return implode(':', [
            'session',
            $session->id,
            $session->current_game_number,
            $mode,
        ]);
    }

    private function deterministicEntryOrderValue(string $seed, int $entryId): int
    {
        $hash = substr(hash('sha256', "{$seed}:{$entryId}"), 0, 12);

        return (int) hexdec($hash);
    }

    /**
     * @param  array<string, Collection<int, LeagueSessionEntry>>  $teams
     * @param  array<string, mixed>  $captains
     * @return array{A: int, B: int}
     */
    private function resolveDraftCaptains(array $teams, string $captainMode, array $captains, string $seed): array
    {
        return match ($captainMode) {
            'arrival' => [
                'A' => (int) $teams['A']->sortBy('arrival_order')->firstOrFail()->id,
                'B' => (int) $teams['B']->sortBy('arrival_order')->firstOrFail()->id,
            ],
            'random' => [
                'A' => (int) $teams['A']
                    ->sortBy(fn (LeagueSessionEntry $entry): int => $this->deterministicEntryOrderValue("{$seed}:captain:A", $entry->id))
                    ->firstOrFail()
                    ->id,
                'B' => (int) $teams['B']
                    ->sortBy(fn (LeagueSessionEntry $entry): int => $this->deterministicEntryOrderValue("{$seed}:captain:B", $entry->id))
                    ->firstOrFail()
                    ->id,
            ],
            'manual' => $this->validateManualDraftCaptains($teams, $captains),
            default => throw ValidationException::withMessages([
                'captain_mode' => 'Modo de capitan invalido.',
            ]),
        };
    }

    /**
     * @param  array<string, Collection<int, LeagueSessionEntry>>  $teams
     * @param  array<string, mixed>  $captains
     * @return array{A: int, B: int}
     */
    private function validateManualDraftCaptains(array $teams, array $captains): array
    {
        $captainA = (int) ($captains['A'] ?? 0);
        $captainB = (int) ($captains['B'] ?? 0);

        if ($captainA < 1 || $captainB < 1 || $captainA === $captainB) {
            throw ValidationException::withMessages([
                'captains' => 'Debes elegir un capitan valido para cada equipo.',
            ]);
        }

        if (! $teams['A']->contains(fn (LeagueSessionEntry $entry): bool => $entry->id === $captainA)) {
            throw ValidationException::withMessages([
                'captains.A' => 'El capitan del Equipo A debe pertenecer al mismo equipo.',
            ]);
        }

        if (! $teams['B']->contains(fn (LeagueSessionEntry $entry): bool => $entry->id === $captainB)) {
            throw ValidationException::withMessages([
                'captains.B' => 'El capitan del Equipo B debe pertenecer al mismo equipo.',
            ]);
        }

        return [
            'A' => $captainA,
            'B' => $captainB,
        ];
    }

    /**
     * @param  array<string, Collection<int, LeagueSessionEntry>>  $teams
     * @param  array{A: int, B: int}  $captainIds
     * @return array<string, Collection<int, LeagueSessionEntry>>
     */
    private function orderDraftTeamsForGame(array $teams, array $captainIds): array
    {
        return [
            'A' => $this->orderDraftTeam($teams['A'], $captainIds['A']),
            'B' => $this->orderDraftTeam($teams['B'], $captainIds['B']),
        ];
    }

    /**
     * @return Collection<int, LeagueSessionEntry>
     */
    private function orderDraftTeam(Collection $team, int $captainId): Collection
    {
        /** @var LeagueSessionEntry $captain */
        $captain = $team->firstOrFail(fn (LeagueSessionEntry $entry): bool => $entry->id === $captainId);

        return collect([$captain])
            ->concat(
                $team
                    ->reject(fn (LeagueSessionEntry $entry): bool => $entry->id === $captainId)
                    ->sortBy(fn (LeagueSessionEntry $entry): string => mb_strtolower((string) ($entry->entry_type === 'guest'
                        ? $entry->guest_name
                        : $entry->player?->display_name)))
                    ->values(),
            )
            ->values();
    }

    /**
     * @param  array{A: int, B: int}  $captainIds
     */
    private function createOpenGame(
        LeagueSession $session,
        User $user,
        ?string $draftMode,
        string $phase,
        Collection $teamA,
        Collection $teamB,
        array $captainIds = [],
    ): LeagueSessionGame
    {
        return $session->games()->create([
            'game_number' => $session->current_game_number,
            'draft_mode' => $draftMode,
            'status' => 'open',
            'phase' => $phase,
            'team_a_snapshot' => $teamA->map(
                fn (LeagueSessionEntry $entry): array => $this->entrySnapshot(
                    $entry,
                    ($captainIds['A'] ?? null) === $entry->id,
                )
            )->all(),
            'team_b_snapshot' => $teamB->map(
                fn (LeagueSessionEntry $entry): array => $this->entrySnapshot(
                    $entry,
                    ($captainIds['B'] ?? null) === $entry->id,
                )
            )->all(),
            'player_points' => [],
            'player_shots' => [],
            'started_at' => now(),
            'created_by_user_id' => $user->id,
        ]);
    }

    private function createOpenGameFromCurrentCourt(LeagueSession $session, User $user, ?string $draftMode, string $phase): ?LeagueSessionGame
    {
        $teamA = $this->onCourtEntries($session)->where('team_side', 'A')->sortBy('arrival_order')->values();
        $teamB = $this->onCourtEntries($session)->where('team_side', 'B')->sortBy('arrival_order')->values();

        if ($teamA->isEmpty() || $teamB->isEmpty()) {
            return null;
        }

        $captainIds = $this->captainIdsFromLatestSnapshot($session, $teamA, $teamB);

        return $this->createOpenGame(
            $session,
            $user,
            $draftMode,
            $phase,
            $this->orderExistingTeamForSnapshot($teamA, $captainIds['A']),
            $this->orderExistingTeamForSnapshot($teamB, $captainIds['B']),
            $captainIds,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function entrySnapshot(LeagueSessionEntry $entry, bool $isCaptain = false): array
    {
        return [
            'entry_id' => $entry->id,
            'player_id' => $entry->player?->id,
            'name' => $entry->entry_type === 'guest' ? $entry->guest_name : $entry->player?->display_name,
            'is_guest' => $entry->entry_type === 'guest',
            'is_captain' => $isCaptain,
            'jersey_number' => $entry->player?->jersey_number,
            'arrival_order' => $entry->arrival_order,
            'preferred_position' => $entry->player?->scoutProfile?->position,
        ];
    }

    private function syncGameSnapshots(LeagueSessionGame $game, LeagueSession $session): void
    {
        $previousTeamA = collect($game->team_a_snapshot ?? []);
        $previousTeamB = collect($game->team_b_snapshot ?? []);
        $captainIds = [
            'A' => $previousTeamA
                ->first(fn (array $participant): bool => (bool) ($participant['is_captain'] ?? false))['entry_id'] ?? null,
            'B' => $previousTeamB
                ->first(fn (array $participant): bool => (bool) ($participant['is_captain'] ?? false))['entry_id'] ?? null,
        ];
        $orderMaps = [
            'A' => $previousTeamA
                ->values()
                ->mapWithKeys(fn (array $participant, int $index): array => [(int) ($participant['entry_id'] ?? 0) => $index]),
            'B' => $previousTeamB
                ->values()
                ->mapWithKeys(fn (array $participant, int $index): array => [(int) ($participant['entry_id'] ?? 0) => $index]),
        ];
        $teamA = $this->orderedOnCourtTeamForSnapshot($session, 'A', $orderMaps['A'], $captainIds['A']);
        $teamB = $this->orderedOnCourtTeamForSnapshot($session, 'B', $orderMaps['B'], $captainIds['B']);

        $game->forceFill([
            'team_a_snapshot' => $teamA->map(
                fn (LeagueSessionEntry $entry): array => $this->entrySnapshot(
                    $entry,
                    $captainIds['A'] === $entry->id,
                )
            )->all(),
            'team_b_snapshot' => $teamB->map(
                fn (LeagueSessionEntry $entry): array => $this->entrySnapshot(
                    $entry,
                    $captainIds['B'] === $entry->id,
                )
            )->all(),
        ])->save();
    }

    /**
     * @return array{A: int|null, B: int|null}
     */
    private function captainIdsFromLatestSnapshot(LeagueSession $session, Collection $teamA, Collection $teamB): array
    {
        $latestGame = $session->games
            ->sortByDesc('game_number')
            ->first();

        if ($latestGame === null) {
            return [
                'A' => $teamA->sortBy('arrival_order')->first()?->id,
                'B' => $teamB->sortBy('arrival_order')->first()?->id,
            ];
        }

        $previousCaptains = [
            'A' => collect($latestGame->team_a_snapshot ?? [])
                ->first(fn (array $participant): bool => (bool) ($participant['is_captain'] ?? false))['entry_id'] ?? null,
            'B' => collect($latestGame->team_b_snapshot ?? [])
                ->first(fn (array $participant): bool => (bool) ($participant['is_captain'] ?? false))['entry_id'] ?? null,
        ];

        return [
            'A' => $teamA->contains(fn (LeagueSessionEntry $entry): bool => $entry->id === $previousCaptains['A'])
                ? (int) $previousCaptains['A']
                : $teamA->sortBy('arrival_order')->first()?->id,
            'B' => $teamB->contains(fn (LeagueSessionEntry $entry): bool => $entry->id === $previousCaptains['B'])
                ? (int) $previousCaptains['B']
                : $teamB->sortBy('arrival_order')->first()?->id,
        ];
    }

    /**
     * @param  Collection<int, LeagueSessionEntry>  $team
     * @return Collection<int, LeagueSessionEntry>
     */
    private function orderExistingTeamForSnapshot(Collection $team, ?int $captainId): Collection
    {
        if ($captainId === null || ! $team->contains(fn (LeagueSessionEntry $entry): bool => $entry->id === $captainId)) {
            return $team
                ->sortBy('arrival_order')
                ->values();
        }

        /** @var LeagueSessionEntry $captain */
        $captain = $team->firstOrFail(fn (LeagueSessionEntry $entry): bool => $entry->id === $captainId);

        return collect([$captain])
            ->concat(
                $team
                    ->reject(fn (LeagueSessionEntry $entry): bool => $entry->id === $captainId)
                    ->sortBy('arrival_order')
                    ->values(),
            )
            ->values();
    }

    /**
     * @param  Collection<int, int>  $previousOrder
     * @return Collection<int, LeagueSessionEntry>
     */
    private function orderedOnCourtTeamForSnapshot(LeagueSession $session, string $teamSide, Collection $previousOrder, ?int $captainId): Collection
    {
        $team = $this->onCourtEntries($session)
            ->where('team_side', $teamSide)
            ->sort(function (LeagueSessionEntry $left, LeagueSessionEntry $right) use ($previousOrder): int {
                $leftOrder = $previousOrder->get($left->id);
                $rightOrder = $previousOrder->get($right->id);

                if ($leftOrder !== null && $rightOrder !== null && $leftOrder !== $rightOrder) {
                    return $leftOrder <=> $rightOrder;
                }

                if ($leftOrder !== null) {
                    return -1;
                }

                if ($rightOrder !== null) {
                    return 1;
                }

                return ($left->arrival_order ?? PHP_INT_MAX) <=> ($right->arrival_order ?? PHP_INT_MAX);
            })
            ->values();

        return $this->orderExistingTeamForSnapshot($team, $captainId);
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
        $currentGuestCount = $this->onCourtEntries($session)
            ->where('team_side', $teamSide)
            ->where('entry_type', 'guest')
            ->count();

        /** @var LeagueSessionEntry|null $entry */
        $entry = $this->selectIncomingEntries(
            $this->queueEntries($session),
            1,
            max(0, $this->incomingTeamGuestLimit($session) - $currentGuestCount),
        )->first();

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
                    'notice' => $this->buildRotationNotice(
                        'champion_return',
                        $winnerSide,
                        $loserSide,
                    ),
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

            $incoming = $this->pullNextTenToPool($session);
            $session->forceFill([
                'current_game_number' => $session->current_game_number + 1,
                'rotation_state' => [
                    'streak_team' => null,
                    'streak_count' => 0,
                    'double_rotation_mode' => true,
                    'waiting_champion_team' => $winnerSide,
                    'notice' => $this->buildRotationNotice(
                        'double_rotation',
                        $winnerSide,
                        $loserSide,
                        $incoming->count(),
                    ),
                ],
            ])->save();

            return;
        }

        $incomingGuestLimit = $this->incomingTeamGuestLimit($session);
        $incoming = $this->pullNextFiveToCourt($session, $loserSide, $incomingGuestLimit);
        $session->forceFill([
            'current_game_number' => $session->current_game_number + 1,
            'rotation_state' => [
                'streak_team' => $winnerSide,
                'streak_count' => $streakCount,
                'double_rotation_mode' => false,
                'waiting_champion_team' => null,
                'notice' => $this->buildRotationNotice(
                    $streakCount >= 2 ? 'champion_stays' : 'standard',
                    $winnerSide,
                    $loserSide,
                    $incoming->count(),
                    $streakCount,
                    $incomingGuestLimit,
                ),
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
        $orderedEntries = $entries
            ->sortBy(fn (LeagueSessionEntry $entry): int => $entry->entry_type === 'guest' ? 1 : 0)
            ->values();

        foreach ($orderedEntries as $entry) {
            $nextPosition++;
            $entry->forceFill([
                'session_state' => 'queued',
                'team_side' => null,
                'queue_position' => $nextPosition,
            ])->save();
        }
    }

    private function pullNextTenToPool(LeagueSession $session): Collection
    {
        $selected = $this->selectIncomingEntries(
            $this->queueEntries($session),
            10,
            $this->incomingTeamGuestLimit($session) * 2,
        );

        $selected->each(function (LeagueSessionEntry $entry): void {
            $entry->forceFill([
                'session_state' => 'pool',
                'team_side' => null,
                'queue_position' => null,
            ])->save();
        });

        $this->resequenceQueue($session->fresh('entries'));

        return $selected;
    }

    /**
     * @return Collection<int, LeagueSessionEntry>
     */
    private function pullNextFiveToCourt(LeagueSession $session, string $teamSide, int $incomingGuestLimit): Collection
    {
        $selected = $this->selectIncomingEntries($this->queueEntries($session), 5, $incomingGuestLimit);

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
    private function buildRotationNotice(
        string $mode,
        string $winnerSide,
        string $loserSide,
        int $incomingCount = 0,
        int $streakCount = 1,
        ?int $incomingGuestLimit = null,
    ): array {
        return match ($mode) {
            'double_rotation' => [
                'key' => sprintf('rotation-%s-%d', $mode, now()->timestamp),
                'title' => 'Racha de 2 - Rotación total',
                'body' => [
                    sprintf('Eq. %s ganó 2 seguidos y entra en descanso.', $winnerSide),
                    sprintf('Eq. %s va al final de la cola.', $loserSide),
                    sprintf('Los próximos %d jugadores juegan entre ellos.', $incomingCount),
                    sprintf('El ganador de ese cruce se enfrenta luego al Eq. %s.', $winnerSide),
                ],
                'tone' => 'warning',
                'icon' => 'flame',
            ],
            'champion_return' => [
                'key' => sprintf('rotation-%s-%d', $mode, now()->timestamp),
                'title' => 'Ganador vs. el campeón',
                'body' => [
                    'El ganador del grupo de espera se queda en cancha.',
                    'El equipo campeón regresa para disputar el siguiente juego.',
                    'La racha vuelve a contarse desde 1 para este nuevo cruce.',
                ],
                'tone' => 'success',
                'icon' => 'trophy',
            ],
            'champion_stays' => [
                'key' => sprintf('rotation-%s-%d', $mode, now()->timestamp),
                'title' => sprintf('Racha de %d - Campeón en cancha', $streakCount),
                'body' => [
                    sprintf('Eq. %s lleva %d victorias seguidas.', $winnerSide, $streakCount),
                    'Con menos de 20 jugadores el campeón no descansa hasta que alguien le gane.',
                    sprintf('Eq. %s va al final de la cola.', $loserSide),
                    sprintf(
                        'Entran %d jugadores nuevos respetando el límite de %d invitados por equipo nuevo.',
                        $incomingCount,
                        $incomingGuestLimit ?? 2,
                    ),
                ],
                'tone' => 'warning',
                'icon' => 'trophy',
            ],
            default => [
                'key' => sprintf('rotation-%s-%d', $mode, now()->timestamp),
                'title' => sprintf('Eq. %s gana', $winnerSide),
                'body' => [
                    sprintf('Eq. %s se queda completo en cancha.', $winnerSide),
                    sprintf('Eq. %s va al final de la cola.', $loserSide),
                    sprintf('Entran %d jugadores en orden de prioridad.', $incomingCount),
                    'Los invitados derrotados vuelven al final de la cola.',
                ],
                'tone' => 'success',
                'icon' => 'rotate',
            ],
        };
    }

    /**
     * @param  Collection<int, LeagueSessionEntry>  $queueEntries
     * @return Collection<int, LeagueSessionEntry>
     */
    private function selectIncomingEntries(Collection $queueEntries, int $slots, int $baseGuestLimit): Collection
    {
        if ($slots <= 0) {
            return collect();
        }

        $memberCount = $queueEntries
            ->where('entry_type', '!=', 'guest')
            ->count();
        $requiredGuests = max(0, $slots - $memberCount);
        $allowedGuests = min($slots, max(0, $baseGuestLimit, $requiredGuests));
        $selected = collect();
        $selectedGuests = 0;

        foreach ($queueEntries as $entry) {
            if ($selected->count() >= $slots) {
                break;
            }

            if ($entry->entry_type === 'guest') {
                if ($selectedGuests >= $allowedGuests) {
                    continue;
                }

                $selectedGuests++;
            }

            $selected->push($entry);
        }

        return $selected;
    }

    private function incomingTeamGuestLimit(LeagueSession $session): int
    {
        $session->loadMissing('league');

        return min(5, max(0, (int) ($session->league?->incoming_team_guest_limit ?? 2)));
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
            'notice' => null,
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
     * @param  array<string, mixed>  $scoutStatBaseline
     * @return array{
     *     rating: float,
     *     manual_rating: float,
     *     stat_rating: null|array{
     *         victories: float,
     *         scoring: float,
     *         defense: float,
     *         triples: float,
     *         diversity: float,
     *         overall: float,
     *         detail: array{
     *             points_per_game: float,
     *             win_rate: int,
     *             points_allowed_per_game: ?float,
     *             triple_rate: int,
     *             diversity: int
     *         }
     *     },
     *     has_stats: bool
     * }
     */
    private function combinedScoutRating(?LeaguePlayerScoutProfile $profile, ?array $seasonRow, array $scoutStatBaseline): array
    {
        $manualRating = $this->manualScoutRating($profile);
        $statRating = $this->seasonScoutRating($seasonRow, $scoutStatBaseline);

        if ($statRating === null) {
            return ['rating' => $manualRating, 'manual_rating' => $manualRating, 'stat_rating' => null, 'has_stats' => false];
        }

        if ($manualRating <= 0) {
            return ['rating' => $statRating['overall'], 'manual_rating' => 0, 'stat_rating' => $statRating, 'has_stats' => true];
        }

        $games = (int) ($seasonRow['games'] ?? 0);
        $statWeight = $games < 5 ? 0.2 : ($games < 15 ? 0.4 : 0.6);

        return [
            'rating' => round(($manualRating * (1 - $statWeight)) + ($statRating['overall'] * $statWeight), 1),
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

    /**
     * @param  array<string, mixed>  $scoutStatBaseline
     * @return null|array{
     *     victories: float,
     *     scoring: float,
     *     defense: float,
     *     triples: float,
     *     diversity: float,
     *     overall: float,
     *     detail: array{
     *         points_per_game: float,
     *         win_rate: int,
     *         points_allowed_per_game: ?float,
     *         triple_rate: int,
     *         diversity: int
     *     }
     * }
     */
    private function seasonScoutRating(?array $seasonRow, array $scoutStatBaseline): ?array
    {
        if ($seasonRow === null || ($seasonRow['games'] ?? 0) < 3) {
            return null;
        }

        $shots = $seasonRow['shots'] ?? ['1' => 0, '2' => 0, '3' => 0];
        $games = (int) ($seasonRow['games'] ?? 0);
        $wins = (int) ($seasonRow['wins'] ?? 0);
        $gamesDefended = (int) ($seasonRow['games_defended'] ?? 0);
        $pointsPerGame = $games > 0 ? round(((float) ($seasonRow['points'] ?? 0)) / $games, 1) : 0.0;
        $winRate = $games > 0 ? $wins / $games : 0.0;
        $pointsAllowedPerGame = $gamesDefended > 0
            ? round(((float) ($seasonRow['points_allowed'] ?? 0)) / $gamesDefended, 1)
            : null;
        $tripleRate = $this->scoutTripleRate($shots);
        $diversity = $this->scoutDiversityScore($shots);

        $victoriesRating = round($winRate * 5, 1);
        $scoringRating = $this->normalizeScoutValue($pointsPerGame, (float) ($scoutStatBaseline['max_points_per_game'] ?? 0));
        $triplesRating = $this->normalizeScoutValue($tripleRate, (float) ($scoutStatBaseline['max_triple_rate'] ?? 0));
        $defenseRating = 0.0;

        if (
            $pointsAllowedPerGame !== null
            && $scoutStatBaseline['defense_min_points_allowed_per_game'] !== null
            && $scoutStatBaseline['defense_max_points_allowed_per_game'] !== null
        ) {
            $range = (float) $scoutStatBaseline['defense_max_points_allowed_per_game']
                - (float) $scoutStatBaseline['defense_min_points_allowed_per_game'];
            $defenseRating = $range > 0
                ? round((((float) $scoutStatBaseline['defense_max_points_allowed_per_game'] - $pointsAllowedPerGame) / $range) * 5, 1)
                : 2.5;
        }

        $overall = round(
            ($victoriesRating * 0.25)
            + ($scoringRating * 0.20)
            + ($defenseRating * 0.20)
            + ($triplesRating * 0.20)
            + ($diversity * 0.15),
            1,
        );

        return [
            'victories' => $victoriesRating,
            'scoring' => $scoringRating,
            'defense' => $defenseRating,
            'triples' => $triplesRating,
            'diversity' => $diversity,
            'overall' => $overall,
            'detail' => [
                'points_per_game' => $pointsPerGame,
                'win_rate' => (int) round($winRate * 100),
                'points_allowed_per_game' => $pointsAllowedPerGame,
                'triple_rate' => (int) round($tripleRate * 100),
                'diversity' => (int) round(($diversity / 5) * 100),
            ],
        ];
    }

    /**
     * @param  Collection<int|string, array<string, mixed>>  $seasonStats
     * @return array<string, float|null>
     */
    private function scoutStatBaseline(Collection $seasonStats): array
    {
        $eligibleRows = $seasonStats
            ->filter(fn (array $row): bool => (int) ($row['games'] ?? 0) >= 3)
            ->values();

        $defenseRows = $eligibleRows
            ->filter(fn (array $row): bool => (int) ($row['games_defended'] ?? 0) > 0)
            ->values();

        return [
            'max_points_per_game' => $eligibleRows
                ->map(fn (array $row): float => (float) ($row['points_per_game'] ?? 0))
                ->max() ?? 0.0,
            'max_triple_rate' => $eligibleRows
                ->map(fn (array $row): float => $this->scoutTripleRate($row['shots'] ?? ['1' => 0, '2' => 0, '3' => 0]))
                ->max() ?? 0.0,
            'defense_min_points_allowed_per_game' => $defenseRows
                ->map(fn (array $row): float => (float) (($row['points_allowed_per_game'] ?? 0)))
                ->min(),
            'defense_max_points_allowed_per_game' => $defenseRows
                ->map(fn (array $row): float => (float) (($row['points_allowed_per_game'] ?? 0)))
                ->max(),
        ];
    }

    /**
     * @param  array<string, mixed>  $shots
     */
    private function scoutTripleRate(array $shots): float
    {
        $totalShots = (int) ($shots['1'] ?? 0) + (int) ($shots['2'] ?? 0) + (int) ($shots['3'] ?? 0);

        if ($totalShots <= 0) {
            return 0.0;
        }

        return ((int) ($shots['3'] ?? 0)) / $totalShots;
    }

    /**
     * @param  array<string, mixed>  $shots
     */
    private function scoutDiversityScore(array $shots): float
    {
        $onePointShots = (int) ($shots['1'] ?? 0);
        $twoPointShots = (int) ($shots['2'] ?? 0);
        $threePointShots = (int) ($shots['3'] ?? 0);
        $totalShots = $onePointShots + $twoPointShots + $threePointShots;

        if ($totalShots <= 0) {
            return 0.0;
        }

        $entropy = collect([$onePointShots, $twoPointShots, $threePointShots])
            ->map(fn (int $count): float => $count / $totalShots)
            ->reduce(function (float $carry, float $probability): float {
                if ($probability <= 0) {
                    return $carry;
                }

                return $carry - ($probability * log($probability));
            }, 0.0);

        return round(($entropy / log(3.0)) * 5, 1);
    }

    private function normalizeScoutValue(float $value, float $max): float
    {
        if ($max <= 0) {
            return 0.0;
        }

        return min(5.0, round(($value / $max) * 5, 1));
    }

    /**
     * @param  array<string, mixed>  $scoutStatBaseline
     * @return array{entry: LeagueSessionEntry, rating: float, role: ?string}
     */
    private function scoutDraftCandidate(LeagueSessionEntry $entry, Collection $seasonStats, array $scoutStatBaseline): array
    {
        $seasonKey = $this->seasonKeyForIdentity([
            'player_id' => $entry->player?->id,
            'name' => $entry->entry_type === 'guest' ? $entry->guest_name : $entry->player?->display_name,
            'is_guest' => $entry->entry_type === 'guest',
        ]);
        $seasonRow = $seasonStats->get($seasonKey);
        $combined = $this->combinedScoutRating($entry->player?->scoutProfile, $seasonRow, $scoutStatBaseline);

        return [
            'entry' => $entry,
            'rating' => $combined['rating'],
            'role' => $entry->player?->scoutProfile?->role,
        ];
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

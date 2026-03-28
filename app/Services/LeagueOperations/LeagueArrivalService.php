<?php

namespace App\Services\LeagueOperations;

use App\Models\LeagueCut;
use App\Models\LeaguePlayer;
use App\Models\LeagueSession;
use App\Models\LeagueSessionEntry;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeagueArrivalService
{
    public function __construct(
        private readonly LeagueOperationsService $operations,
        private readonly LeagueManagementService $management,
        private readonly LeagueSeasonService $seasons,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function pageData(User $user): array
    {
        $context = $this->operations->requireOperationalContext($user);
        $league = $context['league'];
        $cut = $this->operations->activeCutForLeague($league);
        $session = $this->pruneNonOperationalEntries(
            $this->operations->currentSessionForLeague($league, $cut),
            $league,
        );
        $attendanceCounts = $this->operations->attendanceCounts($league);
        $players = $this->operations->activePlayablePlayersQuery($league)
            ->orderBy('display_name')
            ->get();
        $operationalPlayerIds = $players
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $sessionEntries = $session?->entries
            ? $session->entries
                ->filter(fn (LeagueSessionEntry $entry): bool => $entry->entry_type === 'guest'
                    || in_array((int) $entry->league_player_id, $operationalPlayerIds, true))
                ->where('session_state', '!=', 'removed')
                ->sortBy('arrival_order')
                ->values()
            : collect();
        $entryByPlayer = $sessionEntries
            ->where('entry_type', 'player')
            ->keyBy('league_player_id');
        $guestEntries = $sessionEntries
            ->where('entry_type', 'guest')
            ->values();
        $isPastDue = $this->operations->hasPastDue($cut);
        $draftReadyEntries = $sessionEntries->where('entry_type', 'player')->count()
            + $guestEntries->where('guest_fee_paid', true)->count();

        return [
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
            'cut' => [
                'id' => $cut->id,
                'label' => $cut->label,
                'starts_on' => $cut->starts_on?->toDateString(),
                'ends_on' => $cut->ends_on?->toDateString(),
                'due_on' => $cut->due_on?->toDateString(),
                'is_past_due' => $isPastDue,
                'member_fee_amount_cents' => $cut->member_fee_amount_cents,
                'guest_fee_amount_cents' => $cut->guest_fee_amount_cents,
            ],
            'session' => [
                'id' => $session?->id,
                'status' => $session?->status ?? 'arrival_open',
                'session_date' => $session?->session_date?->toDateString(),
                'started_at' => $session?->started_at?->toIso8601String(),
                'prepared_at' => $session?->prepared_at?->toIso8601String(),
                'counts' => [
                    'arrived_members' => $sessionEntries->where('entry_type', 'player')->count(),
                    'total_members' => $players->count(),
                    'guests' => $guestEntries->count(),
                    'paid_guests' => $guestEntries->where('guest_fee_paid', true)->count(),
                    'draft_ready_entries' => $draftReadyEntries,
                    'draft_ready' => $draftReadyEntries >= 10,
                ],
                'prepared_pool' => $session?->initial_pool ?? [],
                'prepared_queue' => $session?->initial_queue ?? [],
            ],
            'players' => $players
                ->map(function (LeaguePlayer $player) use ($cut, $entryByPlayer, $attendanceCounts, $isPastDue): array {
                    $balance = $this->operations->balanceForPlayer($cut, $player);
                    /** @var LeagueSessionEntry|null $entry */
                    $entry = $entryByPlayer->get($player->id);
                    $hasPaid = $balance->status === 'paid';
                    $statusTone = $hasPaid
                        ? 'paid'
                        : ($isPastDue ? 'overdue' : 'pending');
                    $statusMessage = $hasPaid
                        ? 'Al dia y con prioridad activa.'
                        : ($isPastDue
                            ? 'Plazo vencido. Si juega hoy entra detras de los que estan al dia.'
                            : 'Pendiente de pago. Aun mantiene prioridad dentro del corte activo.');

                    return [
                        'id' => $player->id,
                        'name' => $player->display_name,
                        'jersey_number' => $player->jersey_number,
                        'attendance_count' => $attendanceCounts[$player->id] ?? 0,
                        'arrival_order' => $entry?->arrival_order,
                        'has_arrived' => $entry !== null,
                        'current_cut_paid' => $entry?->current_cut_paid ?? $hasPaid,
                        'status_tone' => $statusTone,
                        'status_message' => $statusMessage,
                    ];
                })
                ->sort(function (array $left, array $right): int {
                    if ($left['has_arrived'] && $right['has_arrived']) {
                        return ($left['arrival_order'] ?? PHP_INT_MAX) <=> ($right['arrival_order'] ?? PHP_INT_MAX);
                    }

                    if ($left['has_arrived'] !== $right['has_arrived']) {
                        return $left['has_arrived'] ? -1 : 1;
                    }

                    return strcasecmp($left['name'], $right['name']);
                })
                ->values()
                ->all(),
            'guests' => $guestEntries
                ->map(fn (LeagueSessionEntry $entry): array => [
                    'id' => $entry->id,
                    'name' => $entry->guest_name,
                    'arrival_order' => $entry->arrival_order,
                    'guest_fee_paid' => $entry->guest_fee_paid,
                ])
                ->all(),
            'roster_management' => $this->management->rosterData($user),
        ];
    }

    public function togglePlayerArrival(User $user, LeaguePlayer $player, ?bool $paid = null): void
    {
        $context = $this->operations->requireAdminContext($user);
        $league = $context['league'];

        if ($player->league_id !== $league->id) {
            throw ValidationException::withMessages([
                'player_id' => 'Ese miembro no pertenece a la liga activa.',
            ]);
        }

        $cut = $this->operations->activeCutForLeague($league);
        $session = $this->seasons->attachSessionToActiveSeason(
            $this->operations->currentSessionForLeague($league, $cut),
            $league,
            $user,
        );
        $session = $this->pruneNonOperationalEntries($session, $league);

        if ($session->status === 'completed') {
            $session = $this->reopenCompletedSession($session);
        }

        $existingEntry = $session->entries()
            ->where('entry_type', 'player')
            ->where('league_player_id', $player->id)
            ->first();

        if ($existingEntry !== null) {
            if (in_array($session->status, ['prepared', 'in_progress'], true) && $existingEntry->session_state !== 'removed') {
                throw ValidationException::withMessages([
                    'session' => 'Con la jornada activa solo puedes registrar nuevas llegadas. Las salidas operativas se controlan desde Juego.',
                ]);
            }

            $existingEntry->delete();

            if ($existingEntry->session_state !== 'removed') {
                $this->resequenceEntries($session->fresh('entries'), $cut);

                return;
            }

            $session = $session->fresh('entries');
        }

        $balance = $this->operations->balanceForPlayer($cut, $player);
        $alreadyPaid = $balance->status === 'paid';

        if (! $alreadyPaid && $paid === null) {
            throw ValidationException::withMessages([
                'paid' => 'Debes confirmar si el miembro esta al dia para registrarlo en llegada.',
            ]);
        }

        if (! $alreadyPaid && $paid === true) {
            $outstandingAmount = $this->operations->outstandingAmount($balance);

            if ($outstandingAmount > 0) {
                $this->management->recordPayment($user, $player, $outstandingAmount, false);
            }

            $balance = $this->operations->balanceForPlayer($cut, $player);
        }

        $isPaidForQueue = $balance->status === 'paid' || $paid === true;
        $isLiveSession = in_array($session->status, ['prepared', 'in_progress'], true);

        $session->entries()->create([
            'league_player_id' => $player->id,
            'entry_type' => 'player',
            'arrival_order' => ((int) $session->entries()->max('arrival_order')) + 1,
            'current_cut_paid' => $isPaidForQueue,
            'guest_fee_paid' => false,
            'was_marked_paid_on_arrival' => ! $alreadyPaid && $paid === true,
            'priority_bucket' => $this->playerPriorityBucket($cut, $isPaidForQueue),
            'session_state' => $isLiveSession ? 'queued' : 'arrival',
            'team_side' => null,
            'queue_position' => null,
        ]);

        $this->resequenceEntries($session->fresh('entries'), $cut);

        if ($isLiveSession) {
            $this->resequenceLiveQueue($session->fresh('entries'), $cut);
        }
    }

    public function storeGuest(User $user, string $guestName): void
    {
        $context = $this->operations->requireAdminContext($user);
        $cut = $this->operations->activeCutForLeague($context['league']);
        $session = $this->seasons->attachSessionToActiveSeason(
            $this->operations->currentSessionForLeague($context['league'], $cut),
            $context['league'],
            $user,
        );
        $session = $this->pruneNonOperationalEntries($session, $context['league']);

        if ($session->status === 'completed') {
            $session = $this->reopenCompletedSession($session);
        }

        $session->entries()->create([
            'guest_name' => $guestName,
            'entry_type' => 'guest',
            'arrival_order' => ((int) $session->entries()->max('arrival_order')) + 1,
            'guest_fee_paid' => false,
            'current_cut_paid' => false,
            'priority_bucket' => 'guest_unpaid',
            'session_state' => 'arrival',
        ]);

        $this->resequenceEntries($session->fresh('entries'), $cut);
    }

    public function updateGuestPayment(User $user, LeagueSessionEntry $guestEntry, bool $paid): void
    {
        $context = $this->operations->requireAdminContext($user);
        $cut = $this->operations->activeCutForLeague($context['league']);
        $session = $this->operations->currentSessionForLeague($context['league'], $cut);

        if ($session === null) {
            throw ValidationException::withMessages([
                'session' => 'No existe una jornada activa para actualizar invitados.',
            ]);
        }

        $session = $this->pruneNonOperationalEntries($session, $context['league']);

        if ($session->status === 'completed') {
            $session = $this->reopenCompletedSession($session);
        }

        if ($guestEntry->league_session_id !== $session->id || $guestEntry->entry_type !== 'guest') {
            throw ValidationException::withMessages([
                'guest_id' => 'El invitado seleccionado no pertenece a la jornada activa.',
            ]);
        }

        if (! $paid && in_array($guestEntry->session_state, ['pool', 'on_court'], true)) {
            throw ValidationException::withMessages([
                'guest_fee_paid' => 'No puedes quitar el pago a un invitado que ya esta dentro del juego activo.',
            ]);
        }

        $isLiveSession = in_array($session->status, ['prepared', 'in_progress'], true);

        $guestEntry->forceFill([
            'guest_fee_paid' => $paid,
            'priority_bucket' => $paid ? 'guest_paid' : 'guest_unpaid',
            'session_state' => $paid && $isLiveSession ? 'queued' : (in_array($guestEntry->session_state, ['pool', 'on_court'], true) ? $guestEntry->session_state : 'arrival'),
            'team_side' => in_array($guestEntry->session_state, ['pool', 'on_court'], true) ? $guestEntry->team_side : null,
            'queue_position' => $paid && $isLiveSession ? $guestEntry->queue_position : null,
        ])->save();

        $this->resequenceEntries($session->fresh('entries'), $cut);

        if ($isLiveSession) {
            $this->resequenceLiveQueue($session->fresh('entries'), $cut);
        }
    }

    public function deleteGuest(User $user, LeagueSessionEntry $guestEntry): void
    {
        $context = $this->operations->requireAdminContext($user);
        $cut = $this->operations->activeCutForLeague($context['league']);
        $session = $this->operations->currentSessionForLeague($context['league'], $cut);

        if ($session === null) {
            return;
        }

        $session = $this->pruneNonOperationalEntries($session, $context['league']);

        if ($session->status === 'completed') {
            $session = $this->reopenCompletedSession($session);
        }

        if ($guestEntry->league_session_id !== $session->id || $guestEntry->entry_type !== 'guest') {
            throw ValidationException::withMessages([
                'guest_id' => 'El invitado seleccionado no pertenece a la jornada activa.',
            ]);
        }

        if (in_array($guestEntry->session_state, ['pool', 'on_court'], true)) {
            throw ValidationException::withMessages([
                'guest_id' => 'No puedes remover un invitado que ya forma parte del juego activo.',
            ]);
        }

        $isLiveSession = in_array($session->status, ['prepared', 'in_progress'], true);

        $guestEntry->delete();
        $this->resequenceEntries($session->fresh('entries'), $cut);

        if ($isLiveSession) {
            $this->resequenceLiveQueue($session->fresh('entries'), $cut);
        }
    }

    /**
     * @param  array<int, array{id: int, paid: bool}>  $guestPayments
     */
    public function prepareSession(User $user, array $guestPayments = []): void
    {
        $context = $this->operations->requireAdminContext($user);
        $cut = $this->operations->activeCutForLeague($context['league']);
        $session = $this->operations->currentSessionForLeague($context['league'], $cut);

        if ($session === null) {
            throw ValidationException::withMessages([
                'session' => 'No existe una jornada abierta para preparar.',
            ]);
        }

        $session = $this->pruneNonOperationalEntries($session, $context['league']);

        if ($session->status === 'completed') {
            $session = $this->reopenCompletedSession($session);
        }

        if ($session->status === 'in_progress') {
            throw ValidationException::withMessages([
                'session' => 'La jornada ya esta en juego. Las llegadas nuevas se agregan a la cola operativa desde Llegada.',
            ]);
        }

        if ($session->status === 'prepared') {
            return;
        }

        foreach ($guestPayments as $guestPayment) {
            $entry = $session->entries()
                ->where('entry_type', 'guest')
                ->find($guestPayment['id']);

            if ($entry !== null) {
                $entry->forceFill([
                    'guest_fee_paid' => (bool) $guestPayment['paid'],
                    'priority_bucket' => (bool) $guestPayment['paid'] ? 'guest_paid' : 'guest_unpaid',
                ])->save();
            }
        }

        $this->resequenceEntries($session->fresh('entries'), $cut);

        $entries = $session->entries()
            ->with('player')
            ->where('session_state', '!=', 'removed')
            ->orderBy('arrival_order')
            ->get();
        $eligiblePlayers = $entries
            ->where('entry_type', 'player')
            ->values()
            ->concat(
                $entries
                    ->where('entry_type', 'guest')
                    ->where('guest_fee_paid', true)
                    ->values(),
            )
            ->values();

        if ($eligiblePlayers->count() < 10) {
            throw ValidationException::withMessages([
                'session' => 'Se necesitan al menos 10 jugadores habiles para iniciar la jornada.',
            ]);
        }

        $playerEntries = $entries->where('entry_type', 'player')->values();
        $paidGuests = $entries
            ->where('entry_type', 'guest')
            ->where('guest_fee_paid', true)
            ->values();
        $isPastDue = $this->operations->hasPastDue($cut);

        if ($isPastDue) {
            $priorityMembers = $playerEntries
                ->where('current_cut_paid', true)
                ->values();
            $lowerPriority = $playerEntries
                ->where('current_cut_paid', false)
                ->values()
                ->concat($paidGuests)
                ->values();
        } else {
            $priorityMembers = $playerEntries;
            $lowerPriority = $paidGuests;
        }

        $firstTen = $priorityMembers->take(10)->values();

        if ($firstTen->count() < 10) {
            $firstTen = $firstTen
                ->concat($lowerPriority->take(10 - $firstTen->count()))
                ->values();
        }

        $selectedIds = $firstTen->pluck('id')->all();
        $queue = $priorityMembers
            ->reject(fn (LeagueSessionEntry $entry): bool => in_array($entry->id, $selectedIds, true))
            ->values()
            ->concat(
                $lowerPriority
                    ->reject(fn (LeagueSessionEntry $entry): bool => in_array($entry->id, $selectedIds, true))
                    ->values(),
            )
            ->values();

        DB::transaction(function () use ($session, $firstTen, $queue): void {
            $session->entries()
                ->where('session_state', '!=', 'removed')
                ->update([
                    'queue_seed' => null,
                    'session_state' => 'arrival',
                    'team_side' => null,
                    'queue_position' => null,
                ]);

            foreach ($queue as $index => $entry) {
                $entry->forceFill([
                    'queue_seed' => $index + 1,
                    'session_state' => 'queued',
                    'queue_position' => $index + 1,
                ])->save();
            }

            foreach ($firstTen as $entry) {
                $entry->forceFill([
                    'session_state' => 'pool',
                    'team_side' => null,
                    'queue_position' => null,
                ])->save();
            }

            $session->forceFill([
                'status' => 'prepared',
                'current_game_number' => $this->nextGameNumber($session),
                'started_at' => $session->started_at ?? now(),
                'prepared_at' => now(),
                'ended_at' => null,
                'initial_pool' => $this->serializeEntries($firstTen),
                'initial_queue' => $this->serializeEntries($queue),
                'rotation_state' => [
                    'streak_team' => null,
                    'streak_count' => 0,
                    'double_rotation_mode' => false,
                    'waiting_champion_team' => null,
                ],
                'clock_remaining_seconds' => $session->clock_duration_seconds,
                'clock_state' => 'paused',
                'clock_started_at' => null,
            ])->save();
        });
    }

    public function resetSession(User $user): void
    {
        $context = $this->operations->requireAdminContext($user);
        $cut = $this->operations->activeCutForLeague($context['league']);
        $session = $this->operations->currentSessionForLeague($context['league'], $cut, false);

        if ($session === null) {
            return;
        }

        DB::transaction(function () use ($session): void {
            $session->actionLogs()->delete();
            $session->games()->delete();
            $session->entries()->delete();
            $session->forceFill([
                'status' => 'arrival_open',
                'current_game_number' => 1,
                'started_at' => null,
                'prepared_at' => null,
                'ended_at' => null,
                'initial_pool' => null,
                'initial_queue' => null,
                'rotation_state' => null,
                'clock_remaining_seconds' => $session->clock_duration_seconds,
                'clock_state' => 'paused',
                'clock_started_at' => null,
            ])->save();
        });
    }

    private function resequenceEntries(LeagueSession $session, LeagueCut $cut): void
    {
        $isPastDue = $this->operations->hasPastDue($cut);

        $session->entries
            ->where('session_state', '!=', 'removed')
            ->sort(function (LeagueSessionEntry $left, LeagueSessionEntry $right) use ($isPastDue): int {
                $priorityDiff = $this->entryPriorityRank($left, $isPastDue) <=> $this->entryPriorityRank($right, $isPastDue);

                if ($priorityDiff !== 0) {
                    return $priorityDiff;
                }

                return ($left->arrival_order ?? PHP_INT_MAX) <=> ($right->arrival_order ?? PHP_INT_MAX);
            })
            ->values()
            ->each(function (LeagueSessionEntry $entry, int $index): void {
                $entry->forceFill([
                    'arrival_order' => $index + 1,
                ])->save();
            });
    }

    private function resequenceLiveQueue(LeagueSession $session, LeagueCut $cut): void
    {
        $isPastDue = $this->operations->hasPastDue($cut);

        // Cuando la jornada ya arranco, las llegadas nuevas no desplazan el pool actual:
        // entran directo a la cola y la recolocamos por prioridad operativa real.
        $session->entries
            ->where('session_state', 'queued')
            ->sort(function (LeagueSessionEntry $left, LeagueSessionEntry $right) use ($isPastDue): int {
                $priorityDiff = $this->entryPriorityRank($left, $isPastDue) <=> $this->entryPriorityRank($right, $isPastDue);

                if ($priorityDiff !== 0) {
                    return $priorityDiff;
                }

                return ($left->arrival_order ?? PHP_INT_MAX) <=> ($right->arrival_order ?? PHP_INT_MAX);
            })
            ->values()
            ->each(function (LeagueSessionEntry $entry, int $index): void {
                $entry->forceFill([
                    'queue_position' => $index + 1,
                ])->save();
            });
    }

    private function playerPriorityBucket(LeagueCut $cut, bool $isPaid): string
    {
        return $this->operations->hasPastDue($cut) && ! $isPaid
            ? 'member_unpaid'
            : 'member_priority';
    }

    private function entryPriorityRank(LeagueSessionEntry $entry, bool $isPastDue): int
    {
        if (! $isPastDue) {
            return $entry->entry_type === 'player' ? 0 : 1;
        }

        return match (true) {
            $entry->entry_type === 'player' && $entry->current_cut_paid => 0,
            $entry->entry_type === 'player' => 1,
            $entry->entry_type === 'guest' && $entry->guest_fee_paid => 2,
            default => 3,
        };
    }

    private function nextGameNumber(LeagueSession $session): int
    {
        return max(1, ((int) $session->games()->max('game_number')) + 1);
    }

    private function reopenCompletedSession(LeagueSession $session): LeagueSession
    {
        DB::transaction(function () use ($session): void {
            // Reabrimos la misma jornada del dia para continuar sobre su historial existente,
            // sin crear otra jornada nueva ni perder los juegos ya guardados.
            $session->entries()
                ->where('session_state', '!=', 'removed')
                ->update([
                    'queue_seed' => null,
                    'session_state' => 'arrival',
                    'team_side' => null,
                    'queue_position' => null,
                ]);

            $session->forceFill([
                'status' => 'arrival_open',
                'prepared_at' => null,
                'ended_at' => null,
                'initial_pool' => null,
                'initial_queue' => null,
                'rotation_state' => null,
                'current_game_number' => $this->nextGameNumber($session),
                'clock_remaining_seconds' => $session->clock_duration_seconds,
                'clock_state' => 'paused',
                'clock_started_at' => null,
            ])->save();
        });

        return $session->fresh('entries');
    }

    /**
     * @param  Collection<int, LeagueSessionEntry>  $entries
     * @return array<int, array<string, mixed>>
     */
    private function serializeEntries(Collection $entries): array
    {
        return $entries
            ->map(fn (LeagueSessionEntry $entry): array => [
                'id' => $entry->id,
                'entry_type' => $entry->entry_type,
                'name' => $entry->entry_type === 'guest'
                    ? $entry->guest_name
                    : $entry->player?->display_name,
                'arrival_order' => $entry->arrival_order,
                'queue_seed' => $entry->queue_seed,
                'current_cut_paid' => $entry->current_cut_paid,
                'guest_fee_paid' => $entry->guest_fee_paid,
            ])
            ->values()
            ->all();
    }

    private function pruneNonOperationalEntries(?LeagueSession $session, $league): ?LeagueSession
    {
        if ($session === null) {
            return null;
        }

        $allowedPlayerIds = $this->operations->activePlayablePlayersQuery($league)
            ->pluck('league_players.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $query = $session->entries()
            ->where('entry_type', 'player');

        if ($allowedPlayerIds === []) {
            $query->whereNotNull('league_player_id');
        } else {
            $query->whereNotIn('league_player_id', $allowedPlayerIds);
        }

        $query->delete();

        return $session->fresh('entries.player');
    }
}

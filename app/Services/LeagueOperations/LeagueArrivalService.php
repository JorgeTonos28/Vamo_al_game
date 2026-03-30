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
        $queuePreview = $session === null
            ? collect()
            : $this->pregameQueueOrder($session, $cut);
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
                        'session_entry_id' => $entry?->id,
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
            'queue_preview' => [
                'can_reorder' => $context['role']->canManageLeague()
                    && $session !== null
                    && in_array($session->status, ['arrival_open', 'prepared'], true),
                'entries' => $queuePreview
                    ->values()
                    ->map(fn (LeagueSessionEntry $entry, int $index): array => [
                        'id' => $entry->id,
                        'position' => $index + 1,
                        'name' => $entry->entry_type === 'guest'
                            ? $entry->guest_name
                            : $entry->player?->display_name,
                        'is_guest' => $entry->entry_type === 'guest',
                        'jersey_number' => $entry->player?->jersey_number,
                        'arrival_order' => $entry->arrival_order,
                        'preferred_position' => $entry->player?->scoutProfile?->position,
                    ])
                    ->all(),
            ],
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

        $hasCustomPregameOrder = $this->hasCustomPregameOrder($this->draftReadyEntries($session));
        $isPreparedSession = $session->status === 'prepared';
        $isLiveSession = $session->status === 'in_progress';

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
            'queue_seed' => $hasCustomPregameOrder ? $this->nextPregameQueueSeed($session) : null,
            'session_state' => $isLiveSession ? 'queued' : 'arrival',
            'team_side' => null,
            'queue_position' => $isLiveSession ? $this->nextLiveQueuePosition($session) : null,
        ]);

        $session = $session->fresh('entries.player.scoutProfile');
        $this->resequenceEntries($session, $cut);

        if ($isPreparedSession) {
            $this->syncPreparedSessionState($session->fresh('entries.player.scoutProfile'), $cut);

            return;
        }

        $this->normalizePregameQueueSeeds($session->fresh('entries.player.scoutProfile'));

        if ($isLiveSession) {
            $this->resequenceLiveQueue($session->fresh('entries.player.scoutProfile'), $cut);
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

        $this->resequenceEntries($session->fresh('entries.player.scoutProfile'), $cut);
        $this->normalizePregameQueueSeeds($session->fresh('entries.player.scoutProfile'));
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

        $hasCustomPregameOrder = $this->hasCustomPregameOrder($this->draftReadyEntries($session));
        $isPreparedSession = $session->status === 'prepared';
        $isLiveSession = $session->status === 'in_progress';

        $guestEntry->forceFill([
            'guest_fee_paid' => $paid,
            'priority_bucket' => $paid ? 'guest_paid' : 'guest_unpaid',
            'queue_seed' => $paid && $hasCustomPregameOrder
                ? ($guestEntry->queue_seed ?? $this->nextPregameQueueSeed($session))
                : null,
            'session_state' => $paid && $isLiveSession
                ? 'queued'
                : (in_array($guestEntry->session_state, ['pool', 'on_court'], true) ? $guestEntry->session_state : 'arrival'),
            'team_side' => in_array($guestEntry->session_state, ['pool', 'on_court'], true) ? $guestEntry->team_side : null,
            'queue_position' => $paid && $isLiveSession
                ? ($guestEntry->queue_position ?? $this->nextLiveQueuePosition($session))
                : null,
        ])->save();

        $session = $session->fresh('entries.player.scoutProfile');
        $this->resequenceEntries($session, $cut);

        if ($isPreparedSession) {
            $this->syncPreparedSessionState($session->fresh('entries.player.scoutProfile'), $cut);

            return;
        }

        $this->normalizePregameQueueSeeds($session->fresh('entries.player.scoutProfile'));

        if ($isLiveSession) {
            $this->resequenceLiveQueue($session->fresh('entries.player.scoutProfile'), $cut);
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

        $isPreparedSession = $session->status === 'prepared';
        $isLiveSession = $session->status === 'in_progress';

        $guestEntry->delete();
        $session = $session->fresh('entries.player.scoutProfile');
        $this->resequenceEntries($session, $cut);

        if ($isPreparedSession) {
            $this->syncPreparedSessionState($session->fresh('entries.player.scoutProfile'), $cut);

            return;
        }

        $this->normalizePregameQueueSeeds($session->fresh('entries.player.scoutProfile'));

        if ($isLiveSession) {
            $this->resequenceLiveQueue($session->fresh('entries.player.scoutProfile'), $cut);
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

        $hadCustomPregameOrder = $this->hasCustomPregameOrder($this->draftReadyEntries($session));

        foreach ($guestPayments as $guestPayment) {
            $entry = $session->entries()
                ->where('entry_type', 'guest')
                ->find($guestPayment['id']);

            if ($entry !== null) {
                $entry->forceFill([
                    'guest_fee_paid' => (bool) $guestPayment['paid'],
                    'priority_bucket' => (bool) $guestPayment['paid'] ? 'guest_paid' : 'guest_unpaid',
                    'queue_seed' => (bool) $guestPayment['paid'] && $hadCustomPregameOrder
                        ? ($entry->queue_seed ?? $this->nextPregameQueueSeed($session))
                        : null,
                ])->save();
            }
        }

        $session = $session->fresh('entries.player.scoutProfile');
        $this->resequenceEntries($session, $cut);
        $this->normalizePregameQueueSeeds($session->fresh('entries.player.scoutProfile'));

        if ($this->draftReadyEntries($session->fresh('entries.player.scoutProfile'))->count() < 10) {
            throw ValidationException::withMessages([
                'session' => 'Se necesitan al menos 10 jugadores habiles para iniciar la jornada.',
            ]);
        }

        $this->syncPreparedSessionState(
            $session->fresh('entries.player.scoutProfile'),
            $cut,
            $session->status !== 'prepared',
        );
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

    /**
     * @param  array<int, int>  $orderedEntryIds
     */
    public function reorderPregameQueue(User $user, array $orderedEntryIds): void
    {
        $context = $this->operations->requireAdminContext($user);
        $cut = $this->operations->activeCutForLeague($context['league']);
        $session = $this->operations->currentSessionForLeague($context['league'], $cut);

        if ($session === null) {
            throw ValidationException::withMessages([
                'session' => 'No existe una jornada activa para reordenar.',
            ]);
        }

        $session = $this->pruneNonOperationalEntries($session, $context['league']);

        if ($session->status === 'completed' || $session->status === 'in_progress') {
            throw ValidationException::withMessages([
                'session' => 'La cola inicial solo se puede mover antes del primer juego.',
            ]);
        }

        $readyEntries = $this->draftReadyEntries($session)
            ->values();
        $readyIds = $readyEntries
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $normalizedIds = collect($orderedEntryIds)
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        sort($readyIds);
        $expectedIds = $readyIds;
        $sortedProvidedIds = $normalizedIds;
        sort($sortedProvidedIds);

        if ($normalizedIds === [] || $sortedProvidedIds !== $expectedIds) {
            throw ValidationException::withMessages([
                'entry_ids' => 'Debes enviar exactamente los integrantes listos de la cola inicial.',
            ]);
        }

        $entriesById = $readyEntries->keyBy('id');
        $arrivedMembersCount = $session->entries
            ->where('entry_type', 'player')
            ->where('session_state', '!=', 'removed')
            ->count();

        if ($arrivedMembersCount > 10) {
            $topTenGuestIds = collect(array_slice($normalizedIds, 0, 10))
                ->filter(fn (int $entryId): bool => $entriesById->get($entryId)?->entry_type === 'guest')
                ->values()
                ->all();

            if ($topTenGuestIds !== []) {
                throw ValidationException::withMessages([
                    'entry_ids' => 'Con mas de 10 miembros llegados, los invitados solo pueden ubicarse desde la posicion 11 en adelante.',
                ]);
            }
        }

        DB::transaction(function () use ($normalizedIds, $entriesById, $session, $cut): void {
            foreach ($normalizedIds as $index => $entryId) {
                /** @var LeagueSessionEntry $entry */
                $entry = $entriesById->get($entryId);
                $entry->forceFill([
                    'queue_seed' => $index + 1,
                ])->save();
            }

            $this->normalizePregameQueueSeeds($session->fresh('entries.player.scoutProfile'));

            if ($session->status === 'prepared') {
                $this->syncPreparedSessionState($session->fresh('entries.player.scoutProfile'), $cut);
            }
        });
    }

    private function resequenceEntries(LeagueSession $session, LeagueCut $cut): void
    {
        $session->entries
            ->where('session_state', '!=', 'removed')
            ->sortBy('arrival_order')
            ->values()
            ->each(function (LeagueSessionEntry $entry, int $index): void {
                $entry->forceFill([
                    'arrival_order' => $index + 1,
                ])->save();
            });
    }

    private function resequenceLiveQueue(LeagueSession $session, LeagueCut $cut): void
    {
        $session->entries
            ->where('session_state', 'queued')
            ->sortBy('queue_position')
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

    /**
     * @return Collection<int, LeagueSessionEntry>
     */
    private function draftReadyEntries(LeagueSession $session): Collection
    {
        return $session->entries
            ->where('session_state', '!=', 'removed')
            ->filter(fn (LeagueSessionEntry $entry): bool => $entry->entry_type === 'player' || $entry->guest_fee_paid)
            ->values();
    }

    private function hasCustomPregameOrder(Collection $readyEntries): bool
    {
        return $readyEntries->isNotEmpty()
            && $readyEntries->whereNotNull('queue_seed')->count() === $readyEntries->count();
    }

    private function nextPregameQueueSeed(LeagueSession $session): int
    {
        return ((int) $this->draftReadyEntries($session)->max('queue_seed')) + 1;
    }

    private function nextLiveQueuePosition(LeagueSession $session): int
    {
        return ((int) $session->entries
            ->where('session_state', 'queued')
            ->max('queue_position')) + 1;
    }

    private function normalizePregameQueueSeeds(LeagueSession $session): void
    {
        $readyEntries = $this->draftReadyEntries($session);

        if (! $this->hasCustomPregameOrder($readyEntries)) {
            return;
        }

        $readyEntries
            ->sortBy([
                ['queue_seed', 'asc'],
                ['arrival_order', 'asc'],
            ])
            ->values()
            ->each(function (LeagueSessionEntry $entry, int $index): void {
                $entry->forceFill([
                    'queue_seed' => $index + 1,
                ])->save();
            });
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

    /**
     * @return Collection<int, LeagueSessionEntry>
     */
    private function pregameQueueOrder(LeagueSession $session, LeagueCut $cut): Collection
    {
        $readyEntries = $this->draftReadyEntries($session);

        if ($readyEntries->isEmpty()) {
            return collect();
        }

        if ($this->hasCustomPregameOrder($readyEntries)) {
            return $readyEntries
                ->sortBy([
                    ['queue_seed', 'asc'],
                    ['arrival_order', 'asc'],
                ])
                ->values();
        }

        $baseOrder = $readyEntries
            ->sortBy('arrival_order')
            ->values();
        $isPastDue = $this->operations->hasPastDue($cut);
        $protectedEntries = $baseOrder
            ->sort(function (LeagueSessionEntry $left, LeagueSessionEntry $right) use ($isPastDue): int {
                $priorityDiff = $this->entryPriorityRank($left, $isPastDue) <=> $this->entryPriorityRank($right, $isPastDue);

                if ($priorityDiff !== 0) {
                    return $priorityDiff;
                }

                return ($left->arrival_order ?? PHP_INT_MAX) <=> ($right->arrival_order ?? PHP_INT_MAX);
            })
            ->take(10)
            ->values();
        $protectedIds = $protectedEntries
            ->pluck('id')
            ->all();

        return $protectedEntries
            ->concat(
                $baseOrder
                    ->reject(fn (LeagueSessionEntry $entry): bool => in_array($entry->id, $protectedIds, true))
                    ->values(),
            )
            ->values();
    }

    private function syncPreparedSessionState(LeagueSession $session, LeagueCut $cut, bool $markPrepared = false): void
    {
        $orderedReady = $this->pregameQueueOrder($session, $cut);

        if ($markPrepared && $orderedReady->count() < 10) {
            throw ValidationException::withMessages([
                'session' => 'Se necesitan al menos 10 jugadores habiles para iniciar la jornada.',
            ]);
        }

        $firstTen = $orderedReady->take(10)->values();
        $queue = $orderedReady->slice(10)->values();
        $orderMap = $orderedReady
            ->values()
            ->mapWithKeys(fn (LeagueSessionEntry $entry, int $index): array => [$entry->id => $index + 1]);
        $poolIds = $firstTen->pluck('id')->all();
        $queueIds = $queue->pluck('id')->all();
        $hasCustomOrder = $this->hasCustomPregameOrder($orderedReady);

        DB::transaction(function () use ($session, $firstTen, $queue, $orderMap, $poolIds, $queueIds, $markPrepared, $hasCustomOrder): void {
            foreach ($session->entries as $entry) {
                if ($entry->session_state === 'removed') {
                    continue;
                }

                if (in_array($entry->id, $poolIds, true)) {
                    $entry->forceFill([
                        'queue_seed' => $hasCustomOrder ? $orderMap[$entry->id] : null,
                        'session_state' => 'pool',
                        'team_side' => null,
                        'queue_position' => null,
                    ])->save();

                    continue;
                }

                $queueIndex = array_search($entry->id, $queueIds, true);

                if ($queueIndex !== false) {
                    $entry->forceFill([
                        'queue_seed' => $hasCustomOrder ? $orderMap[$entry->id] : $queueIndex + 1,
                        'session_state' => 'queued',
                        'team_side' => null,
                        'queue_position' => $queueIndex + 1,
                    ])->save();

                    continue;
                }

                $entry->forceFill([
                    'queue_seed' => null,
                    'session_state' => 'arrival',
                    'team_side' => null,
                    'queue_position' => null,
                ])->save();
            }

            $session->forceFill([
                'status' => 'prepared',
                'current_game_number' => $markPrepared
                    ? $this->nextGameNumber($session)
                    : $session->current_game_number,
                'started_at' => $session->started_at ?? now(),
                'prepared_at' => $markPrepared ? now() : ($session->prepared_at ?? now()),
                'ended_at' => null,
                'initial_pool' => $this->serializeEntries($firstTen),
                'initial_queue' => $this->serializeEntries($queue),
                'rotation_state' => $session->rotation_state ?? [
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

        return $session->fresh('entries.player.scoutProfile');
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

        return $session->fresh('entries.player.scoutProfile');
    }
}

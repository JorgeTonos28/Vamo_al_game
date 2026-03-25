<?php

namespace App\Services\LeagueOperations;

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
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function pageData(User $user): array
    {
        $context = $this->operations->requireOperationalContext($user);
        $league = $context['league'];
        $cut = $this->operations->activeCutForLeague($league);
        $session = $this->operations->currentSessionForLeague($league, $cut);
        $attendanceCounts = $this->operations->attendanceCounts($league);
        $players = $league->activePlayers()
            ->orderBy('display_name')
            ->get();
        $sessionEntries = $session?->entries
            ? $session->entries->sortBy('arrival_order')->values()
            : collect();
        $entryByPlayer = $sessionEntries
            ->where('entry_type', 'player')
            ->keyBy('league_player_id');
        $guestEntries = $sessionEntries
            ->where('entry_type', 'guest')
            ->values();
        $isPastDue = $this->operations->hasPastDue($cut);

        return [
            'league' => [
                'id' => $league->id,
                'name' => $league->name,
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
                ],
                'prepared_pool' => $session?->initial_pool ?? [],
                'prepared_queue' => $session?->initial_queue ?? [],
            ],
            'players' => $players->map(function (LeaguePlayer $player) use ($cut, $entryByPlayer, $attendanceCounts, $isPastDue): array {
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
            })->values()->all(),
            'guests' => $guestEntries->map(fn (LeagueSessionEntry $entry): array => [
                'id' => $entry->id,
                'name' => $entry->guest_name,
                'arrival_order' => $entry->arrival_order,
                'guest_fee_paid' => $entry->guest_fee_paid,
            ])->all(),
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
        $session = $this->operations->currentSessionForLeague($league, $cut);

        if ($session?->status === 'prepared') {
            throw ValidationException::withMessages([
                'session' => 'La jornada ya fue preparada. Reinicia la lista de llegada para modificarla.',
            ]);
        }

        $existingEntry = $session?->entries()
            ->where('entry_type', 'player')
            ->where('league_player_id', $player->id)
            ->first();

        if ($existingEntry !== null) {
            $existingEntry->delete();
            $this->resequenceEntries($session->fresh('entries'));

            return;
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
        $arrivalOrder = ($session?->entries()->max('arrival_order') ?? 0) + 1;

        $session?->entries()->create([
            'league_player_id' => $player->id,
            'entry_type' => 'player',
            'arrival_order' => $arrivalOrder,
            'current_cut_paid' => $isPaidForQueue,
            'guest_fee_paid' => false,
            'was_marked_paid_on_arrival' => ! $alreadyPaid && $paid === true,
            'priority_bucket' => $this->operations->hasPastDue($cut) && ! $isPaidForQueue
                ? 'member_unpaid'
                : 'member_priority',
        ]);
    }

    public function storeGuest(User $user, string $guestName): void
    {
        $context = $this->operations->requireAdminContext($user);
        $cut = $this->operations->activeCutForLeague($context['league']);
        $session = $this->operations->currentSessionForLeague($context['league'], $cut);

        if ($session?->status === 'prepared') {
            throw ValidationException::withMessages([
                'session' => 'La jornada ya fue preparada. Reinicia la lista de llegada para agregar invitados nuevos.',
            ]);
        }

        $session?->entries()->create([
            'guest_name' => $guestName,
            'entry_type' => 'guest',
            'arrival_order' => ($session->entries()->max('arrival_order') ?? 0) + 1,
            'guest_fee_paid' => false,
            'current_cut_paid' => false,
            'priority_bucket' => 'guest_paid',
        ]);
    }

    public function updateGuestPayment(User $user, LeagueSessionEntry $guestEntry, bool $paid): void
    {
        $context = $this->operations->requireAdminContext($user);
        $cut = $this->operations->activeCutForLeague($context['league']);
        $session = $this->operations->currentSessionForLeague($context['league'], $cut);

        if ($guestEntry->league_session_id !== $session?->id || $guestEntry->entry_type !== 'guest') {
            throw ValidationException::withMessages([
                'guest_id' => 'El invitado seleccionado no pertenece a la jornada activa.',
            ]);
        }

        $guestEntry->forceFill([
            'guest_fee_paid' => $paid,
        ])->save();
    }

    public function deleteGuest(User $user, LeagueSessionEntry $guestEntry): void
    {
        $context = $this->operations->requireAdminContext($user);
        $cut = $this->operations->activeCutForLeague($context['league']);
        $session = $this->operations->currentSessionForLeague($context['league'], $cut);

        if ($guestEntry->league_session_id !== $session?->id || $guestEntry->entry_type !== 'guest') {
            throw ValidationException::withMessages([
                'guest_id' => 'El invitado seleccionado no pertenece a la jornada activa.',
            ]);
        }

        $guestEntry->delete();
        $this->resequenceEntries($session->fresh('entries'));
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
                ])->save();
            }
        }

        $entries = $session->entries()
            ->with('player')
            ->orderBy('arrival_order')
            ->get();
        $members = $entries->where('entry_type', 'player')->values();

        if ($members->count() < 10) {
            throw ValidationException::withMessages([
                'session' => 'Se necesitan al menos 10 miembros marcados para iniciar la jornada.',
            ]);
        }

        $entries
            ->where('entry_type', 'guest')
            ->where('guest_fee_paid', false)
            ->each
            ->delete();

        $entries = $session->entries()
            ->with('player')
            ->orderBy('arrival_order')
            ->get();

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
                ->whereNotNull('queue_seed')
                ->update(['queue_seed' => null]);

            foreach ($queue as $index => $entry) {
                $entry->forceFill([
                    'queue_seed' => $index + 1,
                ])->save();
            }

            $session->forceFill([
                'status' => 'prepared',
                'started_at' => now(),
                'prepared_at' => now(),
                'initial_pool' => $this->serializeEntries($firstTen),
                'initial_queue' => $this->serializeEntries($queue),
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
            $session->entries()->delete();
            $session->forceFill([
                'status' => 'arrival_open',
                'started_at' => null,
                'prepared_at' => null,
                'initial_pool' => null,
                'initial_queue' => null,
            ])->save();
        });
    }

    private function resequenceEntries(LeagueSession $session): void
    {
        $session->entries
            ->sortBy('arrival_order')
            ->values()
            ->each(function (LeagueSessionEntry $entry, int $index): void {
                $entry->forceFill([
                    'arrival_order' => $index + 1,
                ])->save();
            });
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
}

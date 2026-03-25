<?php

namespace App\Services\LeagueOperations;

use App\Enums\LeagueMembershipRole;
use App\Models\League;
use App\Models\LeagueCut;
use App\Models\LeagueCutConfiguration;
use App\Models\LeagueCutExpense;
use App\Models\LeagueCutPlayerBalance;
use App\Models\LeagueFeeSchedule;
use App\Models\LeagueMembership;
use App\Models\LeaguePlayer;
use App\Models\LeagueSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LeagueOperationsService
{
    public const DEFAULT_MEMBER_FEE_CENTS = 60000;

    public const DEFAULT_GUEST_FEE_CENTS = 10000;

    public const DEFAULT_REFERRAL_CREDIT_CENTS = 20000;

    public const DEFAULT_COURT_RENT_CENTS = 1000000;

    public const DEFAULT_REFEREE_FEE_CENTS = 130000;

    public const DEFAULT_SUPPLIES_FEE_CENTS = 35000;

    /**
     * @return array{league: League, membership: LeagueMembership, role: LeagueMembershipRole}|null
     */
    public function activeContextFor(User $user): ?array
    {
        if ($user->isGeneralAdmin()) {
            return null;
        }

        $membership = LeagueMembership::query()
            ->with('league')
            ->where('user_id', $user->id)
            ->get()
            ->filter(fn (LeagueMembership $membership): bool => $membership->league !== null)
            ->sortBy(fn (LeagueMembership $membership): string => sprintf(
                '%d-%d-%02d-%s',
                $user->active_league_id !== null && $membership->league_id === $user->active_league_id ? 0 : 1,
                $membership->league?->is_active ? 0 : 1,
                $membership->role->sortOrder(),
                mb_strtolower($membership->league?->name ?? ''),
            ))
            ->first();

        if ($membership === null || $membership->league === null) {
            return null;
        }

        return [
            'league' => $membership->league,
            'membership' => $membership,
            'role' => $membership->role,
        ];
    }

    /**
     * @return array{league: League, membership: LeagueMembership, role: LeagueMembershipRole}
     */
    public function requireActiveContext(User $user): array
    {
        $context = $this->activeContextFor($user);

        if ($context === null) {
            throw new AuthorizationException('La cuenta no tiene una liga activa disponible.');
        }

        if (! $context['league']->is_active) {
            throw new AuthorizationException('La liga activa seleccionada no esta disponible en este momento.');
        }

        return $context;
    }

    /**
     * @return array{league: League, membership: LeagueMembership, role: LeagueMembershipRole}
     */
    public function requireOperationalContext(User $user): array
    {
        $context = $this->requireActiveContext($user);

        if (! $context['role']->canAccessOperationalModules()) {
            throw new AuthorizationException('Tu rol actual no puede entrar a los modulos operativos de la liga.');
        }

        return $context;
    }

    /**
     * @return array{league: League, membership: LeagueMembership, role: LeagueMembershipRole}
     */
    public function requireAdminContext(User $user): array
    {
        $context = $this->requireActiveContext($user);

        if (! $context['role']->canManageLeague()) {
            throw new AuthorizationException('Solo la administracion de la liga puede ejecutar esta accion.');
        }

        return $context;
    }

    public function hasPastDue(LeagueCut $cut, ?CarbonImmutable $today = null): bool
    {
        $today ??= now()->toImmutable()->startOfDay();

        return $today->greaterThanOrEqualTo($cut->due_on->toImmutable());
    }

    public function activeCutForLeague(League $league, ?CarbonImmutable $today = null): LeagueCut
    {
        $today ??= now()->toImmutable()->startOfDay();

        return DB::transaction(function () use ($league, $today): LeagueCut {
            $configuration = $this->activeConfigurationForLeague($league, $today);
            $memberFee = $this->activeFeeScheduleForLeague($league, 'member_monthly', $today);
            $guestFee = $this->activeFeeScheduleForLeague($league, 'guest_session', $today);
            $window = $this->buildCutWindow($today, $configuration->cut_day);

            /** @var LeagueCut|null $cut */
            $cut = LeagueCut::query()
                ->where('league_id', $league->id)
                ->whereDate('starts_on', $window['starts_on']->toDateString())
                ->first();

            if ($cut === null) {
                $cut = LeagueCut::query()->create([
                    'league_id' => $league->id,
                    'league_cut_configuration_id' => $configuration->id,
                    'label' => $window['label'],
                    'starts_on' => $window['starts_on']->toDateString(),
                    'ends_on' => $window['ends_on']->toDateString(),
                    'due_on' => $window['due_on']->toDateString(),
                    'sessions_limit' => $configuration->sessions_limit,
                    'game_days' => $configuration->game_days ?? ['Sabado'],
                    'member_fee_amount_cents' => $memberFee->amount_cents,
                    'guest_fee_amount_cents' => $guestFee->amount_cents,
                    'status' => 'open',
                ]);
            }

            $cut->forceFill([
                'league_cut_configuration_id' => $configuration->id,
                'label' => $window['label'],
                'ends_on' => $window['ends_on']->toDateString(),
                'due_on' => $window['due_on']->toDateString(),
                'sessions_limit' => $configuration->sessions_limit,
                'game_days' => $configuration->game_days ?? ['Sabado'],
                'member_fee_amount_cents' => $memberFee->amount_cents,
                'guest_fee_amount_cents' => $guestFee->amount_cents,
            ])->save();

            $this->ensureSystemExpenses($cut->fresh());

            return $cut->fresh();
        });
    }

    /**
     * @return Collection<int, LeagueCut>
     */
    public function cutsForLeague(League $league): Collection
    {
        $this->activeCutForLeague($league);

        return $league->cuts()
            ->orderByDesc('starts_on')
            ->get();
    }

    public function currentSessionForLeague(League $league, LeagueCut $cut, bool $createIfMissing = true): ?LeagueSession
    {
        $today = now()->toImmutable()->startOfDay()->toDateString();

        $session = LeagueSession::query()
            ->with(['entries.player'])
            ->where('league_id', $league->id)
            ->whereDate('session_date', $today)
            ->first();

        if ($session !== null || ! $createIfMissing) {
            return $session;
        }

        return LeagueSession::query()->create([
            'league_id' => $league->id,
            'league_cut_id' => $cut->id,
            'session_date' => $today,
            'status' => 'arrival_open',
        ])->fresh(['entries.player']);
    }

    public function currentConfigurationForLeague(League $league, ?CarbonImmutable $today = null): LeagueCutConfiguration
    {
        $today ??= now()->toImmutable()->startOfDay();

        return $this->activeConfigurationForLeague($league, $today);
    }

    public function currentFeeScheduleForLeague(League $league, string $feeType, ?CarbonImmutable $today = null): LeagueFeeSchedule
    {
        $today ??= now()->toImmutable()->startOfDay();

        return $this->activeFeeScheduleForLeague($league, $feeType, $today);
    }

    /**
     * @return Collection<int, LeagueCutPlayerBalance>
     */
    public function balancesThroughCutForPlayer(LeaguePlayer $player, LeagueCut $selectedCut): Collection
    {
        return LeagueCut::query()
            ->where('league_id', $selectedCut->league_id)
            ->where('starts_on', '<=', $selectedCut->starts_on)
            ->orderBy('starts_on')
            ->get()
            ->map(fn (LeagueCut $cut): LeagueCutPlayerBalance => $this->balanceForPlayer($cut, $player));
    }

    public function balanceForPlayer(LeagueCut $cut, LeaguePlayer $player): LeagueCutPlayerBalance
    {
        /** @var LeagueCutPlayerBalance $balance */
        $balance = LeagueCutPlayerBalance::query()->firstOrCreate(
            [
                'league_cut_id' => $cut->id,
                'league_player_id' => $player->id,
            ],
            [
                'carry_in_cents' => $this->carryInFromPreviousCut($cut, $player),
                'amount_due_cents' => $cut->member_fee_amount_cents,
                'status' => 'pending',
            ],
        );

        if ($balance->amount_due_cents !== $cut->member_fee_amount_cents) {
            $balance->forceFill([
                'amount_due_cents' => $cut->member_fee_amount_cents,
            ])->save();
        }

        return $this->recalculateBalance($balance->fresh('transactions'));
    }

    public function recalculateBalance(LeagueCutPlayerBalance $balance): LeagueCutPlayerBalance
    {
        $balance->loadMissing('transactions');

        $cashTotal = (int) $balance->transactions
            ->whereIn('transaction_type', ['cash_payment', 'payment_reversal'])
            ->sum('amount_cents');

        $creditTotal = (int) $balance->transactions
            ->whereIn('transaction_type', ['referral_credit', 'referral_credit_reversal'])
            ->sum('amount_cents');

        $effectiveTotal = max(0, $balance->carry_in_cents + $cashTotal + $creditTotal);
        $extraCredit = max(0, $effectiveTotal - $balance->amount_due_cents);
        $status = $effectiveTotal <= 0
            ? 'pending'
            : ($effectiveTotal < $balance->amount_due_cents ? 'partial' : 'paid');
        $lastPaymentAt = $balance->transactions
            ->sortByDesc('created_at')
            ->first()?->created_at;

        $balance->forceFill([
            'amount_paid_cents' => max(0, $cashTotal),
            'referral_credit_applied_cents' => max(0, $creditTotal),
            'extra_credit_cents' => $extraCredit,
            'status' => $status,
            'last_payment_at' => $lastPaymentAt,
            'paid_at' => $status === 'paid' ? ($balance->paid_at ?? $lastPaymentAt) : null,
        ])->save();

        return $balance->fresh('transactions');
    }

    public function availableReferralCredit(LeaguePlayer $player): int
    {
        $earned = (int) $player->referralsMade()->sum('credit_amount_cents');
        $used = (int) $player->balances()->sum('referral_credit_applied_cents');

        return max(0, $earned - $used);
    }

    public function outstandingAmount(LeagueCutPlayerBalance $balance): int
    {
        $covered = $balance->carry_in_cents
            + $balance->amount_paid_cents
            + $balance->referral_credit_applied_cents;

        return max(0, $balance->amount_due_cents - $covered);
    }

    public function previousDebtAmount(LeaguePlayer $player, LeagueCut $currentCut): int
    {
        return (int) $player->balances()
            ->whereHas('cut', fn ($query) => $query
                ->where('league_id', $currentCut->league_id)
                ->where('starts_on', '<', $currentCut->starts_on))
            ->get()
            ->sum(fn (LeagueCutPlayerBalance $balance): int => max(
                0,
                $balance->amount_due_cents
                    - ($balance->carry_in_cents + $balance->amount_paid_cents + $balance->referral_credit_applied_cents),
            ));
    }

    public function cashIncomeForCut(LeagueCut $cut): int
    {
        return (int) DB::table('league_cut_player_transactions')
            ->where('source_cut_id', $cut->id)
            ->whereIn('transaction_type', ['cash_payment', 'payment_reversal'])
            ->sum('amount_cents');
    }

    /**
     * @return array<int, int>
     */
    public function attendanceCounts(League $league): array
    {
        return DB::table('league_session_entries')
            ->join('league_sessions', 'league_sessions.id', '=', 'league_session_entries.league_session_id')
            ->where('league_sessions.league_id', $league->id)
            ->where('league_sessions.status', 'completed')
            ->whereNotNull('league_session_entries.league_player_id')
            ->groupBy('league_session_entries.league_player_id')
            ->pluck(DB::raw('count(*)'), 'league_session_entries.league_player_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    private function ensureSystemExpenses(LeagueCut $cut): void
    {
        $definitions = [
            [
                'expense_type' => 'court_rent',
                'name' => 'Alquiler cancha',
                'amount_cents' => self::DEFAULT_COURT_RENT_CENTS,
            ],
            [
                'expense_type' => 'referees',
                'name' => 'Arbitros',
                'amount_cents' => self::DEFAULT_REFEREE_FEE_CENTS * $cut->sessions_limit,
            ],
            [
                'expense_type' => 'supplies',
                'name' => 'Agua, vasos e hielo',
                'amount_cents' => self::DEFAULT_SUPPLIES_FEE_CENTS * $cut->sessions_limit,
            ],
        ];

        foreach ($definitions as $definition) {
            LeagueCutExpense::query()->updateOrCreate(
                [
                    'league_cut_id' => $cut->id,
                    'expense_type' => $definition['expense_type'],
                    'is_system_generated' => true,
                ],
                [
                    'name' => $definition['name'],
                    'amount_cents' => $definition['amount_cents'],
                    'spent_on' => $cut->ends_on,
                ],
            );
        }
    }

    private function carryInFromPreviousCut(LeagueCut $cut, LeaguePlayer $player): int
    {
        $previousCut = LeagueCut::query()
            ->where('league_id', $cut->league_id)
            ->where('starts_on', '<', $cut->starts_on)
            ->orderByDesc('starts_on')
            ->first();

        if ($previousCut === null) {
            return 0;
        }

        $previousBalance = LeagueCutPlayerBalance::query()
            ->where('league_cut_id', $previousCut->id)
            ->where('league_player_id', $player->id)
            ->first();

        return $previousBalance?->extra_credit_cents ?? 0;
    }

    private function activeConfigurationForLeague(League $league, CarbonImmutable $date): LeagueCutConfiguration
    {
        $configuration = LeagueCutConfiguration::query()
            ->where('league_id', $league->id)
            ->whereDate('effective_from', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query->whereNull('effective_until')
                    ->orWhereDate('effective_until', '>=', $date);
            })
            ->orderByDesc('effective_from')
            ->first();

        if ($configuration !== null) {
            return $configuration;
        }

        return LeagueCutConfiguration::query()->create([
            'league_id' => $league->id,
            'sessions_limit' => 4,
            'game_days' => ['Sabado'],
            'cut_day' => 15,
            'effective_from' => $date->startOfMonth()->toDateString(),
        ]);
    }

    private function activeFeeScheduleForLeague(League $league, string $feeType, CarbonImmutable $date): LeagueFeeSchedule
    {
        $schedule = LeagueFeeSchedule::query()
            ->where('league_id', $league->id)
            ->where('fee_type', $feeType)
            ->whereDate('effective_from', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query->whereNull('effective_until')
                    ->orWhereDate('effective_until', '>=', $date);
            })
            ->orderByDesc('effective_from')
            ->first();

        if ($schedule !== null) {
            return $schedule;
        }

        return LeagueFeeSchedule::query()->create([
            'league_id' => $league->id,
            'fee_type' => $feeType,
            'amount_cents' => match ($feeType) {
                'guest_session' => self::DEFAULT_GUEST_FEE_CENTS,
                'referral_credit' => self::DEFAULT_REFERRAL_CREDIT_CENTS,
                default => self::DEFAULT_MEMBER_FEE_CENTS,
            },
            'effective_from' => $date->startOfMonth()->toDateString(),
        ]);
    }

    /**
     * @return array{starts_on: CarbonImmutable, ends_on: CarbonImmutable, due_on: CarbonImmutable, label: string}
     */
    private function buildCutWindow(CarbonImmutable $date, int $cutDay): array
    {
        $currentMonthDue = $this->dueDateForMonth($date, $cutDay);

        if ($date->day <= $currentMonthDue->day) {
            $startsOn = $this->dueDateForMonth($date->subMonthNoOverflow(), $cutDay);
            $endsOn = $currentMonthDue;
        } else {
            $startsOn = $currentMonthDue;
            $endsOn = $this->dueDateForMonth($date->addMonthNoOverflow(), $cutDay);
        }

        return [
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'due_on' => $endsOn,
            'label' => sprintf(
                '%d %s - %d %s %d',
                $startsOn->day,
                $this->monthName($startsOn->month),
                $endsOn->day,
                $this->monthName($endsOn->month),
                $endsOn->year,
            ),
        ];
    }

    private function dueDateForMonth(CarbonImmutable $date, int $cutDay): CarbonImmutable
    {
        $safeDay = min($cutDay, $date->endOfMonth()->day);

        return CarbonImmutable::create(
            $date->year,
            $date->month,
            $safeDay,
            0,
            0,
            0,
            $date->timezone,
        );
    }

    private function monthName(int $month): string
    {
        return [
            1 => 'Ene',
            2 => 'Feb',
            3 => 'Mar',
            4 => 'Abr',
            5 => 'May',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Ago',
            9 => 'Sep',
            10 => 'Oct',
            11 => 'Nov',
            12 => 'Dic',
        ][$month];
    }
}

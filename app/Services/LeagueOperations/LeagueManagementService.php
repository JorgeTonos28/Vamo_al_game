<?php

namespace App\Services\LeagueOperations;

use App\Enums\AccountRole;
use App\Enums\LeagueMembershipRole;
use App\Models\League;
use App\Models\LeagueCut;
use App\Models\LeagueCutExpense;
use App\Models\LeagueCutPlayerBalance;
use App\Models\LeagueFeeSchedule;
use App\Models\LeagueMembership;
use App\Models\LeaguePlayer;
use App\Models\LeaguePlayerReferral;
use App\Models\User;
use App\Notifications\AppInvitationNotification;
use App\Services\Invitations\UserInvitationService;
use App\Services\LeagueMemberships\LeagueMembershipManager;
use App\Support\UserName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LeagueManagementService
{
    public function __construct(
        private readonly LeagueOperationsService $operations,
        private readonly LeagueMembershipManager $membershipManager,
        private readonly UserInvitationService $userInvitationService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function pageData(User $user, ?int $selectedCutId = null): array
    {
        $context = $this->operations->requireAdminContext($user);
        $league = $context['league'];
        $activeCut = $this->operations->activeCutForLeague($league);
        $cuts = $this->operations->cutsForLeague($league);
        $selectedCut = $selectedCutId !== null
            ? $cuts->firstWhere('id', $selectedCutId)
            : $activeCut;
        $selectedCut ??= $activeCut;

        $players = $this->operations->activeOperationalPlayersQuery($league)
            ->orderBy('display_name')
            ->get();
        $expenses = $selectedCut->expenses()
            ->orderBy('is_system_generated', 'desc')
            ->orderBy('expense_type')
            ->orderBy('name')
            ->get();
        $payments = $players
            ->map(fn (LeaguePlayer $player): array => $this->serializePlayerPaymentRow($player, $selectedCut))
            ->values();
        $cashIncome = $this->operations->cashIncomeForCut($selectedCut);
        $guestIncome = (int) $selectedCut->sessions()
            ->withCount([
                'entries as paid_guest_count' => fn ($query) => $query
                    ->where('entry_type', 'guest')
                    ->where('guest_fee_paid', true),
            ])
            ->get()
            ->sum(fn ($session): int => (int) $session->paid_guest_count * $selectedCut->guest_fee_amount_cents);
        $totalExpenses = (int) $expenses->sum('amount_cents');
        $totalIncome = $cashIncome + $guestIncome;
        $balanceAmount = $totalIncome - $totalExpenses;
        $admins = $league->adminMemberships()
            ->with('user')
            ->get()
            ->filter(fn ($membership) => $membership->user !== null)
            ->values();
        $shareAmount = $balanceAmount < 0 && $admins->count() > 0
            ? (int) ceil(abs($balanceAmount) / $admins->count())
            : 0;
        $configuration = $this->operations->currentConfigurationForLeague($league);
        $memberFee = $this->operations->currentFeeScheduleForLeague($league, 'member_monthly');
        $guestFee = $this->operations->currentFeeScheduleForLeague($league, 'guest_session');
        $referralCredit = $this->operations->currentFeeScheduleForLeague($league, 'referral_credit');
        $referrals = $this->groupedReferrals($league);

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
            ],
            'cut_selector' => [
                'selected_cut_id' => $selectedCut->id,
                'cuts' => $cuts->map(fn (LeagueCut $cut): array => [
                    'id' => $cut->id,
                    'label' => $cut->label,
                    'starts_on' => $cut->starts_on?->toDateString(),
                    'ends_on' => $cut->ends_on?->toDateString(),
                    'is_active' => $cut->id === $activeCut->id,
                ])->all(),
            ],
            'summary' => [
                'selected_cut' => [
                    'id' => $selectedCut->id,
                    'label' => $selectedCut->label,
                    'starts_on' => $selectedCut->starts_on?->toDateString(),
                    'ends_on' => $selectedCut->ends_on?->toDateString(),
                    'due_on' => $selectedCut->due_on?->toDateString(),
                    'is_past_due' => $this->operations->hasPastDue($selectedCut),
                ],
                'income' => [
                    'cash_payments_cents' => $cashIncome,
                    'guest_income_cents' => $guestIncome,
                    'total_cents' => $totalIncome,
                ],
                'expenses' => [
                    'total_cents' => $totalExpenses,
                ],
                'balance_cents' => $balanceAmount,
            ],
            'payments' => $payments->all(),
            'expenses' => $expenses->map(fn (LeagueCutExpense $expense): array => [
                'id' => $expense->id,
                'name' => $expense->name,
                'expense_type' => $expense->expense_type,
                'amount_cents' => $expense->amount_cents,
                'is_system_generated' => $expense->is_system_generated,
                'is_fixed' => $expense->is_system_generated || $expense->expense_type === 'fixed',
            ])->all(),
            'board' => [
                'members' => $admins->map(fn ($membership): array => [
                    'id' => $membership->user->id,
                    'name' => $membership->user->name,
                    'share_cents' => $shareAmount,
                ])->all(),
                'share_cents' => $shareAmount,
            ],
            'settings' => [
                'sessions_limit' => $configuration->sessions_limit,
                'game_days' => $configuration->game_days ?? ['Sabado'],
                'cut_day' => $configuration->cut_day,
                'incoming_team_guest_limit' => max(0, (int) ($league->incoming_team_guest_limit ?? 2)),
                'member_fee_amount_cents' => $memberFee->amount_cents,
                'guest_fee_amount_cents' => $guestFee->amount_cents,
                'referral_credit_amount_cents' => $referralCredit->amount_cents,
            ],
            'referrals' => $referrals,
            'roster_management' => $this->rosterData($user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rosterData(User $user): array
    {
        $context = $this->operations->activeContextFor($user);

        if ($context === null || ! $context['role']->canManageLeague()) {
            return [
                'can_manage' => false,
                'active_players' => [],
                'inactive_players' => [],
                'referral_options' => [],
                'referral_credit_amount_cents' => 0,
            ];
        }

        $league = $context['league'];
        $players = $this->rosterPlayersQuery($league)
            ->with([
                'user.leagueMemberships' => fn ($query) => $query->where('league_id', $league->id),
            ])
            ->orderBy('status')
            ->orderBy('display_name')
            ->get();
        $referralCredit = $this->operations->currentFeeScheduleForLeague($league, 'referral_credit');

        return [
            'can_manage' => true,
            'active_players' => $players
                ->where('status', 'active')
                ->values()
                ->map(fn (LeaguePlayer $player): array => $this->serializeRosterPlayer($league, $player))
                ->all(),
            'inactive_players' => $players
                ->where('status', 'inactive')
                ->values()
                ->map(fn (LeaguePlayer $player): array => $this->serializeRosterPlayer($league, $player))
                ->all(),
            'referral_options' => $players
                ->where('status', 'active')
                ->filter(fn (LeaguePlayer $player): bool => $this->resolveRosterRole($league, $player) === LeagueMembershipRole::Member)
                ->values()
                ->map(fn (LeaguePlayer $player): array => [
                    'id' => $player->id,
                    'name' => $player->display_name,
                ])->all(),
            'referral_credit_amount_cents' => $referralCredit->amount_cents,
        ];
    }

    public function recordPayment(
        User $user,
        LeaguePlayer $player,
        int $amountCents,
        bool $applyReferralCredit = false,
        ?int $cutId = null,
    ): LeagueCutPlayerBalance {
        $context = $this->operations->requireAdminContext($user);
        $cut = $this->selectedCut($context['league'], $cutId);
        $this->ensurePlayerBelongsToLeague($player, $context['league']);

        if ($amountCents <= 0 && ! $applyReferralCredit) {
            throw ValidationException::withMessages([
                'amount' => 'Debes registrar un monto valido o aplicar credito por referidos.',
            ]);
        }

        return DB::transaction(function () use ($user, $player, $amountCents, $applyReferralCredit, $cut): LeagueCutPlayerBalance {
            $balances = $this->operations->balancesThroughCutForPlayer($player, $cut);

            if ($applyReferralCredit) {
                $availableCredit = $this->operations->availableReferralCredit($player);
                $creditToApply = min($availableCredit, $this->balancesOutstandingAmount($balances));

                $this->allocateAcrossBalances(
                    $balances,
                    $creditToApply,
                    'referral_credit',
                    $user,
                    $cut,
                    false,
                );
            }

            if ($amountCents > 0) {
                $this->allocateAcrossBalances(
                    $balances,
                    $amountCents,
                    'cash_payment',
                    $user,
                    $cut,
                    true,
                );
            }

            return $this->recalculateBalances($balances, $cut);
        });
    }

    public function removePayment(User $user, LeaguePlayer $player, ?int $cutId = null): LeagueCutPlayerBalance
    {
        $context = $this->operations->requireAdminContext($user);
        $cut = $this->selectedCut($context['league'], $cutId);
        $this->ensurePlayerBelongsToLeague($player, $context['league']);

        return DB::transaction(function () use ($user, $player, $cut): LeagueCutPlayerBalance {
            $balances = $this->operations->balancesThroughCutForPlayer($player, $cut);

            foreach ($balances as $balance) {
                $cashNet = (int) $balance->transactions()
                    ->where('source_cut_id', $cut->id)
                    ->whereIn('transaction_type', ['cash_payment', 'payment_reversal'])
                    ->sum('amount_cents');

                if ($cashNet > 0) {
                    $balance->transactions()->create([
                        'transaction_type' => 'payment_reversal',
                        'amount_cents' => -$cashNet,
                        'note' => sprintf('Reversion del pago registrado para el corte %s.', $cut->label),
                        'source_cut_id' => $cut->id,
                        'recorded_by_user_id' => $user->id,
                    ]);
                }

                $creditNet = (int) $balance->transactions()
                    ->where('source_cut_id', $cut->id)
                    ->whereIn('transaction_type', ['referral_credit', 'referral_credit_reversal'])
                    ->sum('amount_cents');

                if ($creditNet > 0) {
                    $balance->transactions()->create([
                        'transaction_type' => 'referral_credit_reversal',
                        'amount_cents' => -$creditNet,
                        'note' => sprintf('Reversion del credito aplicado para el corte %s.', $cut->label),
                        'source_cut_id' => $cut->id,
                        'recorded_by_user_id' => $user->id,
                    ]);
                }
            }

            return $this->recalculateBalances($balances, $cut);
        });
    }

    public function storeExpense(
        User $user,
        string $name,
        int $amountCents,
        string $expenseType = 'custom',
        ?int $cutId = null,
    ): LeagueCutExpense {
        $context = $this->operations->requireAdminContext($user);
        $cut = $this->selectedCut($context['league'], $cutId);

        if ($amountCents <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Debes indicar un monto mayor que cero.',
            ]);
        }

        return $cut->expenses()->create([
            'expense_type' => $expenseType,
            'name' => $name,
            'amount_cents' => $amountCents,
            'is_system_generated' => false,
            'recorded_by_user_id' => $user->id,
            'spent_on' => now()->toDateString(),
        ]);
    }

    public function deleteExpense(User $user, LeagueCutExpense $expense): void
    {
        $context = $this->operations->requireAdminContext($user);

        if ($expense->cut->league_id !== $context['league']->id) {
            throw ValidationException::withMessages([
                'expense_id' => 'Ese gasto no pertenece a la liga activa.',
            ]);
        }

        $expense->delete();
    }

    /**
     * @param  array{
     *      name?: string|null,
     *      sessions_limit: int,
     *      game_days: array<int, string>,
     *      cut_day: int,
     *      incoming_team_guest_limit: int,
     *      member_fee_amount_cents: int,
     *      guest_fee_amount_cents: int,
     *      referral_credit_amount_cents: int
     * }  $data
     */
    public function updateSettings(User $user, array $data): void
    {
        $context = $this->operations->requireAdminContext($user);
        $league = $context['league'];
        $today = now()->toImmutable()->startOfDay();
        $yesterday = $today->subDay()->toDateString();
        $todayString = $today->toDateString();

        DB::transaction(function () use ($user, $league, $data, $todayString, $yesterday): void {
            $league->forceFill([
                'name' => filled($data['name'] ?? null) ? $data['name'] : $league->name,
                'incoming_team_guest_limit' => $data['incoming_team_guest_limit'],
            ])->save();

            $configuration = $this->operations->currentConfigurationForLeague($league);

            if ($configuration->effective_from?->toDateString() === $todayString) {
                $configuration->forceFill([
                    'sessions_limit' => $data['sessions_limit'],
                    'game_days' => $data['game_days'],
                    'cut_day' => $data['cut_day'],
                ])->save();
            } else {
                $configuration->forceFill([
                    'effective_until' => $yesterday,
                ])->save();

                $league->cutConfigurations()->create([
                    'sessions_limit' => $data['sessions_limit'],
                    'game_days' => $data['game_days'],
                    'cut_day' => $data['cut_day'],
                    'effective_from' => $todayString,
                    'created_by_user_id' => $user->id,
                ]);
            }

            $this->upsertFeeSchedule($league, $user, 'member_monthly', $data['member_fee_amount_cents'], $todayString, $yesterday);
            $this->upsertFeeSchedule($league, $user, 'guest_session', $data['guest_fee_amount_cents'], $todayString, $yesterday);
            $this->upsertFeeSchedule($league, $user, 'referral_credit', $data['referral_credit_amount_cents'], $todayString, $yesterday);
        });
    }

    public function storeReferral(User $user, LeaguePlayer $referrer, LeaguePlayer $referred): LeaguePlayerReferral
    {
        $context = $this->operations->requireAdminContext($user);
        $this->ensurePlayerBelongsToLeague($referrer, $context['league']);
        $this->ensurePlayerBelongsToLeague($referred, $context['league']);

        if ($referrer->is($referred)) {
            throw ValidationException::withMessages([
                'referred_player_id' => 'El referidor y el referido no pueden ser la misma persona.',
            ]);
        }

        $alreadyExists = LeaguePlayerReferral::query()
            ->where('league_id', $context['league']->id)
            ->where('referred_player_id', $referred->id)
            ->exists();

        if ($alreadyExists) {
            throw ValidationException::withMessages([
                'referred_player_id' => 'Ese miembro ya tiene un referido registrado en esta liga.',
            ]);
        }

        return LeaguePlayerReferral::query()->create([
            'league_id' => $context['league']->id,
            'referrer_player_id' => $referrer->id,
            'referred_player_id' => $referred->id,
            'credit_amount_cents' => $this->operations
                ->currentFeeScheduleForLeague($context['league'], 'referral_credit')
                ->amount_cents,
            'created_by_user_id' => $user->id,
        ]);
    }

    public function deleteReferral(User $user, LeaguePlayerReferral $referral): void
    {
        $context = $this->operations->requireAdminContext($user);

        if ($referral->league_id !== $context['league']->id) {
            throw ValidationException::withMessages([
                'referral_id' => 'Ese referido no pertenece a la liga activa.',
            ]);
        }

        $referral->delete();
    }

    public function storePlayer(
        User $user,
        string $displayName,
        ?int $jerseyNumber = null,
        ?LeaguePlayer $referredBy = null,
    ): LeaguePlayer {
        $context = $this->operations->requireAdminContext($user);
        $league = $context['league'];

        $exists = $league->players()
            ->whereRaw('lower(display_name) = ?', [mb_strtolower($displayName)])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'display_name' => 'Ya existe un miembro con ese nombre dentro de la liga.',
            ]);
        }

        return DB::transaction(function () use ($user, $league, $displayName, $jerseyNumber, $referredBy): LeaguePlayer {
            $player = $league->players()->create([
                'display_name' => $displayName,
                'jersey_number' => $jerseyNumber,
                'status' => 'active',
                'created_by_user_id' => $user->id,
                'updated_by_user_id' => $user->id,
                'joined_at' => now(),
            ]);

            if ($referredBy !== null) {
                $this->storeReferral($user, $referredBy, $player);
            }

            return $player->fresh();
        });
    }

    /**
     * @param  array{
     *     first_name: string,
     *     last_name: string,
     *     document_id: string,
     *     phone?: string|null,
     *     address?: string|null,
     *     email?: string|null,
     *     jersey_number?: int|null,
     *     account_role: string
     * }  $data
     */
    public function updateRosterMember(User $user, LeaguePlayer $player, array $data): LeaguePlayer
    {
        $context = $this->operations->requireAdminContext($user);
        $this->ensurePlayerBelongsToLeague($player, $context['league']);
        $league = $context['league'];
        $displayName = UserName::displayName($data['first_name'], $data['last_name']);
        $membershipRole = $data['account_role'] === AccountRole::LeagueAdmin->value
            ? LeagueMembershipRole::Admin
            : LeagueMembershipRole::Member;

        $exists = $league->players()
            ->whereKeyNot($player->id)
            ->whereRaw('lower(display_name) = ?', [mb_strtolower($displayName)])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'display_name' => 'Ya existe otro miembro con ese nombre dentro de la liga.',
            ]);
        }

        return DB::transaction(function () use ($user, $player, $data, $displayName, $membershipRole, $league): LeaguePlayer {
            if ($player->user === null && $membershipRole === LeagueMembershipRole::Member) {
                // Keep the same roster row when a member without account details is completed later.
                $player->forceFill([
                    'display_name' => $displayName,
                    'jersey_number' => $data['jersey_number'] ?? null,
                    'updated_by_user_id' => $user->id,
                ])->save();
            }

            $linkedUser = $player->user;
            $previousEmail = $linkedUser?->email;

            if ($linkedUser === null) {
                $linkedUser = User::query()->create([
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'name' => $displayName,
                    'document_id' => $data['document_id'],
                    'phone' => blank($data['phone'] ?? null) ? null : $data['phone'],
                    'address' => blank($data['address'] ?? null) ? null : $data['address'],
                    'email' => blank($data['email'] ?? null) ? null : $data['email'],
                    'password' => Str::password(32),
                    'account_role' => $membershipRole === LeagueMembershipRole::Admin
                        ? AccountRole::LeagueAdmin
                        : AccountRole::Member,
                    'invited_by_user_id' => $user->id,
                    'invited_at' => now(),
                    'onboarded_at' => null,
                ]);
            } else {
                $linkedUser->forceFill([
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'name' => $displayName,
                    'document_id' => $data['document_id'],
                    'phone' => blank($data['phone'] ?? null) ? null : $data['phone'],
                    'address' => blank($data['address'] ?? null) ? null : $data['address'],
                    'email' => blank($data['email'] ?? null) ? null : $data['email'],
                    'account_role' => $membershipRole === LeagueMembershipRole::Admin
                        ? AccountRole::LeagueAdmin
                        : AccountRole::Member,
                ])->save();
            }

            $this->membershipManager->assign($linkedUser, $league, $membershipRole, $user);

            /** @var LeaguePlayer|null $resolvedPlayer */
            $resolvedPlayer = $league->players()
                ->where('user_id', $linkedUser->id)
                ->orderByDesc('status')
                ->first();

            if ($resolvedPlayer === null) {
                $resolvedPlayer = $player->fresh();
            }

            $resolvedPlayer->forceFill([
                'display_name' => $displayName,
                'jersey_number' => $data['jersey_number'] ?? null,
                'updated_by_user_id' => $user->id,
                'status' => $membershipRole === LeagueMembershipRole::Member ? 'active' : 'inactive',
                'removed_at' => $membershipRole === LeagueMembershipRole::Member ? null : now(),
            ])->save();

            $newEmail = blank($data['email'] ?? null) ? null : $data['email'];
            $shouldIssueInvitation = filled($newEmail) && $newEmail !== $previousEmail;

            if ($shouldIssueInvitation) {
                $linkedUser->forceFill([
                    'invited_at' => now(),
                    'email_verified_at' => null,
                ])->save();

                $issuedInvitation = $this->userInvitationService->issue($linkedUser);
                $linkedUser->notify(new AppInvitationNotification(
                    $issuedInvitation['invitation'],
                    $issuedInvitation['token'],
                ));
            }

            return $resolvedPlayer->fresh(['user']);
        });
    }

    public function setPlayerActive(User $user, LeaguePlayer $player, bool $active): LeaguePlayer
    {
        $context = $this->operations->requireAdminContext($user);
        $this->ensurePlayerBelongsToLeague($player, $context['league']);
        $role = $this->resolveRosterRole($context['league'], $player);

        if ($active && $role === LeagueMembershipRole::Admin) {
            throw ValidationException::withMessages([
                'player_id' => 'Los administradores no forman parte del roster operativo de juego.',
            ]);
        }

        DB::transaction(function () use ($user, $player, $active): void {
            $player->forceFill([
                'status' => $active ? 'active' : 'inactive',
                'removed_at' => $active ? null : now(),
                'updated_by_user_id' => $user->id,
            ])->save();

            if (! $active) {
                $currentCut = $this->operations->activeCutForLeague($player->league);
                $session = $this->operations->currentSessionForLeague($player->league, $currentCut, false);

                if ($session !== null) {
                    $session->entries()
                        ->where('league_player_id', $player->id)
                        ->delete();
                }
            }
        });

        return $player->fresh();
    }

    private function serializePlayerPaymentRow(LeaguePlayer $player, LeagueCut $selectedCut): array
    {
        $balance = $this->operations->balanceForPlayer($selectedCut, $player);
        $previousDebt = $this->operations->previousDebtAmount($player, $selectedCut);
        $hasPastDue = $this->operations->hasPastDue($selectedCut);
        $statusTone = match (true) {
            $balance->status === 'paid' => 'paid',
            $previousDebt > 0 => 'arrears',
            $hasPastDue => 'overdue',
            default => 'pending',
        };
        $statusMessage = match (true) {
            $balance->status === 'paid' && $balance->extra_credit_cents > 0 && $previousDebt === 0 => 'Pago completo y saldo a favor para el proximo corte.',
            $balance->status === 'paid' => 'Pago completo para este corte.',
            $previousDebt > 0 => 'Tiene montos pendientes de cortes anteriores.',
            $hasPastDue => 'No pago este corte y hoy pierde prioridad operativa.',
            default => 'Pendiente de pago para el corte seleccionado.',
        };

        return [
            'player' => [
                'id' => $player->id,
                'name' => $player->display_name,
                'jersey_number' => $player->jersey_number,
            ],
            'balance' => [
                'status' => $balance->status,
                'amount_due_cents' => $balance->amount_due_cents,
                'amount_paid_cents' => $balance->amount_paid_cents,
                'carry_in_cents' => $balance->carry_in_cents,
                'extra_credit_cents' => $balance->extra_credit_cents,
                'referral_credit_applied_cents' => $balance->referral_credit_applied_cents,
                'available_referral_credit_cents' => $this->operations->availableReferralCredit($player),
                'previous_debt_cents' => $previousDebt,
                'settlement_due_cents' => $previousDebt + $this->operations->outstandingAmount($balance),
                'status_tone' => $statusTone,
                'status_message' => $statusMessage,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function groupedReferrals(League $league): array
    {
        return LeaguePlayerReferral::query()
            ->with(['referrer', 'referred'])
            ->where('league_id', $league->id)
            ->get()
            ->groupBy('referrer_player_id')
            ->map(function (Collection $group): array {
                /** @var LeaguePlayerReferral $first */
                $first = $group->first();
                $availableCredit = $first->referrer !== null
                    ? $this->operations->availableReferralCredit($first->referrer)
                    : 0;

                return [
                    'referrer' => [
                        'id' => $first->referrer?->id,
                        'name' => $first->referrer?->display_name,
                    ],
                    'available_credit_cents' => $availableCredit,
                    'members' => $group->map(fn (LeaguePlayerReferral $referral): array => [
                        'id' => $referral->id,
                        'name' => $referral->referred?->display_name,
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function upsertFeeSchedule(
        League $league,
        User $user,
        string $feeType,
        int $amountCents,
        string $today,
        string $yesterday,
    ): void {
        /** @var LeagueFeeSchedule $schedule */
        $schedule = $this->operations->currentFeeScheduleForLeague($league, $feeType);

        if ($schedule->effective_from?->toDateString() === $today) {
            $schedule->forceFill([
                'amount_cents' => $amountCents,
            ])->save();

            return;
        }

        $schedule->forceFill([
            'effective_until' => $yesterday,
        ])->save();

        $league->feeSchedules()->create([
            'fee_type' => $feeType,
            'amount_cents' => $amountCents,
            'effective_from' => $today,
            'created_by_user_id' => $user->id,
        ]);
    }

    private function selectedCut(League $league, ?int $cutId = null): LeagueCut
    {
        if ($cutId === null) {
            return $this->operations->activeCutForLeague($league);
        }

        /** @var LeagueCut|null $cut */
        $cut = $league->cuts()->whereKey($cutId)->first();

        if ($cut === null) {
            throw ValidationException::withMessages([
                'cut_id' => 'El corte seleccionado no pertenece a la liga activa.',
            ]);
        }

        return $cut;
    }

    private function ensurePlayerBelongsToLeague(LeaguePlayer $player, League $league): void
    {
        if ($player->league_id !== $league->id) {
            throw ValidationException::withMessages([
                'player_id' => 'Ese miembro no pertenece a la liga activa.',
            ]);
        }
    }

    /**
     * @param  Collection<int, LeagueCutPlayerBalance>  $balances
     */
    private function balancesOutstandingAmount(Collection $balances): int
    {
        return (int) $balances->sum(
            fn (LeagueCutPlayerBalance $balance): int => $this->operations->outstandingAmount($balance),
        );
    }

    /**
     * @param  Collection<int, LeagueCutPlayerBalance>  $balances
     */
    private function allocateAcrossBalances(
        Collection $balances,
        int $amountCents,
        string $transactionType,
        User $user,
        LeagueCut $sourceCut,
        bool $allowOverflowOnSelectedCut,
    ): void {
        if ($amountCents <= 0) {
            return;
        }

        $remaining = $amountCents;

        foreach ($balances as $balance) {
            $outstanding = $this->operations->outstandingAmount($balance);

            if ($outstanding <= 0) {
                continue;
            }

            $applied = min($remaining, $outstanding);

            if ($applied <= 0) {
                continue;
            }

            $balance->transactions()->create([
                'transaction_type' => $transactionType,
                'amount_cents' => $applied,
                'note' => $balance->cut->is($sourceCut)
                    ? sprintf('Movimiento aplicado al corte %s.', $sourceCut->label)
                    : sprintf('Movimiento del corte %s aplicado a deuda del corte %s.', $sourceCut->label, $balance->cut->label),
                'source_cut_id' => $sourceCut->id,
                'recorded_by_user_id' => $user->id,
            ]);

            $remaining -= $applied;

            if ($remaining === 0) {
                return;
            }
        }

        if (! $allowOverflowOnSelectedCut || $remaining <= 0) {
            return;
        }

        /** @var LeagueCutPlayerBalance|null $selectedBalance */
        $selectedBalance = $balances->last();

        if ($selectedBalance === null) {
            return;
        }

        $selectedBalance->transactions()->create([
            'transaction_type' => $transactionType,
            'amount_cents' => $remaining,
            'note' => sprintf('Movimiento adicional registrado en el corte %s.', $sourceCut->label),
            'source_cut_id' => $sourceCut->id,
            'recorded_by_user_id' => $user->id,
        ]);
    }

    /**
     * @param  Collection<int, LeagueCutPlayerBalance>  $balances
     */
    private function recalculateBalances(Collection $balances, LeagueCut $selectedCut): LeagueCutPlayerBalance
    {
        $recalculated = $balances
            ->map(fn (LeagueCutPlayerBalance $balance): LeagueCutPlayerBalance => $this->operations
                ->recalculateBalance($balance->fresh('transactions')));

        /** @var LeagueCutPlayerBalance|null $selectedBalance */
        $selectedBalance = $recalculated->first(
            fn (LeagueCutPlayerBalance $balance): bool => $balance->league_cut_id === $selectedCut->id,
        );

        return $selectedBalance ?? $this->operations->balanceForPlayer($selectedCut, $balances->first()->player);
    }

    private function rosterPlayersQuery(League $league): Builder|HasMany
    {
        return $league->players()
            ->where(function (Builder $query) use ($league): void {
                $query->whereNull('user_id')
                    ->orWhereHas('user.leagueMemberships', function (Builder $membershipQuery) use ($league): void {
                        $membershipQuery
                            ->where('league_id', $league->id)
                            ->whereIn('role', [
                                LeagueMembershipRole::Member,
                                LeagueMembershipRole::Admin,
                            ]);
                    });
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRosterPlayer(League $league, LeaguePlayer $player): array
    {
        $membership = $player->user?->leagueMemberships
            ?->first(fn (LeagueMembership $membership): bool => $membership->league_id === $league->id);
        $nameParts = UserName::fromFullName($player->display_name);
        $role = $membership?->role ?? LeagueMembershipRole::Member;

        return [
            'id' => $player->id,
            'name' => $player->display_name,
            'jersey_number' => $player->jersey_number,
            'first_name' => $player->user?->first_name ?? $nameParts['first_name'],
            'last_name' => $player->user?->last_name ?? $nameParts['last_name'],
            'document_id' => $player->user?->document_id,
            'phone' => $player->user?->phone,
            'email' => $player->user?->email,
            'address' => $player->user?->address,
            'account_role' => $role === LeagueMembershipRole::Admin
                ? AccountRole::LeagueAdmin->value
                : AccountRole::Member->value,
            'invitation_pending' => $player->user?->hasPendingInvitation() ?? false,
        ];
    }

    private function resolveRosterRole(League $league, LeaguePlayer $player): LeagueMembershipRole
    {
        return $player->user?->leagueMemberships
            ?->first(fn (LeagueMembership $membership): bool => $membership->league_id === $league->id)
            ?->role
            ?? LeagueMembershipRole::Member;
    }
}

<?php

namespace Tests\Feature;

use App\Models\League;
use App\Models\LeaguePlayer;
use App\Models\User;
use App\Services\LeagueOperations\LeagueManagementService;
use App\Services\LeagueOperations\LeagueOperationsService;
use Carbon\CarbonImmutable;
use Database\Factories\LeagueMembershipFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeagueManagementPaymentAllocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_for_current_cut_settles_previous_debt_before_creating_credit(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-20 10:00:00'));

        try {
        $league = League::factory()->create();
        $admin = User::factory()->leagueAdmin()->create([
            'active_league_id' => $league->id,
        ]);

        LeagueMembershipFactory::new()->admin()->create([
            'league_id' => $league->id,
            'user_id' => $admin->id,
        ]);

        $player = LeaguePlayer::factory()->for($league)->create([
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);

        $operations = app(LeagueOperationsService::class);
        $management = app(LeagueManagementService::class);

        $league->cutConfigurations()->create([
            'sessions_limit' => 4,
            'game_days' => ['Sabado'],
            'cut_day' => 15,
            'effective_from' => CarbonImmutable::now()->subMonth()->startOfMonth()->toDateString(),
            'created_by_user_id' => $admin->id,
        ]);

        $previousCut = $operations->activeCutForLeague($league, CarbonImmutable::parse('2026-03-20 10:00:00'));
        $currentCut = $operations->activeCutForLeague($league);

        $management->recordPayment($admin, $player, 120000, false, $currentCut->id);

        $previousBalance = $operations->balanceForPlayer($previousCut, $player);
        $currentBalance = $operations->balanceForPlayer($currentCut, $player);

        $this->assertSame('paid', $previousBalance->status);
        $this->assertSame(60000, $previousBalance->amount_paid_cents);
        $this->assertSame('paid', $currentBalance->status);
        $this->assertSame(60000, $currentBalance->amount_paid_cents);
        $this->assertSame(0, $currentBalance->extra_credit_cents);
        $this->assertSame(0, $operations->previousDebtAmount($player, $currentCut));
        $this->assertSame(120000, $operations->cashIncomeForCut($currentCut));
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}

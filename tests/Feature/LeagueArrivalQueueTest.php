<?php

namespace Tests\Feature;

use App\Models\League;
use App\Models\LeaguePlayer;
use App\Models\User;
use App\Services\LeagueOperations\LeagueArrivalService;
use App\Services\LeagueOperations\LeagueManagementService;
use App\Services\LeagueOperations\LeagueOperationsService;
use Carbon\CarbonImmutable;
use Database\Factories\LeagueMembershipFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class LeagueArrivalQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_unpaid_members_lose_priority_when_the_cut_is_past_due(): void
    {
        [$league, $admin, $players] = $this->makeLeagueContext();
        $operations = app(LeagueOperationsService::class);
        $management = app(LeagueManagementService::class);
        $arrival = app(LeagueArrivalService::class);

        $league->cutConfigurations()->create([
            'sessions_limit' => 4,
            'game_days' => ['Sabado'],
            'cut_day' => CarbonImmutable::now()->day,
            'effective_from' => now()->startOfMonth()->toDateString(),
            'created_by_user_id' => $admin->id,
        ]);

        $cut = $operations->activeCutForLeague($league);

        foreach ($players->take(10) as $player) {
            $management->recordPayment($admin, $player, 60000, false, $cut->id);
            $arrival->togglePlayerArrival($admin, $player);
        }

        $unpaidPlayer = $players->last();
        $arrival->togglePlayerArrival($admin, $unpaidPlayer, false);
        $arrival->prepareSession($admin);

        $session = $operations->currentSessionForLeague($league, $cut, false);

        $this->assertNotNull($session);
        $this->assertSame('prepared', $session->status);
        $this->assertNotContains($unpaidPlayer->display_name, array_column($session->initial_pool, 'name'));
        $this->assertSame($unpaidPlayer->display_name, $session->initial_queue[0]['name']);
    }

    public function test_unpaid_members_keep_arrival_priority_before_cut_due_date(): void
    {
        [$league, $admin, $players] = $this->makeLeagueContext();
        $operations = app(LeagueOperationsService::class);
        $management = app(LeagueManagementService::class);
        $arrival = app(LeagueArrivalService::class);

        $league->cutConfigurations()->create([
            'sessions_limit' => 4,
            'game_days' => ['Sabado'],
            'cut_day' => CarbonImmutable::now()->addDay()->day,
            'effective_from' => now()->startOfMonth()->toDateString(),
            'created_by_user_id' => $admin->id,
        ]);

        $cut = $operations->activeCutForLeague($league);

        foreach ($players->take(9) as $player) {
            $management->recordPayment($admin, $player, 60000, false, $cut->id);
            $arrival->togglePlayerArrival($admin, $player);
        }

        $unpaidPriorityPlayer = $players->get(9);
        $lastPaidPlayer = $players->get(10);

        $arrival->togglePlayerArrival($admin, $unpaidPriorityPlayer, false);
        $management->recordPayment($admin, $lastPaidPlayer, 60000, false, $cut->id);
        $arrival->togglePlayerArrival($admin, $lastPaidPlayer);
        $arrival->prepareSession($admin);

        $session = $operations->currentSessionForLeague($league, $cut, false);

        $this->assertNotNull($session);
        $this->assertContains($unpaidPriorityPlayer->display_name, array_column($session->initial_pool, 'name'));
    }

    /**
     * @return array{0: League, 1: User, 2: Collection<int, LeaguePlayer>}
     */
    private function makeLeagueContext(): array
    {
        $league = League::factory()->create();
        $admin = User::factory()->leagueAdmin()->create([
            'active_league_id' => $league->id,
        ]);

        LeagueMembershipFactory::new()->admin()->create([
            'league_id' => $league->id,
            'user_id' => $admin->id,
        ]);

        $players = LeaguePlayer::factory()
            ->count(11)
            ->for($league)
            ->create([
                'created_by_user_id' => $admin->id,
                'updated_by_user_id' => $admin->id,
            ]);

        return [$league, $admin, $players];
    }
}

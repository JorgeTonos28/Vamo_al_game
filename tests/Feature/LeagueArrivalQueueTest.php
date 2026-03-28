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

    public function test_paid_guests_count_towards_the_minimum_players_required_to_start(): void
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

        foreach ($players->take(8) as $player) {
            $management->recordPayment($admin, $player, 60000, false, $cut->id);
            $arrival->togglePlayerArrival($admin, $player);
        }

        $arrival->storeGuest($admin, 'Invitado 1');
        $arrival->storeGuest($admin, 'Invitado 2');

        $session = $operations->currentSessionForLeague($league, $cut, false);

        $this->assertNotNull($session);

        $guestEntries = $session->entries()
            ->where('entry_type', 'guest')
            ->orderBy('arrival_order')
            ->get();

        $arrival->updateGuestPayment($admin, $guestEntries[0], true);
        $arrival->updateGuestPayment($admin, $guestEntries[1], true);
        $arrival->prepareSession($admin);

        $session = $operations->currentSessionForLeague($league, $cut, false);

        $this->assertNotNull($session);
        $this->assertSame('prepared', $session->status);
        $this->assertCount(10, $session->initial_pool);
        $this->assertContains('Invitado 1', array_column($session->initial_pool, 'name'));
        $this->assertContains('Invitado 2', array_column($session->initial_pool, 'name'));
    }

    public function test_arrived_members_are_listed_by_arrival_order_while_pending_members_remain_alphabetical(): void
    {
        [$league, $admin, $players] = $this->makeLeagueContext();
        $arrival = app(LeagueArrivalService::class);

        $league->cutConfigurations()->create([
            'sessions_limit' => 4,
            'game_days' => ['Sabado'],
            'cut_day' => CarbonImmutable::now()->addDay()->day,
            'effective_from' => now()->startOfMonth()->toDateString(),
            'created_by_user_id' => $admin->id,
        ]);

        $players[0]->update(['display_name' => 'Carlos']);
        $players[1]->update(['display_name' => 'Alberto']);
        $players[2]->update(['display_name' => 'Beisbol']);
        $players[3]->update(['display_name' => 'Zorro']);
        foreach ($players->slice(4)->values() as $index => $player) {
            $player->update(['display_name' => sprintf('Zulu %02d', $index + 1)]);
        }

        $arrival->togglePlayerArrival($admin, $players[0], false);
        $arrival->togglePlayerArrival($admin, $players[2], false);

        $playerNames = array_column($arrival->pageData($admin)['players'], 'name');

        $this->assertSame('Carlos', $playerNames[0]);
        $this->assertSame('Beisbol', $playerNames[1]);
        $this->assertSame('Alberto', $playerNames[2]);
        $this->assertContains('Zorro', $playerNames);
    }

    public function test_league_admin_players_can_arrive_and_count_for_the_session_once_paid(): void
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

        $adminPlayer = LeaguePlayer::factory()->for($league)->create([
            'user_id' => $admin->id,
            'display_name' => 'Admin en cancha',
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
            'status' => 'active',
        ]);

        $cut = $operations->activeCutForLeague($league);

        foreach ($players->take(9) as $player) {
            $management->recordPayment($admin, $player, 60000, false, $cut->id);
            $arrival->togglePlayerArrival($admin, $player);
        }

        $management->recordPayment($admin, $adminPlayer, 60000, false, $cut->id);
        $arrival->togglePlayerArrival($admin, $adminPlayer);

        $balance = $operations->balanceForPlayer($cut, $adminPlayer);
        $pageData = $arrival->pageData($admin);

        $this->assertSame('paid', $balance->status);
        $this->assertTrue(
            collect($pageData['players'])
                ->contains(fn (array $player): bool => $player['id'] === $adminPlayer->id && $player['current_cut_paid'] === true && $player['has_arrived'] === true),
        );

        $arrival->prepareSession($admin);

        $session = $operations->currentSessionForLeague($league, $cut, false);

        $this->assertNotNull($session);
        $this->assertContains('Admin en cancha', array_column($session->initial_pool, 'name'));
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

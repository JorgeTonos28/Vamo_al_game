<?php

namespace Tests\Feature\Api\V1;

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

class LeagueCompetitionModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_view_competition_modules_but_cannot_execute_admin_actions(): void
    {
        [$league, $admin, $players] = $this->makeLeagueContext();
        $member = User::factory()->memberRole()->create([
            'active_league_id' => $league->id,
        ]);

        LeagueMembershipFactory::new()->member()->create([
            'league_id' => $league->id,
            'user_id' => $member->id,
        ]);

        $this->prepareLeagueSession($league, $admin, $players->take(10));

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/v1/league/modules/game')
            ->assertOk();

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/v1/league/modules/scout')
            ->assertOk();

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/v1/league/modules/game/draft', [
                'mode' => 'arrival',
            ])
            ->assertForbidden();

        $this->actingAs($member, 'sanctum')
            ->patchJson("/api/v1/league/modules/scout/players/{$players->first()->id}", [
                'position' => 'Base',
                'role' => 'Equilibrado',
                'offensive_consistency' => 'Constante',
                'speed_rating' => 3,
                'dribbling_rating' => 3,
                'scoring_rating' => 3,
                'team_play_rating' => 3,
                'court_knowledge_rating' => 3,
                'defense_rating' => 3,
                'triples_rating' => 3,
            ])
            ->assertForbidden();
    }

    public function test_guest_membership_cannot_access_operational_modules(): void
    {
        $league = League::factory()->create();
        $guest = User::factory()->guestRole()->create([
            'active_league_id' => $league->id,
        ]);

        LeagueMembershipFactory::new()->guest()->create([
            'league_id' => $league->id,
            'user_id' => $guest->id,
        ]);

        $this->actingAs($guest, 'sanctum')
            ->getJson('/api/v1/league/modules/game')
            ->assertForbidden();
    }

    public function test_admin_can_start_a_game_from_the_prepared_pool(): void
    {
        [$league, $admin, $players] = $this->makeLeagueContext();
        $this->prepareLeagueSession($league, $admin, $players->take(10));

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/league/modules/game/draft', [
                'mode' => 'arrival',
            ])
            ->assertOk()
            ->assertJsonPath('data.game.state', 'live')
            ->assertJsonCount(5, 'data.game.current.team_a')
            ->assertJsonCount(5, 'data.game.current.team_b');
    }

    public function test_finishing_a_game_returns_the_rotation_notice_payload(): void
    {
        [$league, $admin, $players] = $this->makeLeagueContext();
        $this->prepareLeagueSession($league, $admin, $players->take(10));

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/league/modules/game/draft', [
                'mode' => 'arrival',
            ])
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/league/modules/game/team-point', [
                'team_side' => 'A',
            ])
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/league/modules/game/finish')
            ->assertOk()
            ->assertJsonPath('data.game.rotation_notice.title', 'Eq. A gana')
            ->assertJsonPath('data.game.rotation_notice.icon', 'rotate')
            ->assertJsonPath('data.game.rotation_notice.body.0', 'Eq. A se queda completo en cancha.');
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
            ->count(10)
            ->for($league)
            ->create([
                'created_by_user_id' => $admin->id,
                'updated_by_user_id' => $admin->id,
            ]);

        return [$league, $admin, $players];
    }

    /**
     * @param  Collection<int, LeaguePlayer>  $players
     */
    private function prepareLeagueSession(League $league, User $admin, Collection $players): void
    {
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

        foreach ($players as $player) {
            $management->recordPayment($admin, $player, 60000, false, $cut->id);
            $arrival->togglePlayerArrival($admin, $player);
        }

        $arrival->prepareSession($admin);
    }
}

<?php

namespace Tests\Feature\Api\V1;

use App\Models\League;
use App\Models\LeaguePlayer;
use App\Models\LeagueSession;
use App\Models\LeagueSessionEntry;
use App\Models\User;
use App\Services\LeagueOperations\LeagueArrivalService;
use App\Services\LeagueOperations\LeagueManagementService;
use App\Services\LeagueOperations\LeagueOperationsService;
use App\Services\LeagueOperations\LeagueSeasonService;
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

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/league/modules/game/draft', [
                'mode' => 'arrival',
            ])
            ->assertOk()
            ->assertJsonPath('data.game.state', 'live')
            ->assertJsonCount(5, 'data.game.current.team_a')
            ->assertJsonCount(5, 'data.game.current.team_b');

        $teamA = collect($response->json('data.game.current.team_a'));
        $teamB = collect($response->json('data.game.current.team_b'));

        $this->assertSame(1, $teamA->where('is_captain', true)->count());
        $this->assertSame(1, $teamB->where('is_captain', true)->count());
        $this->assertTrue((bool) $teamA->first()['is_captain']);
        $this->assertTrue((bool) $teamB->first()['is_captain']);
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

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/league/modules/game/finish')
            ->assertOk()
            ->assertJsonPath('data.game.rotation_notice.title', 'Eq. A gana')
            ->assertJsonPath('data.game.rotation_notice.icon', 'rotate')
            ->assertJsonPath('data.game.rotation_notice.body.0', 'Eq. A se queda completo en cancha.');

        $body = $response->json('data.game.rotation_notice.body');

        $this->assertIsArray($body);
        $this->assertStringNotContainsString('legacy', implode(' ', $body));
    }

    public function test_guest_losers_return_to_the_end_of_the_queue_after_finishing_a_game(): void
    {
        [$league, $admin, $players] = $this->makeLeagueContext();
        $this->prepareLeagueSession($league, $admin, $players->take(10));

        $session = $league->sessions()->with('entries.player')->latest('id')->firstOrFail();
        $playerEntries = $session->entries->where('entry_type', 'player')->sortBy('arrival_order')->values();

        $removedEntries = $playerEntries->slice(-2)->values();
        foreach ($removedEntries as $entry) {
            $entry->forceFill([
                'session_state' => 'removed',
                'team_side' => null,
                'queue_position' => null,
            ])->save();
        }

        $guestA = $session->entries()->create([
            'guest_name' => 'Invitado A',
            'entry_type' => 'guest',
            'arrival_order' => 11,
            'guest_fee_paid' => true,
            'current_cut_paid' => false,
            'was_marked_paid_on_arrival' => true,
            'priority_bucket' => 'guest',
            'queue_seed' => 11,
            'session_state' => 'pool',
        ]);
        $guestB = $session->entries()->create([
            'guest_name' => 'Invitado B',
            'entry_type' => 'guest',
            'arrival_order' => 12,
            'guest_fee_paid' => true,
            'current_cut_paid' => false,
            'was_marked_paid_on_arrival' => true,
            'priority_bucket' => 'guest',
            'queue_seed' => 12,
            'session_state' => 'pool',
        ]);

        $benchPlayers = LeaguePlayer::factory()
            ->count(7)
            ->for($league)
            ->create([
                'created_by_user_id' => $admin->id,
                'updated_by_user_id' => $admin->id,
            ]);

        foreach ($benchPlayers as $index => $player) {
            $session->entries()->create([
                'league_player_id' => $player->id,
                'entry_type' => 'player',
                'arrival_order' => 13 + $index,
                'current_cut_paid' => true,
                'guest_fee_paid' => false,
                'was_marked_paid_on_arrival' => false,
                'priority_bucket' => 'member',
                'queue_seed' => 13 + $index,
                'session_state' => 'queued',
                'queue_position' => $index + 1,
            ]);
        }

        $poolEntries = $session->fresh('entries')->entries
            ->where('session_state', 'pool')
            ->sortBy('arrival_order')
            ->values();

        $assignments = [];
        foreach ($poolEntries->take(5) as $entry) {
            $assignments[$entry->id] = 'A';
        }
        foreach ($poolEntries->slice(5, 3) as $entry) {
            $assignments[$entry->id] = 'B';
        }
        $assignments[$guestA->id] = 'B';
        $assignments[$guestB->id] = 'B';

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/league/modules/game/draft', [
                'mode' => 'manual',
                'assignments' => $assignments,
            ])
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/league/modules/game/team-point', [
                'team_side' => 'A',
            ])
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/league/modules/game/finish')
            ->assertOk();

        $queuedGuests = $session->fresh('entries')->entries
            ->where('entry_type', 'guest')
            ->sortBy('queue_position')
            ->values();
        $queueCount = $session->fresh('entries')->entries
            ->where('session_state', 'queued')
            ->count();

        $this->assertCount(2, $queuedGuests);
        $this->assertSame('queued', $queuedGuests[0]->session_state);
        $this->assertSame('queued', $queuedGuests[1]->session_state);
        $this->assertNull($queuedGuests[0]->team_side);
        $this->assertNull($queuedGuests[1]->team_side);
        $this->assertSame($queueCount - 1, $queuedGuests[0]->queue_position);
        $this->assertSame($queueCount, $queuedGuests[1]->queue_position);
    }

    public function test_admin_can_fetch_scout_payload_with_legacy_stat_breakdown(): void
    {
        [$league, $admin, $players] = $this->makeLeagueContext();
        $this->prepareLeagueSession($league, $admin, $players->take(10));

        $session = $league->sessions()->with('entries.player')->latest('id')->firstOrFail();
        $focusPlayer = $players->firstOrFail();

        $this->createScoutProfile($focusPlayer, $admin, 3, 'Anotador');
        $this->seedScoutSeasonHistory($session, $admin, $players);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/league/modules/scout')
            ->assertOk();

        $row = collect($response->json('data.scout.players'))
            ->firstWhere('player.id', $focusPlayer->id);

        $this->assertNotNull($row);
        $this->assertSame(true, $row['has_stats']);
        $this->assertEquals(3.0, $row['manual_rating']);
        $this->assertEquals(3.3, $row['combined_rating']);
        $this->assertEquals(5.0, $row['stat_rating']['victories']);
        $this->assertEquals(5.0, $row['stat_rating']['scoring']);
        $this->assertEquals(5.0, $row['stat_rating']['defense']);
        $this->assertEquals(5.0, $row['stat_rating']['triples']);
        $this->assertEquals(0.0, $row['stat_rating']['diversity']);
        $this->assertEquals(4.3, $row['stat_rating']['overall']);
        $this->assertEquals(9.0, $row['stat_rating']['detail']['points_per_game']);
        $this->assertEquals(100, $row['stat_rating']['detail']['win_rate']);
        $this->assertEquals(10.3, $row['stat_rating']['detail']['points_allowed_per_game']);
        $this->assertEquals(100, $row['stat_rating']['detail']['triple_rate']);
        $this->assertEquals(0, $row['stat_rating']['detail']['diversity']);
    }

    public function test_admin_can_start_an_auto_draft_from_scout_ratings(): void
    {
        [$league, $admin, $players] = $this->makeLeagueContext();
        $this->prepareLeagueSession($league, $admin, $players->take(10));

        $ratings = [5, 1, 5, 1, 5, 1, 5, 1, 5, 1];

        foreach ($players as $index => $player) {
            $this->createScoutProfile($player, $admin, $ratings[$index] ?? 1, 'Equilibrado');
        }

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/league/modules/game/draft', [
                'mode' => 'auto',
            ])
            ->assertOk()
            ->assertJsonPath('data.game.state', 'live');

        $teamA = collect($response->json('data.game.current.team_a'))->pluck('name')->values()->all();
        $teamB = collect($response->json('data.game.current.team_b'))->pluck('name')->values()->all();

        $this->assertEqualsCanonicalizing([
            $players[0]->display_name,
            $players[4]->display_name,
            $players[8]->display_name,
            $players[7]->display_name,
            $players[9]->display_name,
        ], $teamA);
        $this->assertEqualsCanonicalizing([
            $players[2]->display_name,
            $players[6]->display_name,
            $players[1]->display_name,
            $players[3]->display_name,
            $players[5]->display_name,
        ], $teamB);
    }

    public function test_clock_can_only_be_reconfigured_when_it_has_been_reset(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-05 10:00:00'));

        try {
            [$league, $admin, $players] = $this->makeLeagueContext();
            $this->prepareLeagueSession($league, $admin, $players->take(10));

            $this->actingAs($admin, 'sanctum')
                ->postJson('/api/v1/league/modules/game/draft', [
                    'mode' => 'arrival',
                ])
                ->assertOk();

            $this->actingAs($admin, 'sanctum')
                ->postJson('/api/v1/league/modules/game/clock', [
                    'duration_seconds' => 1200,
                ])
                ->assertOk();

            $this->actingAs($admin, 'sanctum')
                ->postJson('/api/v1/league/modules/game/clock/start')
                ->assertOk();

            CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-05 10:00:05'));

            $this->actingAs($admin, 'sanctum')
                ->postJson('/api/v1/league/modules/game/clock/pause')
                ->assertOk();

            $this->actingAs($admin, 'sanctum')
                ->postJson('/api/v1/league/modules/game/clock', [
                    'duration_seconds' => 900,
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['clock']);

            $this->actingAs($admin, 'sanctum')
                ->postJson('/api/v1/league/modules/game/clock/reset')
                ->assertOk();

            $this->actingAs($admin, 'sanctum')
                ->postJson('/api/v1/league/modules/game/clock', [
                    'duration_seconds' => 900,
                ])
                ->assertOk()
                ->assertJsonPath('data.game.clock.duration_seconds', 900)
                ->assertJsonPath('data.game.clock.remaining_seconds', 900)
                ->assertJsonPath('data.game.clock.state', 'paused');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_previous_day_open_session_is_auto_closed_and_modules_reset_for_today(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-05 10:00:00'));

        try {
            [$league, $admin, $players] = $this->makeLeagueContext();
            $this->prepareLeagueSession($league, $admin, $players->take(10));

            $this->actingAs($admin, 'sanctum')
                ->postJson('/api/v1/league/modules/game/draft', [
                    'mode' => 'arrival',
                ])
                ->assertOk();

            $session = $league->sessions()->latest('id')->firstOrFail();
            $this->assertSame('in_progress', $session->status);

            CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-06 09:00:00'));

            $this->actingAs($admin, 'sanctum')
                ->getJson('/api/v1/league/modules/game')
                ->assertOk()
                ->assertJsonPath('data.session.id', null)
                ->assertJsonPath('data.session.status', 'idle')
                ->assertJsonPath('data.game.state', 'idle')
                ->assertJsonPath('data.game.current', null);

            $this->actingAs($admin, 'sanctum')
                ->getJson('/api/v1/league/modules/queue')
                ->assertOk()
                ->assertJsonPath('data.session.id', null)
                ->assertJsonPath('data.session.status', 'idle')
                ->assertJsonPath('data.queue.live_game', null)
                ->assertJsonCount(0, 'data.queue.waiting')
                ->assertJsonCount(0, 'data.queue.on_court');

            $session->refresh();

            $this->assertSame('completed', $session->status);
            $this->assertNull($session->clock_started_at);
            $this->assertSame(0, $session->games()->where('status', '!=', 'completed')->count());
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_previous_day_scored_open_game_is_preserved_when_session_auto_closes(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-05 10:00:00'));

        try {
            [$league, $admin, $players] = $this->makeLeagueContext();
            $this->prepareLeagueSession($league, $admin, $players->take(10));

            $this->actingAs($admin, 'sanctum')
                ->postJson('/api/v1/league/modules/game/draft', [
                    'mode' => 'arrival',
                ])
                ->assertOk();

            $session = $league->sessions()->with('entries.player', 'games')->latest('id')->firstOrFail();
            $scorer = $session->entries
                ->where('session_state', 'on_court')
                ->where('team_side', 'A')
                ->sortBy('arrival_order')
                ->firstOrFail();

            $this->actingAs($admin, 'sanctum')
                ->postJson("/api/v1/league/modules/game/players/{$scorer->id}/point", [
                    'points' => 2,
                ])
                ->assertOk();

            CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-06 09:00:00'));

            $this->actingAs($admin, 'sanctum')
                ->getJson('/api/v1/league/modules/game')
                ->assertOk()
                ->assertJsonPath('data.session.id', null)
                ->assertJsonPath('data.game.state', 'idle');

            $session->refresh();
            $game = $session->games()->firstOrFail();

            $this->assertSame('completed', $session->status);
            $this->assertSame('completed', $game->status);
            $this->assertSame(2, $game->team_a_score);
            $this->assertSame(0, $game->team_b_score);
            $this->assertSame('A', $game->winner_side);
            $this->assertSame(2, (int) (($game->player_points ?? [])[(string) $scorer->id] ?? 0));
            $this->assertSame(1, (int) (($game->player_shots ?? [])[(string) $scorer->id]['2'] ?? 0));
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_previous_day_tied_open_game_is_auto_closed_without_affecting_standings(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-05 10:00:00'));

        try {
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
                ->postJson('/api/v1/league/modules/game/team-point', [
                    'team_side' => 'B',
                ])
                ->assertOk();

            $session = $league->sessions()->with('games')->latest('id')->firstOrFail();

            CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-06 09:00:00'));

            $this->actingAs($admin, 'sanctum')
                ->getJson('/api/v1/league/modules/game')
                ->assertOk()
                ->assertJsonPath('data.session.id', null)
                ->assertJsonPath('data.game.state', 'idle');

            $session->refresh();
            $game = $session->games()->firstOrFail();

            $this->assertSame('completed', $session->status);
            $this->assertSame('abandoned', $game->status);
            $this->assertNull($game->winner_side);
            $this->assertSame(1, $game->team_a_score);
            $this->assertSame(1, $game->team_b_score);

            $response = $this->actingAs($admin, 'sanctum')
                ->getJson("/api/v1/league/modules/table?session_id={$session->id}")
                ->assertOk()
                ->assertJsonPath('data.session_selector.selected_session_id', $session->id)
                ->assertJsonPath('data.table.banner.games', 0)
                ->assertJsonCount(0, 'data.table.standings');

            $selectedSession = collect($response->json('data.session_selector.sessions'))
                ->firstWhere('id', $session->id);

            $this->assertNotNull($selectedSession);
            $this->assertSame(0, $selectedSession['completed_games_count']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_admin_can_yield_a_live_turn_to_a_selected_player_from_queue(): void
    {
        [$league, $admin, $players] = $this->makeLeagueContext();
        $this->prepareLeagueSession($league, $admin, $players->take(10));
        $this->appendBenchPlayersToSession($league, $admin, 3);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/league/modules/game/draft', [
                'mode' => 'arrival',
            ])
            ->assertOk();

        $session = $league->sessions()->with('entries.player', 'games')->latest('id')->firstOrFail();
        $outgoing = $session->entries
            ->where('session_state', 'on_court')
            ->where('team_side', 'A')
            ->sortBy('arrival_order')
            ->firstOrFail();
        $queuedEntries = $session->entries
            ->where('session_state', 'queued')
            ->sortBy('queue_position')
            ->values();
        $selectedReplacement = $queuedEntries->get(1);

        $this->assertNotNull($selectedReplacement);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/league/modules/game/players/{$outgoing->id}/point", [
                'points' => 2,
            ])
            ->assertOk();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/league/modules/game/players/{$outgoing->id}/remove", [
                'action' => 'yield',
                'replacement_entry_id' => $selectedReplacement->id,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Turno cedido al jugador seleccionado.')
            ->assertJsonPath('data.game.current.score.team_a', 2);

        $session = $session->fresh('entries.player', 'games');
        $outgoing = $session->entries->firstWhere('id', $outgoing->id);
        $selectedReplacement = $session->entries->firstWhere('id', $selectedReplacement->id);
        $openGame = $session->games->firstWhere('status', 'open');

        $this->assertSame('queued', $outgoing->session_state);
        $this->assertNull($outgoing->team_side);
        $this->assertSame(2, $outgoing->queue_position);
        $this->assertSame('on_court', $selectedReplacement->session_state);
        $this->assertSame('A', $selectedReplacement->team_side);
        $this->assertSame(2, $openGame->team_a_score);
        $this->assertSame(2, (int) (($openGame->player_points ?? [])[(string) $outgoing->id] ?? 0));

        $teamASnapshot = collect($openGame->team_a_snapshot ?? []);
        $outgoingSnapshot = $teamASnapshot->firstWhere('entry_id', $outgoing->id);
        $incomingSnapshot = $teamASnapshot->firstWhere('entry_id', $selectedReplacement->id);

        $this->assertNotNull($outgoingSnapshot);
        $this->assertNotNull($incomingSnapshot);
        $this->assertFalse((bool) ($outgoingSnapshot['result_counts'] ?? true));
        $this->assertTrue((bool) ($incomingSnapshot['result_counts'] ?? false));
        $this->assertSame(
            [$queuedEntries->first()->id, $outgoing->id, $queuedEntries->last()->id],
            $session->entries
                ->where('session_state', 'queued')
                ->sortBy('queue_position')
                ->pluck('id')
                ->values()
                ->all(),
        );

        $yieldedRow = collect($response->json('data.game.current.team_a'))
            ->firstWhere('id', $selectedReplacement->id);
        $waitingRow = collect($response->json('data.game.current.waiting_queue'))
            ->firstWhere('id', $outgoing->id);

        $this->assertNotNull($yieldedRow);
        $this->assertTrue((bool) $yieldedRow['can_yield_turn']);
        $this->assertNotNull($waitingRow);
        $this->assertSame(2, $waitingRow['position']);
    }

    public function test_removed_player_keeps_points_but_the_game_does_not_count_for_wins_or_losses(): void
    {
        [$league, $admin, $players] = $this->makeLeagueContext();
        $this->prepareLeagueSession($league, $admin, $players->take(10));
        $benchPlayers = $this->appendBenchPlayersToSession($league, $admin, 2);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/league/modules/game/draft', [
                'mode' => 'arrival',
            ])
            ->assertOk();

        $session = $league->sessions()->with('entries.player', 'games')->latest('id')->firstOrFail();
        $outgoing = $session->entries
            ->where('session_state', 'on_court')
            ->where('team_side', 'A')
            ->sortBy('arrival_order')
            ->firstOrFail();
        $replacement = $session->entries
            ->where('session_state', 'queued')
            ->sortBy('queue_position')
            ->firstOrFail();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/league/modules/game/players/{$outgoing->id}/point", [
                'points' => 2,
            ])
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/league/modules/game/players/{$outgoing->id}/remove", [
                'action' => 'remove',
            ])
            ->assertOk()
            ->assertJsonPath('data.game.current.score.team_a', 2);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/league/modules/game/team-point', [
                'team_side' => 'A',
            ])
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/league/modules/game/finish')
            ->assertOk();

        $statsResponse = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/league/modules/stats')
            ->assertOk();

        $pointsRow = collect($statsResponse->json('data.stats.points_leaders'))
            ->firstWhere('identity.name', $outgoing->player?->display_name);
        $gamesRow = collect($statsResponse->json('data.stats.games_leaders'))
            ->firstWhere('identity.name', $outgoing->player?->display_name);
        $replacementRow = collect($statsResponse->json('data.stats.games_leaders'))
            ->firstWhere('identity.name', $benchPlayers->firstOrFail()->display_name);

        $this->assertNotNull($pointsRow);
        $this->assertSame(2, $pointsRow['points']);
        $this->assertSame(0, $pointsRow['games']);
        $this->assertNull($gamesRow);
        $this->assertNotNull($replacementRow);
        $this->assertSame(1, $replacementRow['games']);
        $this->assertSame(1, $replacementRow['wins']);
        $this->assertSame(0, $replacementRow['losses']);

        $session = $session->fresh('entries.player', 'games');
        $outgoing = $session->entries->firstWhere('id', $outgoing->id);
        $replacement = $session->entries->firstWhere('id', $replacement->id);
        $completedGame = $session->games->firstWhere('status', 'completed');

        $this->assertSame('removed', $outgoing->session_state);
        $this->assertSame('on_court', $replacement->session_state);
        $this->assertSame(3, $completedGame->team_a_score);
        $this->assertSame(2, (int) (($completedGame->player_points ?? [])[(string) $outgoing->id] ?? 0));
        $this->assertFalse((bool) (collect($completedGame->team_a_snapshot ?? [])->firstWhere('entry_id', $outgoing->id)['result_counts'] ?? true));
    }

    public function test_game_module_can_switch_into_abandoned_review_mode(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-05 10:00:00'));

        try {
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
                ->postJson('/api/v1/league/modules/game/team-point', [
                    'team_side' => 'B',
                ])
                ->assertOk();

            $session = $league->sessions()->with('games')->latest('id')->firstOrFail();

            CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-06 09:00:00'));

            $overview = $this->actingAs($admin, 'sanctum')
                ->getJson('/api/v1/league/modules/game')
                ->assertOk()
                ->assertJsonPath('data.game.state', 'idle')
                ->assertJsonCount(1, 'data.game.abandoned_games');

            $gameId = (int) $overview->json('data.game.abandoned_games.0.id');

            $this->actingAs($admin, 'sanctum')
                ->getJson("/api/v1/league/modules/game?abandoned_game_id={$gameId}")
                ->assertOk()
                ->assertJsonPath('data.session.id', $session->id)
                ->assertJsonPath('data.game.state', 'review')
                ->assertJsonPath('data.game.review.is_active', true)
                ->assertJsonPath('data.game.review.selected_abandoned_game_id', $gameId)
                ->assertJsonPath('data.game.current.id', $gameId)
                ->assertJsonPath('data.game.current.score.team_a', 1)
                ->assertJsonPath('data.game.current.score.team_b', 1)
                ->assertJsonCount(5, 'data.game.current.team_a')
                ->assertJsonCount(5, 'data.game.current.team_b')
                ->assertJsonCount(0, 'data.game.history');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_admin_can_resolve_an_abandoned_game_from_game_module(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-05 10:00:00'));

        try {
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
                ->postJson('/api/v1/league/modules/game/team-point', [
                    'team_side' => 'B',
                ])
                ->assertOk();

            $session = $league->sessions()->with('games')->latest('id')->firstOrFail();

            CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-06 09:00:00'));

            $this->actingAs($admin, 'sanctum')
                ->getJson('/api/v1/league/modules/game')
                ->assertOk();

            $game = $session->fresh('games')->games->firstOrFail();

            $this->assertSame('abandoned', $game->status);

            $this->actingAs($admin, 'sanctum')
                ->postJson("/api/v1/league/modules/game/abandoned/{$game->id}/resolve", [
                    'winner_side' => 'A',
                ])
                ->assertOk()
                ->assertJsonPath('message', 'Juego abandonado resuelto.')
                ->assertJsonPath('data.game.review.is_active', false)
                ->assertJsonCount(0, 'data.game.abandoned_games');

            $game->refresh();

            $this->assertSame('completed', $game->status);
            $this->assertSame('A', $game->winner_side);
            $this->assertSame($admin->id, $game->finished_by_user_id);

            $this->actingAs($admin, 'sanctum')
                ->getJson("/api/v1/league/modules/table?session_id={$session->id}")
                ->assertOk()
                ->assertJsonPath('data.table.banner.games', 1);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_game_module_falls_back_to_normal_view_when_selected_abandoned_game_is_already_resolved(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-05 10:00:00'));

        try {
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
                ->postJson('/api/v1/league/modules/game/team-point', [
                    'team_side' => 'B',
                ])
                ->assertOk();

            CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-06 09:00:00'));

            $overview = $this->actingAs($admin, 'sanctum')
                ->getJson('/api/v1/league/modules/game')
                ->assertOk()
                ->assertJsonPath('data.game.state', 'idle')
                ->assertJsonCount(1, 'data.game.abandoned_games');

            $gameId = (int) $overview->json('data.game.abandoned_games.0.id');

            $this->actingAs($admin, 'sanctum')
                ->postJson("/api/v1/league/modules/game/abandoned/{$gameId}/resolve", [
                    'winner_side' => 'A',
                ])
                ->assertOk()
                ->assertJsonPath('data.game.review.is_active', false);

            $this->actingAs($admin, 'sanctum')
                ->getJson("/api/v1/league/modules/game?abandoned_game_id={$gameId}")
                ->assertOk()
                ->assertJsonPath('data.game.state', 'idle')
                ->assertJsonPath('data.game.review.is_active', false)
                ->assertJsonPath('data.game.review.selected_abandoned_game_id', null)
                ->assertJsonCount(0, 'data.game.abandoned_games');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_member_cannot_resolve_an_abandoned_game(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-05 10:00:00'));

        try {
            [$league, $admin, $players] = $this->makeLeagueContext();
            $member = User::factory()->memberRole()->create([
                'active_league_id' => $league->id,
            ]);

            LeagueMembershipFactory::new()->member()->create([
                'league_id' => $league->id,
                'user_id' => $member->id,
            ]);

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
                ->postJson('/api/v1/league/modules/game/team-point', [
                    'team_side' => 'B',
                ])
                ->assertOk();

            $session = $league->sessions()->with('games')->latest('id')->firstOrFail();

            CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-06 09:00:00'));

            $this->actingAs($admin, 'sanctum')
                ->getJson('/api/v1/league/modules/game')
                ->assertOk();

            $game = $session->fresh('games')->games->firstOrFail();

            $this->actingAs($member, 'sanctum')
                ->postJson("/api/v1/league/modules/game/abandoned/{$game->id}/resolve", [
                    'winner_side' => 'A',
                ])
                ->assertForbidden();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_season_and_scout_keep_historical_context_when_today_has_no_session(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-05 10:00:00'));

        try {
            [$league, $admin, $players] = $this->makeLeagueContext();
            $this->prepareLeagueSession($league, $admin, $players->take(10));

            $session = $league->sessions()->with('entries.player')->latest('id')->firstOrFail();
            $season = app(LeagueSeasonService::class)->activeSeasonForLeague($league, $admin);

            $session->forceFill([
                'league_season_id' => $season->id,
            ])->save();

            $this->seedScoutSeasonHistory($session->fresh('entries.player'), $admin, $players);

            $session->forceFill([
                'status' => 'completed',
                'session_date' => '2026-04-05',
                'ended_at' => CarbonImmutable::parse('2026-04-05 22:00:00'),
            ])->save();

            CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-06 09:00:00'));

            $seasonResponse = $this->actingAs($admin, 'sanctum')
                ->getJson('/api/v1/league/modules/season')
                ->assertOk()
                ->assertJsonPath('data.session.id', null)
                ->assertJsonPath('data.season.season.id', $season->id)
                ->assertJsonPath('data.season.season.sessions_count', 1)
                ->assertJsonPath('data.season.season.totals.games', 3)
                ->assertJsonPath('data.season.season.totals.points', 79);

            $this->assertSame($season->id, $seasonResponse->json('data.season.season.id'));

            $scoutResponse = $this->actingAs($admin, 'sanctum')
                ->getJson('/api/v1/league/modules/scout')
                ->assertOk()
                ->assertJsonPath('data.session.id', null);

            $row = collect($scoutResponse->json('data.scout.players'))
                ->firstWhere('player.id', $players->firstOrFail()->id);

            $this->assertNotNull($row);
            $this->assertTrue((bool) $row['has_stats']);
            $this->assertGreaterThan(0, (float) $row['stat_rating']['overall']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_admin_can_delete_a_session_from_stats_endpoint(): void
    {
        [$league, $admin, $players] = $this->makeLeagueContext();
        $this->prepareLeagueSession($league, $admin, $players->take(10));

        $session = $league->sessions()->latest('id')->firstOrFail();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/league/modules/stats/sessions/{$session->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Jornada eliminada.')
            ->assertJsonPath('data.session.id', null)
            ->assertJsonCount(0, 'data.session_selector.sessions');

        $this->assertDatabaseMissing('league_sessions', [
            'id' => $session->id,
        ]);
    }

    public function test_table_endpoint_can_load_a_previous_session_from_the_selector(): void
    {
        [$league, $admin, $players] = $this->makeLeagueContext();
        $this->prepareLeagueSession($league, $admin, $players->take(10));

        $historicalSession = $league->sessions()->with('entries.player')->latest('id')->firstOrFail();
        $this->seedScoutSeasonHistory($historicalSession, $admin, $players);
        $historicalSession->forceFill([
            'status' => 'completed',
            'session_date' => now()->subDay()->toDateString(),
            'ended_at' => now()->subDay(),
        ])->save();

        $this->prepareLeagueSession($league, $admin, $players->take(10));

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/league/modules/table?session_id={$historicalSession->id}")
            ->assertOk()
            ->assertJsonPath('data.session_selector.selected_session_id', $historicalSession->id)
            ->assertJsonPath('data.table.banner.games', 3)
            ->assertJsonPath('data.table.banner.points', 79);
    }

    public function test_admin_can_reorder_the_pregame_queue_from_api(): void
    {
        [$league, $admin, $players] = $this->makeLeagueContext();
        $this->prepareLeagueSession($league, $admin, $players->take(10));

        $session = $league->sessions()->with('entries.player')->latest('id')->firstOrFail();
        $orderedIds = $session->entries
            ->where('session_state', 'pool')
            ->sortBy('arrival_order')
            ->pluck('id')
            ->reverse()
            ->values()
            ->all();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/league/arrival/queue/reorder', [
                'entry_ids' => $orderedIds,
            ])
            ->assertOk()
            ->assertJsonPath('data.queue_preview.entries.0.id', $orderedIds[0])
            ->assertJsonPath('data.queue_preview.entries.9.id', $orderedIds[9]);

        $preparedPoolIds = collect(
            $league->sessions()->latest('id')->firstOrFail()->fresh()->initial_pool
        )->pluck('id')->all();

        $this->assertSame($orderedIds, $preparedPoolIds);
    }

    public function test_live_queue_reorder_endpoint_is_rejected_and_queue_payload_marks_it_disabled(): void
    {
        [$league, $admin, $players] = $this->makeLeagueContext();
        $this->prepareLeagueSession($league, $admin, $players->take(10));

        $operations = app(LeagueOperationsService::class);
        $management = app(LeagueManagementService::class);
        $arrival = app(LeagueArrivalService::class);
        $cut = $operations->activeCutForLeague($league);

        $benchPlayers = LeaguePlayer::factory()
            ->count(3)
            ->for($league)
            ->create([
                'created_by_user_id' => $admin->id,
                'updated_by_user_id' => $admin->id,
            ]);

        foreach ($benchPlayers as $player) {
            $management->recordPayment($admin, $player, 60000, false, $cut->id);
            $arrival->togglePlayerArrival($admin, $player);
        }

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/league/modules/game/draft', [
                'mode' => 'arrival',
            ])
            ->assertOk();

        $session = $league->sessions()->with('entries.player')->latest('id')->firstOrFail();
        $reorderedIds = $session->entries
            ->where('session_state', 'queued')
            ->sortBy('queue_position')
            ->pluck('id')
            ->reverse()
            ->values()
            ->all();

        $queueResponse = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/league/modules/queue')
            ->assertOk()
            ->assertJsonPath('data.queue.can_reorder', false);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/league/modules/queue/reorder', [
                'entry_ids' => $reorderedIds,
            ])
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.session.0',
                'La cola operativa no se reordena desde este modulo. Usa Llegada antes del primer juego.',
            );

        $this->assertFalse($queueResponse->json('data.queue.can_reorder'));
    }

    public function test_game_payload_exposes_scout_preferred_position_in_draft_entries(): void
    {
        [$league, $admin, $players] = $this->makeLeagueContext();
        $this->prepareLeagueSession($league, $admin, $players->take(10));
        $this->createScoutProfile($players->firstOrFail(), $admin, 4, 'Equilibrado');

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/league/modules/game')
            ->assertOk();

        $entry = collect($response->json('data.game.draft.entries'))
            ->firstWhere('name', $players->firstOrFail()->display_name);

        $this->assertNotNull($entry);
        $this->assertSame('Base', $entry['preferred_position']);
    }

    public function test_admin_can_start_a_random_draft_with_manual_captains(): void
    {
        [$league, $admin, $players] = $this->makeLeagueContext();
        $this->prepareLeagueSession($league, $admin, $players->take(10));

        $session = $league->sessions()->with('entries.player')->latest('id')->firstOrFail();
        $teams = $this->expectedRandomDraftTeams($session);
        /** @var LeagueSessionEntry|null $captainA */
        $captainA = $teams['A']->last();
        /** @var LeagueSessionEntry|null $captainB */
        $captainB = $teams['B']->last();

        $this->assertNotNull($captainA);
        $this->assertNotNull($captainB);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/league/modules/game/draft', [
                'mode' => 'random',
                'captain_mode' => 'manual',
                'captains' => [
                    'A' => $captainA->id,
                    'B' => $captainB->id,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.game.state', 'live')
            ->assertJsonCount(5, 'data.game.current.team_a')
            ->assertJsonCount(5, 'data.game.current.team_b');

        $teamA = collect($response->json('data.game.current.team_a'));
        $teamB = collect($response->json('data.game.current.team_b'));

        $this->assertSame('random', $session->fresh('games')->games->firstWhere('status', 'open')?->draft_mode);
        $this->assertSame($captainA->id, $teamA->first()['id']);
        $this->assertSame($captainB->id, $teamB->first()['id']);
        $this->assertTrue((bool) $teamA->first()['is_captain']);
        $this->assertTrue((bool) $teamB->first()['is_captain']);
        $this->assertSame([$captainA->id], $teamA->where('is_captain', true)->pluck('id')->all());
        $this->assertSame([$captainB->id], $teamB->where('is_captain', true)->pluck('id')->all());
        $this->assertSame($this->expectedOrderedTeamIds($teams['A'], $captainA->id), $teamA->pluck('id')->all());
        $this->assertSame($this->expectedOrderedTeamIds($teams['B'], $captainB->id), $teamB->pluck('id')->all());
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

    private function createScoutProfile(LeaguePlayer $player, User $admin, int $rating, string $role): void
    {
        $player->scoutProfile()->create([
            'position' => 'Base',
            'role' => $role,
            'offensive_consistency' => 'Constante',
            'speed_rating' => $rating,
            'dribbling_rating' => $rating,
            'scoring_rating' => $rating,
            'team_play_rating' => $rating,
            'court_knowledge_rating' => $rating,
            'defense_rating' => $rating,
            'triples_rating' => $rating,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    /**
     * @param  Collection<int, LeaguePlayer>  $players
     */
    private function seedScoutSeasonHistory(LeagueSession $session, User $admin, Collection $players): void
    {
        $entries = $session->entries->keyBy('league_player_id');
        $teamA = $players->take(5)->map(fn (LeaguePlayer $player): LeagueSessionEntry => $entries->get($player->id));
        $teamB = $players->slice(5, 5)->map(fn (LeaguePlayer $player): LeagueSessionEntry => $entries->get($player->id));

        $games = [
            [
                'score_a' => 16,
                'score_b' => 10,
                'points' => [9, 2, 2, 2, 1, 3, 2, 2, 2, 1],
            ],
            [
                'score_a' => 16,
                'score_b' => 12,
                'points' => [9, 2, 2, 2, 1, 4, 3, 2, 2, 1],
            ],
            [
                'score_a' => 16,
                'score_b' => 9,
                'points' => [9, 2, 2, 2, 1, 2, 2, 2, 2, 1],
            ],
        ];

        foreach ($games as $index => $game) {
            $playerPoints = [];
            $playerShots = [];
            $pointList = $game['points'];

            foreach ($teamA->values() as $teamIndex => $entry) {
                $playerPoints[(string) $entry->id] = $pointList[$teamIndex];
                $playerShots[(string) $entry->id] = $teamIndex === 0
                    ? ['1' => 0, '2' => 0, '3' => 3]
                    : ['1' => $pointList[$teamIndex], '2' => 0, '3' => 0];
            }

            foreach ($teamB->values() as $teamIndex => $entry) {
                $playerPoints[(string) $entry->id] = $pointList[$teamIndex + 5];
                $playerShots[(string) $entry->id] = ['1' => $pointList[$teamIndex + 5], '2' => 0, '3' => 0];
            }

            $session->games()->create([
                'game_number' => $index + 1,
                'draft_mode' => 'auto',
                'status' => 'completed',
                'phase' => 'standard',
                'team_a_score' => $game['score_a'],
                'team_b_score' => $game['score_b'],
                'winner_side' => 'A',
                'team_a_snapshot' => $teamA->map(fn (LeagueSessionEntry $entry): array => $this->snapshotForEntry($entry))->all(),
                'team_b_snapshot' => $teamB->map(fn (LeagueSessionEntry $entry): array => $this->snapshotForEntry($entry))->all(),
                'player_points' => $playerPoints,
                'player_shots' => $playerShots,
                'started_at' => now()->subMinutes(30 - $index),
                'ended_at' => now()->subMinutes(29 - $index),
                'created_by_user_id' => $admin->id,
                'finished_by_user_id' => $admin->id,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotForEntry(LeagueSessionEntry $entry): array
    {
        return [
            'entry_id' => $entry->id,
            'player_id' => $entry->league_player_id,
            'name' => $entry->player?->display_name,
            'is_guest' => false,
            'jersey_number' => $entry->player?->jersey_number,
        ];
    }

    /**
     * @return array{A: Collection<int, LeagueSessionEntry>, B: Collection<int, LeagueSessionEntry>}
     */
    private function expectedRandomDraftTeams(LeagueSession $session): array
    {
        $seed = sprintf('session:%d:%d:random', $session->id, $session->current_game_number);
        $ordered = $session->entries
            ->where('session_state', 'pool')
            ->sortBy(function (LeagueSessionEntry $entry) use ($seed): string {
                return sprintf(
                    '%012s-%05d',
                    substr(hash('sha256', "{$seed}:draft:{$entry->id}"), 0, 12),
                    (int) $entry->arrival_order,
                );
            })
            ->values();

        return [
            'A' => $ordered->take(5)->values(),
            'B' => $ordered->slice(5, 5)->values(),
        ];
    }

    /**
     * @param  Collection<int, LeagueSessionEntry>  $team
     * @return array<int, int>
     */
    private function expectedOrderedTeamIds(Collection $team, int $captainId): array
    {
        /** @var LeagueSessionEntry $captain */
        $captain = $team->firstOrFail(fn (LeagueSessionEntry $entry): bool => $entry->id === $captainId);

        return collect([$captain])
            ->concat(
                $team
                    ->reject(fn (LeagueSessionEntry $entry): bool => $entry->id === $captainId)
                    ->sortBy(fn (LeagueSessionEntry $entry): string => mb_strtolower((string) $entry->player?->display_name))
                    ->values(),
            )
            ->pluck('id')
            ->all();
    }

    /**
     * @return Collection<int, LeaguePlayer>
     */
    private function appendBenchPlayersToSession(League $league, User $admin, int $count): Collection
    {
        $operations = app(LeagueOperationsService::class);
        $management = app(LeagueManagementService::class);
        $arrival = app(LeagueArrivalService::class);
        $cut = $operations->activeCutForLeague($league);
        $benchPlayers = LeaguePlayer::factory()
            ->count($count)
            ->for($league)
            ->create([
                'created_by_user_id' => $admin->id,
                'updated_by_user_id' => $admin->id,
            ]);

        foreach ($benchPlayers as $player) {
            $management->recordPayment($admin, $player, 60000, false, $cut->id);
            $arrival->togglePlayerArrival($admin, $player);
        }

        return $benchPlayers;
    }
}

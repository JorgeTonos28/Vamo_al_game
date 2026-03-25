<?php

namespace Tests\Feature\Api\V1;

use App\Models\League;
use App\Models\LeaguePlayer;
use App\Models\User;
use Database\Factories\LeagueMembershipFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeagueArrivalPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_view_arrival_but_cannot_execute_arrival_actions(): void
    {
        $league = League::factory()->create();
        $admin = User::factory()->leagueAdmin()->create([
            'active_league_id' => $league->id,
        ]);
        $member = User::factory()->memberRole()->create([
            'active_league_id' => $league->id,
        ]);

        LeagueMembershipFactory::new()->admin()->create([
            'league_id' => $league->id,
            'user_id' => $admin->id,
        ]);

        LeagueMembershipFactory::new()->member()->create([
            'league_id' => $league->id,
            'user_id' => $member->id,
        ]);

        $player = LeaguePlayer::factory()->for($league)->create([
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/v1/league/arrival')
            ->assertOk();

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/v1/league/arrival/players/{$player->id}/toggle")
            ->assertForbidden();

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/v1/league/arrival/guests', [
                'guest_name' => 'Invitado lectura',
            ])
            ->assertForbidden();

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/v1/league/arrival/reset')
            ->assertForbidden();
    }
}

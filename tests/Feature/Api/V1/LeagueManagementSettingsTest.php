<?php

namespace Tests\Feature\Api\V1;

use App\Models\League;
use App\Models\User;
use Database\Factories\LeagueMembershipFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeagueManagementSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_league_admin_can_update_incoming_team_guest_limit(): void
    {
        $league = League::factory()->create([
            'incoming_team_guest_limit' => 2,
        ]);
        $admin = User::factory()->leagueAdmin()->create([
            'active_league_id' => $league->id,
        ]);

        LeagueMembershipFactory::new()->admin()->create([
            'league_id' => $league->id,
            'user_id' => $admin->id,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/league/management/settings', [
                'sessions_limit' => 5,
                'game_days' => ['Lunes', 'Miércoles'],
                'cut_day' => 20,
                'incoming_team_guest_limit' => 3,
                'member_fee_amount_cents' => 65000,
                'guest_fee_amount_cents' => 15000,
                'referral_credit_amount_cents' => 25000,
            ])
            ->assertOk()
            ->assertJsonPath('data.settings.incoming_team_guest_limit', 3);

        $this->assertDatabaseHas('leagues', [
            'id' => $league->id,
            'incoming_team_guest_limit' => 3,
        ]);
    }
}

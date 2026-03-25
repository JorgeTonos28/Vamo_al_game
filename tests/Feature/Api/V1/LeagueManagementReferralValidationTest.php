<?php

namespace Tests\Feature\Api\V1;

use App\Models\League;
use App\Models\LeaguePlayer;
use App\Models\User;
use Database\Factories\LeagueMembershipFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeagueManagementReferralValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_referred_player_returns_a_validation_error(): void
    {
        $league = League::factory()->create();
        $admin = User::factory()->leagueAdmin()->create([
            'active_league_id' => $league->id,
        ]);

        LeagueMembershipFactory::new()->admin()->create([
            'league_id' => $league->id,
            'user_id' => $admin->id,
        ]);

        $referrerA = LeaguePlayer::factory()->for($league)->create([
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);
        $referrerB = LeaguePlayer::factory()->for($league)->create([
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);
        $referred = LeaguePlayer::factory()->for($league)->create([
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/league/management/referrals', [
                'referrer_player_id' => $referrerA->id,
                'referred_player_id' => $referred->id,
            ])
            ->assertCreated();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/league/management/referrals', [
                'referrer_player_id' => $referrerB->id,
                'referred_player_id' => $referred->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('referred_player_id');
    }
}

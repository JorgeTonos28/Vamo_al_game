<?php

namespace Tests\Feature;

use App\Enums\LeagueMembershipRole;
use App\Models\League;
use App\Models\LeagueMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InactiveLeagueAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_switch_to_an_inactive_league_and_is_redirected_to_unavailable_screen(): void
    {
        [$user, $activeLeague, $inactiveLeague] = $this->makeMemberWithActiveAndInactiveLeagues();

        $this->actingAs($user)
            ->post(route('active-league.store'), [
                'league_id' => $inactiveLeague->id,
            ])
            ->assertRedirect();

        $this->assertSame($inactiveLeague->id, $user->fresh()->active_league_id);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('app.unavailable'));
    }

    public function test_blocked_user_can_switch_back_to_an_active_league(): void
    {
        [$user, $activeLeague, $inactiveLeague] = $this->makeMemberWithActiveAndInactiveLeagues();

        $user->forceFill([
            'active_league_id' => $inactiveLeague->id,
        ])->save();

        $this->actingAs($user)
            ->get(route('app.unavailable'))
            ->assertOk();

        $this->actingAs($user)
            ->post(route('active-league.store'), [
                'league_id' => $activeLeague->id,
            ])
            ->assertRedirect();

        $this->assertSame($activeLeague->id, $user->fresh()->active_league_id);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }

    /**
     * @return array{0: User, 1: League, 2: League}
     */
    private function makeMemberWithActiveAndInactiveLeagues(): array
    {
        $user = User::factory()->memberRole()->create();
        $activeLeague = League::factory()->create();
        $inactiveLeague = League::factory()->inactive()->create();

        LeagueMembership::factory()->create([
            'user_id' => $user->id,
            'league_id' => $activeLeague->id,
            'role' => LeagueMembershipRole::Member,
        ]);

        LeagueMembership::factory()->create([
            'user_id' => $user->id,
            'league_id' => $inactiveLeague->id,
            'role' => LeagueMembershipRole::Member,
        ]);

        $user->forceFill([
            'active_league_id' => $activeLeague->id,
        ])->save();

        return [$user, $activeLeague, $inactiveLeague];
    }
}

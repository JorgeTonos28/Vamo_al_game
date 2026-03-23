<?php

namespace Tests\Feature;

use App\Enums\LeagueMembershipRole;
use App\Models\League;
use App\Models\LeagueMembership;
use App\Models\User;
use App\Services\Tenancy\LeagueContextResolver;
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

        $context = app(LeagueContextResolver::class)->contextFor($user->fresh());

        $this->assertFalse($context['has_blocked_access']);
        $this->assertSame($activeLeague->id, $context['active_league']['id']);
    }

    public function test_deactivating_the_current_league_falls_back_to_another_active_league_when_available(): void
    {
        $generalAdmin = User::factory()->generalAdmin()->create();
        $user = User::factory()->memberRole()->create();
        $currentLeague = League::factory()->create([
            'is_active' => true,
        ]);
        $fallbackLeague = League::factory()->create([
            'is_active' => true,
        ]);

        LeagueMembership::factory()->create([
            'user_id' => $user->id,
            'league_id' => $currentLeague->id,
            'role' => LeagueMembershipRole::Member,
        ]);

        LeagueMembership::factory()->admin()->create([
            'user_id' => $user->id,
            'league_id' => $fallbackLeague->id,
        ]);

        $user->forceFill([
            'active_league_id' => $currentLeague->id,
        ])->save();

        $this->actingAs($generalAdmin)
            ->patch(route('command-center.leagues.update', $currentLeague))
            ->assertRedirect(route('command-center.leagues.index'));

        $this->assertSame($fallbackLeague->id, $user->fresh()->active_league_id);

        $context = app(LeagueContextResolver::class)->contextFor($user->fresh());

        $this->assertFalse($context['has_blocked_access']);
        $this->assertSame($fallbackLeague->id, $context['active_league']['id']);
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

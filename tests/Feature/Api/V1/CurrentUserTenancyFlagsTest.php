<?php

namespace Tests\Feature\Api\V1;

use App\Enums\LeagueMembershipRole;
use App\Models\League;
use App\Models\LeagueMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentUserTenancyFlagsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_membership_exposes_guest_flags_in_tenancy_meta(): void
    {
        $user = User::factory()->guestRole()->create();
        $league = League::factory()->create();

        LeagueMembership::factory()->guest()->create([
            'user_id' => $user->id,
            'league_id' => $league->id,
            'role' => LeagueMembershipRole::Guest,
        ]);

        $user->forceFill([
            'active_league_id' => $league->id,
        ])->save();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/me');

        $response
            ->assertOk()
            ->assertJsonPath('meta.tenancy.can_access_modules', false)
            ->assertJsonPath('meta.tenancy.can_manage_league', false)
            ->assertJsonPath('meta.tenancy.is_guest_role', true)
            ->assertJsonPath('meta.tenancy.active_league.role', 'guest');
    }
}

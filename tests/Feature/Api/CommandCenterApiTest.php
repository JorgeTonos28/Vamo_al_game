<?php

namespace Tests\Feature\Api;

use App\Enums\AccountRole;
use App\Enums\LeagueMembershipRole;
use App\Models\League;
use App\Models\LeagueMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommandCenterApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_general_admin_can_fetch_command_center_payloads(): void
    {
        $generalAdmin = User::factory()->generalAdmin()->create();
        $leagueAdmin = User::factory()->leagueAdmin()->create();
        $league = League::factory()->create([
            'created_by_user_id' => $leagueAdmin->id,
        ]);

        LeagueMembership::factory()->admin()->create([
            'user_id' => $leagueAdmin->id,
            'league_id' => $league->id,
        ]);

        $this->actingAs($generalAdmin, 'sanctum')
            ->getJson('/api/v1/command-center/dashboard')
            ->assertOk()
            ->assertJsonPath('data.metrics.total_users', 2);

        $this->actingAs($generalAdmin, 'sanctum')
            ->getJson('/api/v1/command-center/users')
            ->assertOk()
            ->assertJsonCount(4, 'data.role_options')
            ->assertJsonCount(1, 'data.league_options');

        $this->actingAs($generalAdmin, 'sanctum')
            ->getJson('/api/v1/command-center/leagues')
            ->assertOk()
            ->assertJsonCount(1, 'data.leagues')
            ->assertJsonPath('data.leagues.0.members_count', 1);
    }

    public function test_general_admin_can_invite_users_from_api(): void
    {
        Notification::fake();

        $generalAdmin = User::factory()->generalAdmin()->create();
        $league = League::factory()->create();

        $this->actingAs($generalAdmin, 'sanctum')
            ->postJson('/api/v1/command-center/users', [
                'first_name' => 'Laura',
                'last_name' => 'Perez',
                'document_id' => '12345678901',
                'phone' => '809-555-1111',
                'address' => 'Santo Domingo',
                'email' => 'laura@example.com',
                'account_role' => AccountRole::LeagueAdmin->value,
                'league_id' => $league->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.user.email', 'laura@example.com')
            ->assertJsonPath('data.user.league_memberships_count', 1);

        $this->assertDatabaseHas('users', [
            'email' => 'laura@example.com',
            'active_league_id' => $league->id,
        ]);

        $this->assertDatabaseHas('league_memberships', [
            'user_id' => User::query()->where('email', 'laura@example.com')->value('id'),
            'league_id' => $league->id,
            'role' => 'admin',
        ]);
    }

    public function test_general_admin_can_toggle_league_access_from_api(): void
    {
        $generalAdmin = User::factory()->generalAdmin()->create();
        $league = League::factory()->create([
            'is_active' => true,
        ]);

        $this->actingAs($generalAdmin, 'sanctum')
            ->patchJson("/api/v1/command-center/leagues/{$league->id}")
            ->assertOk()
            ->assertJsonPath('data.league.is_active', false);
    }

    public function test_general_admin_can_assign_an_existing_user_to_an_active_league(): void
    {
        $generalAdmin = User::factory()->generalAdmin()->create();
        $league = League::factory()->create([
            'is_active' => true,
        ]);
        $user = User::factory()->memberRole()->create([
            'active_league_id' => null,
        ]);

        $this->actingAs($generalAdmin, 'sanctum')
            ->postJson("/api/v1/command-center/users/{$user->id}/leagues", [
                'league_id' => $league->id,
                'role' => 'admin',
            ])
            ->assertOk()
            ->assertJsonPath('data.user.league_memberships_count', 1)
            ->assertJsonPath('data.user.memberships.0.role', 'admin');

        $this->assertDatabaseHas('league_memberships', [
            'league_id' => $league->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);

        $this->assertDatabaseHas('league_players', [
            'league_id' => $league->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $this->assertSame($league->id, $user->fresh()->active_league_id);
    }

    public function test_general_admin_can_update_branding_from_api(): void
    {
        Storage::fake('local');

        $generalAdmin = User::factory()->generalAdmin()->create();

        $this->actingAs($generalAdmin, 'sanctum')
            ->post('/api/v1/command-center/settings', [
                'logo' => UploadedFile::fake()->image('logo.png', 960, 240),
                'favicon' => UploadedFile::fake()->image('favicon.png', 512, 512),
            ], [
                'Accept' => 'application/json',
            ])
            ->assertOk()
            ->assertJsonPath('data.branding.has_custom_logo', true)
            ->assertJsonPath('data.branding.has_custom_favicon', true);

        Storage::disk('local')->assertExists('branding/app-logo.png');
        Storage::disk('local')->assertExists('branding/app-favicon.png');
    }

    public function test_non_general_admin_cannot_access_command_center_api(): void
    {
        $member = User::factory()->memberRole()->create();
        $league = League::factory()->create();

        LeagueMembership::factory()->create([
            'user_id' => $member->id,
            'league_id' => $league->id,
            'role' => LeagueMembershipRole::Member,
        ]);

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/v1/command-center/dashboard')
            ->assertForbidden();
    }
}

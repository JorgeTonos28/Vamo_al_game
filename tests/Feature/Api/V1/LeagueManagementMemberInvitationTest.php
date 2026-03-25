<?php

namespace Tests\Feature\Api\V1;

use App\Enums\LeagueMembershipRole;
use App\Models\League;
use App\Models\User;
use Database\Factories\LeagueMembershipFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LeagueManagementMemberInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_league_admin_can_invite_a_member_from_league_management(): void
    {
        Notification::fake();

        $league = League::factory()->create();
        $admin = User::factory()->leagueAdmin()->create([
            'active_league_id' => $league->id,
        ]);

        LeagueMembershipFactory::new()->admin()->create([
            'league_id' => $league->id,
            'user_id' => $admin->id,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/league/management/players', [
                'first_name' => 'Carlos',
                'last_name' => 'Diaz',
                'document_id' => '55555555555',
                'phone' => '8095552020',
                'address' => 'Santo Domingo',
                'email' => 'carlos.diaz@example.com',
                'account_role' => 'member',
            ])
            ->assertCreated();

        $userId = User::query()->where('email', 'carlos.diaz@example.com')->value('id');

        $this->assertNotNull($userId);

        $this->assertDatabaseHas('league_memberships', [
            'league_id' => $league->id,
            'user_id' => $userId,
            'role' => LeagueMembershipRole::Member->value,
        ]);

        $this->assertDatabaseHas('league_players', [
            'league_id' => $league->id,
            'user_id' => $userId,
            'status' => 'active',
        ]);
    }

    public function test_league_admin_can_invite_multiple_members_without_document_id(): void
    {
        Notification::fake();

        $league = League::factory()->create();
        $admin = User::factory()->leagueAdmin()->create([
            'active_league_id' => $league->id,
        ]);

        LeagueMembershipFactory::new()->admin()->create([
            'league_id' => $league->id,
            'user_id' => $admin->id,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/league/management/players', [
                'first_name' => 'Carlos',
                'last_name' => 'Diaz',
                'document_id' => '',
                'phone' => '',
                'address' => '',
                'email' => 'carlos.blank@example.com',
                'account_role' => 'member',
            ])
            ->assertCreated();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/league/management/players', [
                'first_name' => 'Juan',
                'last_name' => 'Perez',
                'document_id' => '',
                'phone' => '',
                'address' => '',
                'email' => 'juan.blank@example.com',
                'account_role' => 'member',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => 'carlos.blank@example.com',
            'document_id' => null,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'juan.blank@example.com',
            'document_id' => null,
        ]);
    }
}

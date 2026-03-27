<?php

namespace Tests\Feature\Api\V1;

use App\Enums\LeagueMembershipRole;
use App\Models\League;
use App\Models\LeaguePlayer;
use App\Models\User;
use App\Notifications\AppInvitationNotification;
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
                'jersey_number' => 12,
                'account_role' => 'member',
            ])
            ->assertCreated();

        /** @var User|null $invitedUser */
        $invitedUser = User::query()->where('email', 'carlos.diaz@example.com')->first();

        $this->assertNotNull($invitedUser);

        $this->assertDatabaseHas('league_memberships', [
            'league_id' => $league->id,
            'user_id' => $invitedUser->id,
            'role' => LeagueMembershipRole::Member->value,
        ]);

        $this->assertDatabaseHas('league_players', [
            'league_id' => $league->id,
            'user_id' => $invitedUser->id,
            'status' => 'active',
            'jersey_number' => 12,
        ]);

        Notification::assertSentTo($invitedUser, AppInvitationNotification::class);
    }

    public function test_league_admin_can_add_a_member_without_email(): void
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
                'document_id' => '11111111111',
                'phone' => '',
                'address' => '',
                'email' => '',
                'jersey_number' => 7,
                'account_role' => 'member',
            ])
            ->assertCreated();

        /** @var User|null $memberWithoutEmail */
        $memberWithoutEmail = User::query()->where('document_id', '11111111111')->first();

        $this->assertNotNull($memberWithoutEmail);

        $this->assertDatabaseHas('users', [
            'id' => $memberWithoutEmail->id,
            'email' => null,
            'document_id' => '11111111111',
        ]);

        $this->assertDatabaseHas('league_players', [
            'league_id' => $league->id,
            'user_id' => $memberWithoutEmail->id,
            'jersey_number' => 7,
            'status' => 'active',
        ]);

        Notification::assertNothingSent();
    }

    public function test_league_admin_sends_invitation_when_editing_member_with_a_new_email(): void
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
                'first_name' => 'Juan',
                'last_name' => 'Perez',
                'document_id' => '22222222222',
                'phone' => '',
                'address' => '',
                'email' => '',
                'account_role' => 'member',
            ])
            ->assertCreated();

        /** @var LeaguePlayer $player */
        $player = LeaguePlayer::query()
            ->whereHas('user', fn ($query) => $query->where('document_id', '22222222222'))
            ->firstOrFail();

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/league/management/players/{$player->id}", [
                'first_name' => 'Juan',
                'last_name' => 'Perez',
                'document_id' => '22222222222',
                'phone' => '8095552020',
                'address' => 'Santo Domingo Este',
                'email' => 'juan.perez@example.com',
                'jersey_number' => 23,
                'account_role' => 'member',
            ])
            ->assertOk();

        /** @var User $updatedUser */
        $updatedUser = $player->fresh('user')->user;

        $this->assertSame('juan.perez@example.com', $updatedUser->email);
        Notification::assertSentTo($updatedUser, AppInvitationNotification::class);
    }
}

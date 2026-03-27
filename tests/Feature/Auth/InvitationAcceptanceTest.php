<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Invitations\UserInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_invited_user_can_complete_onboarding_with_matching_password_confirmation(): void
    {
        $user = User::factory()->memberRole()->create([
            'email_verified_at' => null,
            'onboarded_at' => null,
        ]);

        ['invitation' => $invitation, 'token' => $token] = app(UserInvitationService::class)->issue($user);

        $response = $this->post(route('invitations.store', $invitation), [
            'token' => $token,
            'first_name' => 'Nuevo',
            'last_name' => 'Miembro',
            'password' => 'PasswordSegura123',
            'password_confirmation' => 'PasswordSegura123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user->fresh());
        $this->assertNotNull($user->fresh()->onboarded_at);
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }
}

<?php

namespace Tests\Feature\Api\V1\Settings;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class SettingsManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_can_update_their_profile_via_api(): void
    {
        $user = User::factory()->create([
            'email' => 'before@example.com',
        ]);

        $response = $this->actingAs($user, 'sanctum')->patchJson('/api/v1/settings/profile', [
            'name' => 'Mobile Updated',
            'email' => 'after@example.com',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Perfil actualizado.')
            ->assertJsonPath('data.email', 'after@example.com')
            ->assertJsonPath('data.email_verified_at', null);
    }

    public function test_authenticated_users_can_request_a_new_verification_email_via_api(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/settings/email/verification-notification');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Se envio un nuevo enlace de verificacion.');

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_authenticated_users_can_update_their_password_via_api(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->putJson('/api/v1/settings/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Contrasena actualizada.');
    }

    public function test_authenticated_users_can_delete_their_account_via_api(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/settings/profile', [
            'password' => 'password',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Cuenta eliminada.');

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
    }

    public function test_authenticated_users_can_manage_two_factor_settings_via_api(): void
    {
        $user = User::factory()->create();

        $enableResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/settings/two-factor');

        $enableResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pending_setup', true)
            ->assertJsonPath('data.confirmed', false);

        $setupResponse = $this->actingAs($user->fresh(), 'sanctum')
            ->getJson('/api/v1/settings/two-factor/setup');

        $setupResponse
            ->assertOk()
            ->assertJsonPath('success', true);

        $code = app(Google2FA::class)->getCurrentOtp(
            Fortify::currentEncrypter()->decrypt($user->fresh()->two_factor_secret),
        );

        $confirmResponse = $this->actingAs($user->fresh(), 'sanctum')
            ->postJson('/api/v1/settings/two-factor/confirm', [
                'code' => $code,
            ]);

        $confirmResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Autenticacion de dos factores activada.')
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.confirmed', true);

        $recoveryCodesResponse = $this->actingAs($user->fresh(), 'sanctum')
            ->getJson('/api/v1/settings/two-factor/recovery-codes');

        $recoveryCodesResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(8, 'data.codes');

        $regeneratedCodesResponse = $this->actingAs($user->fresh(), 'sanctum')
            ->postJson('/api/v1/settings/two-factor/recovery-codes');

        $regeneratedCodesResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(8, 'data.codes');

        $disableResponse = $this->actingAs($user->fresh(), 'sanctum')
            ->deleteJson('/api/v1/settings/two-factor');

        $disableResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Autenticacion de dos factores desactivada.')
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.pending_setup', false);
    }
}

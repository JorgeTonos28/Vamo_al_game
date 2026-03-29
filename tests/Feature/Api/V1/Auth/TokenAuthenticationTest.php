<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Actions\Api\Auth\CreateMobileOauthHandoff;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use App\Services\Invitations\UserInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TokenAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_register_via_the_api(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Mobile Coach',
            'email' => 'coach@vamoalgame.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Cuenta creada. Verifica tu correo antes de iniciar sesion.')
            ->assertJsonPath('data.email', 'coach@vamoalgame.test')
            ->assertJsonPath('meta.must_verify_email', true);

        $user = User::query()->where('email', 'coach@vamoalgame.test')->firstOrFail();

        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_google_handoffs_can_be_exchanged_for_a_mobile_token(): void
    {
        $user = User::factory()->create();
        $handoff = app(CreateMobileOauthHandoff::class)->handle($user, 'Google OAuth');

        $response = $this->postJson('/api/v1/auth/google/exchange', [
            'handoff' => $handoff,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Sesion iniciada con Google.')
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonPath('data.token_type', 'Bearer');

        $this->postJson('/api/v1/auth/google/exchange', [
            'handoff' => $handoff,
        ])->assertUnprocessable();
    }

    public function test_verified_users_can_authenticate_with_the_api(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'Ionic Dev',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Sesion iniciada.')
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonPath('data.token_type', 'Bearer');

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'Ionic Dev',
        ]);
    }

    public function test_verified_users_can_authenticate_with_trimmed_and_uppercased_email_via_the_api(): void
    {
        $user = User::factory()->create([
            'email' => 'adminleaguetest@vamoalgame.com',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => '  AdminLeagueTest@VamoAlGame.com  ',
            'password' => 'password',
            'device_name' => 'Ionic Dev',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'adminleaguetest@vamoalgame.com');
    }

    public function test_verified_users_with_two_factor_enabled_receive_a_mobile_challenge(): void
    {
        $user = User::factory()->create();
        $this->enableTwoFactor($user);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'Ionic Dev',
        ]);

        $response
            ->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Se requiere autenticacion de dos factores.')
            ->assertJsonPath('data.recovery_code_allowed', true);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'Ionic Dev',
        ]);
    }

    public function test_users_can_complete_a_mobile_two_factor_challenge_with_a_recovery_code(): void
    {
        $user = User::factory()->create();
        $this->enableTwoFactor($user);
        $recoveryCode = $user->fresh()->recoveryCodes()[0];

        $challengeResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'Ionic Dev',
        ]);

        $challengeToken = $challengeResponse->json('data.challenge_token');

        $response = $this->postJson('/api/v1/auth/two-factor-challenge', [
            'challenge_token' => $challengeToken,
            'recovery_code' => $recoveryCode,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Sesion iniciada.')
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonPath('data.token_type', 'Bearer');
    }

    public function test_google_exchange_returns_a_two_factor_challenge_when_required(): void
    {
        $user = User::factory()->create();
        $this->enableTwoFactor($user);
        $handoff = app(CreateMobileOauthHandoff::class)->handle($user, 'Google OAuth');

        $response = $this->postJson('/api/v1/auth/google/exchange', [
            'handoff' => $handoff,
        ]);

        $response
            ->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Se requiere autenticacion de dos factores.')
            ->assertJsonPath('data.recovery_code_allowed', true);
    }

    public function test_unverified_users_can_not_get_an_api_token(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Debes verificar tu correo antes de usar la app movil.');
    }

    public function test_pending_invited_users_can_not_get_an_api_token(): void
    {
        $user = User::factory()->create([
            'invited_at' => now(),
            'onboarded_at' => null,
        ]);

        app(UserInvitationService::class)->issue($user);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_expired_invited_users_can_not_get_an_api_token(): void
    {
        $user = User::factory()->create([
            'invited_at' => now()->subDays(8),
            'onboarded_at' => null,
        ]);

        app(UserInvitationService::class)->issue($user);
        $user->invitation()->update([
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_authenticated_users_can_logout_from_the_api(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Ionic Dev');

        $response = $this->withToken($token->plainTextToken)
            ->postJson('/api/v1/auth/logout');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Sesion cerrada.');

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    }

    public function test_authenticated_users_can_fetch_their_profile(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Ionic Dev');

        $response = $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/me');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', $user->email);
    }

    private function enableTwoFactor(User $user): void
    {
        app(EnableTwoFactorAuthentication::class)($user);

        $secret = Fortify::currentEncrypter()->decrypt($user->fresh()->two_factor_secret);
        $code = app(Google2FA::class)->getCurrentOtp($secret);

        app(ConfirmTwoFactorAuthentication::class)($user->fresh(), $code);
    }
}

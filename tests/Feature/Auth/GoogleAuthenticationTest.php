<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\UserInvitation;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_callback_creates_an_unverified_account_and_sends_verification_email(): void
    {
        Notification::fake();

        $googleUser = Mockery::mock(SocialiteUserContract::class);
        $googleUser->shouldReceive('getId')->andReturn('google-123');
        $googleUser->shouldReceive('getEmail')->andReturn('google-user@example.com');
        $googleUser->shouldReceive('getName')->andReturn('Google User');
        $googleUser->shouldReceive('getNickname')->andReturn(null);
        $googleUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.png');

        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('setHttpClient')->once()->andReturnSelf();
        $provider->shouldReceive('user')->once()->andReturn($googleUser);

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);

        $response = $this->get(route('auth.google.callback'));

        $user = User::where('email', 'google-user@example.com')->first();

        $this->assertNotNull($user);
        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmailNotification::class);
        $response->assertRedirect(route('verification.notice'));
    }

    public function test_google_callback_links_existing_verified_user_and_redirects_to_dashboard(): void
    {
        $user = User::factory()->create([
            'email' => 'verified@example.com',
        ]);

        $googleUser = Mockery::mock(SocialiteUserContract::class);
        $googleUser->shouldReceive('getId')->andReturn('google-verified');
        $googleUser->shouldReceive('getEmail')->andReturn('verified@example.com');
        $googleUser->shouldReceive('getName')->andReturn('Verified User');
        $googleUser->shouldReceive('getNickname')->andReturn(null);
        $googleUser->shouldReceive('getAvatar')->andReturn('https://example.com/verified.png');

        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('setHttpClient')->once()->andReturnSelf();
        $provider->shouldReceive('user')->once()->andReturn($googleUser);

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);

        $response = $this->get(route('auth.google.callback'));

        $this->assertAuthenticatedAs($user->fresh());
        $this->assertSame('google-verified', $user->fresh()->google_id);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_mobile_google_callback_redirects_back_to_the_mobile_shell_with_a_handoff(): void
    {
        config([
            'app.mobile_url' => 'http://localhost:8100',
        ]);

        $user = User::factory()->create([
            'email' => 'verified@example.com',
        ]);

        $googleUser = Mockery::mock(SocialiteUserContract::class);
        $googleUser->shouldReceive('getId')->andReturn('google-mobile');
        $googleUser->shouldReceive('getEmail')->andReturn('verified@example.com');
        $googleUser->shouldReceive('getName')->andReturn('Verified Mobile User');
        $googleUser->shouldReceive('getNickname')->andReturn(null);
        $googleUser->shouldReceive('getAvatar')->andReturn('https://example.com/mobile.png');

        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('setHttpClient')->once()->andReturnSelf();
        $provider->shouldReceive('user')->once()->andReturn($googleUser);

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);

        $response = $this
            ->withSession(['auth.google.channel' => 'mobile'])
            ->get(route('auth.google.callback'));

        $this->assertGuest();
        $response->assertRedirectContains('http://localhost:8100/auth/google/callback?handoff=');
    }

    public function test_google_callback_returns_an_actionable_message_for_ssl_certificate_errors(): void
    {
        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('setHttpClient')->once()->andReturnSelf();
        $provider->shouldReceive('user')
            ->once()
            ->andThrow(new RuntimeException('cURL error 60: SSL certificate problem: self-signed certificate'));

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status', function (string $status): bool {
            return str_contains($status, 'curl.cainfo')
                && str_contains($status, 'openssl.cafile')
                && str_contains($status, 'localhost o 127.0.0.1');
        });
    }

    public function test_google_callback_redirects_back_to_login_when_invitation_completion_fails(): void
    {
        $invitedUser = User::factory()->create([
            'email' => 'invitee@example.com',
            'invited_at' => now(),
            'onboarded_at' => null,
        ]);

        $invitation = UserInvitation::query()->create([
            'user_id' => $invitedUser->id,
            'token_hash' => hash('sha256', 'valid-token'),
            'expires_at' => now()->subDay(),
            'accepted_at' => null,
            'last_sent_at' => now(),
        ]);

        $googleUser = Mockery::mock(SocialiteUserContract::class);
        $googleUser->shouldReceive('getId')->andReturn('google-invitee');
        $googleUser->shouldReceive('getEmail')->andReturn('invitee@example.com');
        $googleUser->shouldReceive('getName')->andReturn('Invitee');
        $googleUser->shouldReceive('getNickname')->andReturn(null);
        $googleUser->shouldReceive('getAvatar')->andReturn('https://example.com/invitee.png');

        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('setHttpClient')->once()->andReturnSelf();
        $provider->shouldReceive('user')->once()->andReturn($googleUser);

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);

        $response = $this
            ->withSession([
                'auth.google.invitation' => [
                    'invitation_id' => $invitation->id,
                    'token' => 'valid-token',
                ],
            ])
            ->get(route('auth.google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status', 'La invitacion no es valida o ya expiro.');
    }
}

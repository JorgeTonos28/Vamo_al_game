<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
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
        Notification::assertSentTo($user, VerifyEmail::class);
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
}

<?php

namespace App\Http\Controllers\Web\Auth;

use App\Actions\Api\Auth\CreateMobileOauthHandoff;
use App\Actions\Auth\SynchronizeGoogleUser;
use App\Http\Controllers\Controller;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        if ($request->string('channel')->value() === 'mobile') {
            $request->session()->put('auth.google.channel', 'mobile');
        } else {
            $request->session()->forget('auth.google.channel');
        }

        return $this->googleProvider()
            ->redirect();
    }

    public function callback(
        Request $request,
        SynchronizeGoogleUser $synchronizeGoogleUser,
        CreateMobileOauthHandoff $createMobileOauthHandoff,
    ): RedirectResponse
    {
        try {
            $googleUser = $this->googleProvider()->user();
        } catch (Throwable $exception) {
            report($exception);

            return $this->googleFailureRedirect(
                $request,
                $this->googleFailureMessage($exception),
            );
        }

        if (! $googleUser->getEmail()) {
            return $this->googleFailureRedirect(
                $request,
                'Google no devolvio un correo valido para crear la cuenta.',
            );
        }

        $user = $synchronizeGoogleUser->handle($googleUser);

        if ($this->usesMobileChannel($request)) {
            $request->session()->forget('auth.google.channel');

            return $this->completeMobileSignIn(
                $user,
                $createMobileOauthHandoff,
            );
        }

        Auth::login($user, true);

        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();

            return redirect()
                ->route('verification.notice')
                ->with('status', 'verification-link-sent');
        }

        return redirect()->intended(route('dashboard'));
    }

    private function completeMobileSignIn(
        User $user,
        CreateMobileOauthHandoff $createMobileOauthHandoff,
    ): RedirectResponse
    {
        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();

            return redirect()->away($this->mobileCallbackUrl([
                'status' => 'verification_required',
                'message' => 'Debes verificar tu correo antes de entrar desde movil.',
            ]));
        }

        $handoff = $createMobileOauthHandoff->handle($user, 'Google OAuth');

        return redirect()->away($this->mobileCallbackUrl([
            'handoff' => $handoff,
        ]));
    }

    private function googleProvider(): GoogleProvider
    {
        $caBundle = ini_get('curl.cainfo') ?: ini_get('openssl.cafile') ?: true;

        return Socialite::driver('google')
            ->setHttpClient(new Client([
                'connect_timeout' => 5,
                'timeout' => 10,
                'verify' => $caBundle,
            ]));
    }

    private function googleFailureRedirect(Request $request, string $message): RedirectResponse
    {
        if ($this->usesMobileChannel($request)) {
            $request->session()->forget('auth.google.channel');

            return redirect()->away($this->mobileCallbackUrl([
                'status' => 'google_failed',
                'message' => $message,
            ]));
        }

        return redirect()
            ->route('login')
            ->with('status', $message);
    }

    private function usesMobileChannel(Request $request): bool
    {
        return $request->session()->get('auth.google.channel') === 'mobile';
    }

    /**
     * @param  array<string, string>  $query
     */
    private function mobileCallbackUrl(array $query = []): string
    {
        $baseUrl = rtrim((string) config('app.mobile_url'), '/');
        $url = "{$baseUrl}/auth/google/callback";

        if ($query === []) {
            return $url;
        }

        return $url.'?'.http_build_query($query);
    }

    private function googleFailureMessage(Throwable $exception): string
    {
        $details = strtolower($this->flattenExceptionMessages($exception));

        if (
            str_contains($details, 'curl error 60')
            || str_contains($details, 'ssl certificate problem')
            || str_contains($details, 'certificate verify failed')
            || str_contains($details, 'self-signed certificate')
        ) {
            return 'No fue posible completar el acceso con Google porque PHP no pudo validar el certificado SSL al conectar con Google. Configura curl.cainfo y openssl.cafile en tu php.ini, y usa la misma URL base en APP_URL, GOOGLE_REDIRECT_URI, Google Cloud y el navegador: localhost o 127.0.0.1, pero no mezclados.';
        }

        if (str_contains($details, 'invalid state')) {
            return 'No fue posible completar el acceso con Google porque la sesion OAuth no coincidio al volver del proveedor. Usa la misma URL base en APP_URL, GOOGLE_REDIRECT_URI, Google Cloud y el navegador: localhost o 127.0.0.1, pero no mezclados.';
        }

        if (
            str_contains($details, 'timed out')
            || str_contains($details, 'maximum execution time')
            || str_contains($details, 'curl error 28')
        ) {
            return 'No fue posible completar el acceso con Google porque la conexion expiro antes de terminar el intercambio OAuth. Revisa tu conexion saliente, certificados SSL y la configuracion de APP_URL/GOOGLE_REDIRECT_URI.';
        }

        return 'No fue posible completar el acceso con Google. Intentalo de nuevo.';
    }

    private function flattenExceptionMessages(Throwable $exception): string
    {
        $messages = [];
        $current = $exception;

        do {
            $messages[] = $current->getMessage();
            $current = $current->getPrevious();
        } while ($current);

        return implode(' | ', array_filter($messages));
    }
}

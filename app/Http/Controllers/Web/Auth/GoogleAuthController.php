<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return $this->googleProvider()
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = $this->googleProvider()->user();
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('login')
                ->with('status', $this->googleFailureMessage($exception));
        }

        if (! $googleUser->getEmail()) {
            return redirect()
                ->route('login')
                ->with('status', 'Google no devolvio un correo valido para crear la cuenta.');
        }

        $user = User::query()
            ->where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if (! $user) {
            $user = User::create([
                'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: 'Google User',
                'email' => $googleUser->getEmail(),
                'password' => Str::password(32),
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
            ]);
        } else {
            $user->forceFill([
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar() ?: $user->avatar,
                'name' => $user->name ?: ($googleUser->getName() ?: 'Google User'),
            ])->save();
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

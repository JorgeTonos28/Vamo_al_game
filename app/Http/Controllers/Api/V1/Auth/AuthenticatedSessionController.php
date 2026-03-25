<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Api\Auth\CreateMobileTwoFactorChallenge;
use App\Actions\Api\Auth\IssueMobileToken;
use App\Actions\Api\Auth\RevokeCurrentAccessToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Resources\V1\AuthTokenResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    public function store(
        LoginRequest $request,
        IssueMobileToken $issueMobileToken,
        CreateMobileTwoFactorChallenge $createMobileTwoFactorChallenge,
    ): JsonResponse {
        $user = User::query()
            ->where('email', $request->string('email')->value())
            ->first();

        if (! $user || ! Hash::check($request->string('password')->value(), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas no son validas.'],
            ]);
        }

        if ($user->mustCompleteInvitationOnboarding()) {
            throw ValidationException::withMessages([
                'email' => ['Debes completar la invitacion enviada a tu correo antes de iniciar sesion.'],
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            return ApiResponse::error(
                'Debes verificar tu correo antes de usar la app movil.',
                403,
                [
                    'email' => ['Tu cuenta todavia no ha verificado el correo electronico.'],
                ],
            );
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            return TwoFactorChallengeController::pendingResponse(
                $request,
                $user,
                $request->deviceName(),
                $createMobileTwoFactorChallenge,
            );
        }

        $token = $issueMobileToken->handle($user, $request->deviceName());

        return ApiResponse::success(
            $request,
            new AuthTokenResource([
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $token->accessToken->expires_at,
                'user' => $user,
            ]),
            'Sesion iniciada.',
        );
    }

    public function destroy(Request $request, RevokeCurrentAccessToken $revokeCurrentAccessToken): JsonResponse
    {
        $revokeCurrentAccessToken->handle($request);

        return ApiResponse::success($request, null, 'Sesion cerrada.');
    }
}

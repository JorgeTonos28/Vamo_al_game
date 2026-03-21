<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Api\Auth\ConsumeMobileOauthHandoff;
use App\Actions\Api\Auth\CreateMobileTwoFactorChallenge;
use App\Actions\Api\Auth\IssueMobileToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\GoogleExchangeRequest;
use App\Http\Resources\V1\AuthTokenResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class GoogleExchangeController extends Controller
{
    public function __invoke(
        GoogleExchangeRequest $request,
        ConsumeMobileOauthHandoff $consumeMobileOauthHandoff,
        IssueMobileToken $issueMobileToken,
        CreateMobileTwoFactorChallenge $createMobileTwoFactorChallenge,
    ): JsonResponse {
        $payload = $consumeMobileOauthHandoff->handle($request->handoff());

        if (! $payload) {
            return ApiResponse::error(
                'La autenticacion con Google ya expiro o no es valida.',
                422,
                [
                    'handoff' => ['Debes iniciar de nuevo el acceso con Google.'],
                ],
            );
        }

        $user = User::query()->findOrFail($payload['user_id']);

        if ($user->hasEnabledTwoFactorAuthentication()) {
            return TwoFactorChallengeController::pendingResponse(
                $request,
                $user,
                $payload['device_name'],
                $createMobileTwoFactorChallenge,
            );
        }

        $token = $issueMobileToken->handle($user, $payload['device_name']);

        return ApiResponse::success(
            $request,
            new AuthTokenResource([
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $token->accessToken->expires_at,
                'user' => $user,
            ]),
            'Sesion iniciada con Google.',
        );
    }
}

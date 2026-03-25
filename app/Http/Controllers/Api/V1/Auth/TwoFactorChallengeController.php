<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Api\Auth\CreateMobileTwoFactorChallenge;
use App\Actions\Api\Auth\ForgetMobileTwoFactorChallenge;
use App\Actions\Api\Auth\GetMobileTwoFactorChallenge;
use App\Actions\Api\Auth\IssueMobileToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\TwoFactorChallengeRequest;
use App\Http\Resources\V1\AuthTokenResource;
use App\Http\Resources\V1\TwoFactorChallengeResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TwoFactorChallengeController extends Controller
{
    public function store(
        TwoFactorChallengeRequest $request,
        GetMobileTwoFactorChallenge $getMobileTwoFactorChallenge,
        ForgetMobileTwoFactorChallenge $forgetMobileTwoFactorChallenge,
        IssueMobileToken $issueMobileToken,
    ): JsonResponse {
        $payload = $getMobileTwoFactorChallenge->handle($request->challengeToken());

        if (! $payload) {
            return ApiResponse::error(
                'El reto de dos factores ya expiro o no es valido.',
                422,
                [
                    'challenge_token' => ['Debes iniciar sesion nuevamente.'],
                ],
            );
        }

        $user = User::query()->find($payload['user_id']);

        if (! $user || ! $user->hasEnabledTwoFactorAuthentication()) {
            $forgetMobileTwoFactorChallenge->handle($request->challengeToken());

            return ApiResponse::error(
                'El reto de dos factores ya no es valido.',
                422,
                [
                    'challenge_token' => ['Debes iniciar sesion nuevamente.'],
                ],
            );
        }

        $recoveryCode = $request->validRecoveryCode($user);

        if ($recoveryCode) {
            $user->replaceRecoveryCode($recoveryCode);
        } elseif (! $request->hasValidCode($user)) {
            return ApiResponse::error(
                'El codigo de dos factores no es valido.',
                422,
                [
                    'code' => ['El codigo o recovery code no es valido.'],
                ],
            );
        }

        $forgetMobileTwoFactorChallenge->handle($request->challengeToken());

        $token = $issueMobileToken->handle($user, $payload['device_name']);

        return ApiResponse::success(
            $request,
            new AuthTokenResource([
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $token->accessToken->expires_at,
                'user' => $user->fresh(),
            ]),
            'Sesion iniciada.',
        );
    }

    public static function pendingResponse(
        Request $request,
        User $user,
        string $deviceName,
        CreateMobileTwoFactorChallenge $createMobileTwoFactorChallenge,
    ): JsonResponse {
        $challengeToken = $createMobileTwoFactorChallenge->handle($user, $deviceName);

        return ApiResponse::success(
            $request,
            new TwoFactorChallengeResource([
                'challenge_token' => $challengeToken,
            ]),
            'Se requiere autenticacion de dos factores.',
            202,
        );
    }
}

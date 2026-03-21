<?php

namespace App\Http\Controllers\Api\V1\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Settings\ConfirmTwoFactorRequest;
use App\Http\Resources\V1\RecoveryCodesResource;
use App\Http\Resources\V1\TwoFactorSetupResource;
use App\Http\Resources\V1\TwoFactorStatusResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Features;

class TwoFactorController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $this->ensureFeatureIsEnabled();

        return ApiResponse::success(
            $request,
            new TwoFactorStatusResource($request->user()),
            'Configuracion de dos factores cargada.',
        );
    }

    public function store(
        Request $request,
        EnableTwoFactorAuthentication $enableTwoFactorAuthentication,
    ): JsonResponse {
        $this->ensureFeatureIsEnabled();

        $enableTwoFactorAuthentication($request->user());

        return ApiResponse::success(
            $request,
            new TwoFactorSetupResource($request->user()->fresh()),
            'Autenticacion de dos factores lista para configurarse.',
        );
    }

    public function setup(Request $request): JsonResponse
    {
        $this->ensureFeatureIsEnabled();

        $user = $request->user();

        if (! $user->two_factor_secret) {
            return ApiResponse::error(
                'Debes habilitar 2FA antes de consultar el setup.',
                404,
                [
                    'two_factor' => ['La autenticacion de dos factores todavia no esta habilitada.'],
                ],
            );
        }

        return ApiResponse::success(
            $request,
            new TwoFactorSetupResource($user),
            'Setup de dos factores cargado.',
        );
    }

    public function confirm(
        ConfirmTwoFactorRequest $request,
        ConfirmTwoFactorAuthentication $confirmTwoFactorAuthentication,
    ): JsonResponse {
        $this->ensureFeatureIsEnabled();

        $confirmTwoFactorAuthentication($request->user(), $request->code());

        return ApiResponse::success(
            $request,
            new TwoFactorStatusResource($request->user()->fresh()),
            'Autenticacion de dos factores activada.',
        );
    }

    public function destroy(
        Request $request,
        DisableTwoFactorAuthentication $disableTwoFactorAuthentication,
    ): JsonResponse {
        $this->ensureFeatureIsEnabled();

        $disableTwoFactorAuthentication($request->user());

        return ApiResponse::success(
            $request,
            new TwoFactorStatusResource($request->user()->fresh()),
            'Autenticacion de dos factores desactivada.',
        );
    }

    public function recoveryCodes(Request $request): JsonResponse
    {
        $this->ensureFeatureIsEnabled();

        return ApiResponse::success(
            $request,
            new RecoveryCodesResource($request->user()),
            'Codigos de recuperacion cargados.',
        );
    }

    public function regenerateRecoveryCodes(
        Request $request,
        GenerateNewRecoveryCodes $generateNewRecoveryCodes,
    ): JsonResponse {
        $this->ensureFeatureIsEnabled();

        $generateNewRecoveryCodes($request->user());

        return ApiResponse::success(
            $request,
            new RecoveryCodesResource($request->user()->fresh()),
            'Codigos de recuperacion regenerados.',
        );
    }

    private function ensureFeatureIsEnabled(): void
    {
        abort_unless(Features::canManageTwoFactorAuthentication(), 404);
    }
}

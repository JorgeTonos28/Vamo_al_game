<?php

namespace App\Http\Controllers\Api\V1\CommandCenter;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CommandCenter\UpdateBrandingSettingsRequest;
use App\Services\Branding\AppBrandingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index(Request $request, AppBrandingService $brandingService): JsonResponse
    {
        return ApiResponse::success(
            $request,
            [
                'branding' => $brandingService->branding(),
            ],
            'Configuracion del branding cargada.',
        );
    }

    public function update(
        UpdateBrandingSettingsRequest $request,
        AppBrandingService $brandingService,
    ): JsonResponse {
        $brandingService->update([
            'logo' => $request->file('logo'),
            'favicon' => $request->file('favicon'),
        ]);

        return ApiResponse::success(
            $request,
            [
                'branding' => $brandingService->branding(),
            ],
            'Branding actualizado correctamente.',
        );
    }
}

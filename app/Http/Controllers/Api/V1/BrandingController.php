<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Branding\AppBrandingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandingController extends Controller
{
    public function __invoke(Request $request, AppBrandingService $brandingService): JsonResponse
    {
        return ApiResponse::success(
            $request,
            $brandingService->branding(),
            'Branding cargado.',
        );
    }
}

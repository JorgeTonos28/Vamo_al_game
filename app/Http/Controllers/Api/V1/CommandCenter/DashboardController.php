<?php

namespace App\Http\Controllers\Api\V1\CommandCenter;

use App\Http\Controllers\Controller;
use App\Services\CommandCenter\CommandCenterMetricsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        CommandCenterMetricsService $metricsService,
    ): JsonResponse {
        return ApiResponse::success(
            $request,
            [
                'metrics' => $metricsService->totals(),
            ],
            'Metricas del centro de mando cargadas.',
        );
    }
}

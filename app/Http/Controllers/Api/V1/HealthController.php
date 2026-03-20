<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\HealthResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HealthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $request,
            new HealthResource([
                'status' => 'ok',
                'app' => config('app.name'),
                'api_version' => 'v1',
                'timestamp' => now()->toIso8601String(),
            ]),
            'API disponible.',
        );
    }
}

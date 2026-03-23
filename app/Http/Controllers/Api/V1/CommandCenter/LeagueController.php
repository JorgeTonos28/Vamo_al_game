<?php

namespace App\Http\Controllers\Api\V1\CommandCenter;

use App\Http\Controllers\Controller;
use App\Models\League;
use App\Services\CommandCenter\CommandCenterLeagueDirectoryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeagueController extends Controller
{
    public function index(
        Request $request,
        CommandCenterLeagueDirectoryService $directoryService,
    ): JsonResponse {
        return ApiResponse::success(
            $request,
            [
                'leagues' => $directoryService->leagues(),
            ],
            'Ligas del centro de mando cargadas.',
        );
    }

    public function update(
        Request $request,
        League $league,
        CommandCenterLeagueDirectoryService $directoryService,
    ): JsonResponse {
        return ApiResponse::success(
            $request,
            [
                'league' => $directoryService->toggle($league),
            ],
            'Estado de la liga actualizado.',
        );
    }
}

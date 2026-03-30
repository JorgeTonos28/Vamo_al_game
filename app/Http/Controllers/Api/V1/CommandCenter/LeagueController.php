<?php

namespace App\Http\Controllers\Api\V1\CommandCenter;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CommandCenter\StoreLeagueRequest;
use App\Http\Requests\Api\V1\CommandCenter\UpdateLeagueRequest;
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

    public function store(
        StoreLeagueRequest $request,
        CommandCenterLeagueDirectoryService $directoryService,
    ): JsonResponse {
        return ApiResponse::success(
            $request,
            [
                'league' => $directoryService->create(
                    $request->user(),
                    $request->string('name')->value(),
                    $request->input('emoji'),
                ),
            ],
            'Liga creada correctamente.',
            201,
        );
    }

    public function update(
        UpdateLeagueRequest $request,
        League $league,
        CommandCenterLeagueDirectoryService $directoryService,
    ): JsonResponse {
        if ($request->hasAny(['name', 'emoji'])) {
            return ApiResponse::success(
                $request,
                [
                    'league' => $directoryService->update(
                        $league,
                        $request->has('name') ? $request->string('name')->value() : null,
                        $request->has('emoji') ? $request->input('emoji') : null,
                    ),
                ],
                'Liga actualizada correctamente.',
            );
        }

        return ApiResponse::success(
            $request,
            [
                'league' => $directoryService->toggle($league),
            ],
            'Estado de la liga actualizado.',
        );
    }
}

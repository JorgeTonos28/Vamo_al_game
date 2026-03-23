<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\UserResource;
use App\Services\Tenancy\LeagueContextResolver;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrentUserController extends Controller
{
    public function __invoke(
        Request $request,
        LeagueContextResolver $leagueContextResolver,
    ): JsonResponse
    {
        return ApiResponse::success(
            $request,
            new UserResource($request->user()),
            'Perfil cargado.',
            200,
            [
                'tenancy' => $leagueContextResolver->contextFor($request->user()),
            ],
        );
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ActiveLeagueUpdateRequest;
use App\Http\Resources\V1\UserResource;
use App\Services\Tenancy\LeagueContextResolver;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ActiveLeagueController extends Controller
{
    public function __invoke(
        ActiveLeagueUpdateRequest $request,
        LeagueContextResolver $leagueContextResolver,
    ): JsonResponse {
        abort_unless(
            $leagueContextResolver->switchActiveLeague(
                $request->user(),
                $request->integer('league_id'),
            ),
            403,
        );

        $user = $request->user()->fresh();

        return ApiResponse::success(
            $request,
            new UserResource($user),
            'Liga activa actualizada.',
            200,
            [
                'tenancy' => $leagueContextResolver->contextFor($user),
            ],
        );
    }
}

<?php

namespace App\Http\Controllers\Api\V1\League;

use App\Http\Controllers\Controller;
use App\Models\LeagueSession;
use App\Services\LeagueOperations\LeagueCompetitionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function __construct(
        private readonly LeagueCompetitionService $competition,
    ) {}

    public function destroy(Request $request, LeagueSession $session): JsonResponse
    {
        return ApiResponse::success(
            $request,
            $this->competition->destroySession($request->user(), $session),
            'Jornada eliminada.',
        );
    }
}

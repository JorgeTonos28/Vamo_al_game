<?php

namespace App\Http\Controllers\Api\V1\League;

use App\Http\Controllers\Controller;
use App\Services\LeagueOperations\LeagueHomeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __construct(
        private readonly LeagueHomeService $leagueHomeService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $request,
            $this->leagueHomeService->pageData($request->user()),
            'Panel de liga cargado.',
        );
    }
}

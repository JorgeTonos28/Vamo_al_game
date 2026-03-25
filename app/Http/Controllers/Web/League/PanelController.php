<?php

namespace App\Http\Controllers\Web\League;

use App\Http\Controllers\Controller;
use App\Services\LeagueOperations\LeagueHomeService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PanelController extends Controller
{
    public function __construct(
        private readonly LeagueHomeService $leagueHomeService,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('league/Panel', [
            'module' => $this->leagueHomeService->pageData($request->user()),
        ]);
    }
}

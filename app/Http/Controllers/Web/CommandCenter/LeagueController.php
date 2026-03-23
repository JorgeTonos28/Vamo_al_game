<?php

namespace App\Http\Controllers\Web\CommandCenter;

use App\Http\Controllers\Controller;
use App\Models\League;
use App\Services\CommandCenter\CommandCenterLeagueDirectoryService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LeagueController extends Controller
{
    public function index(CommandCenterLeagueDirectoryService $directoryService): Response
    {
        return Inertia::render('command-center/Leagues', [
            'leagues' => $directoryService->leagues(),
        ]);
    }

    public function update(
        League $league,
        CommandCenterLeagueDirectoryService $directoryService,
    ): RedirectResponse
    {
        $directoryService->toggle($league);

        return to_route('command-center.leagues.index')
            ->with('status', 'Estado de la liga actualizado.');
    }
}

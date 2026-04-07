<?php

namespace App\Http\Controllers\Web\League;

use App\Http\Controllers\Controller;
use App\Models\LeagueSession;
use App\Services\LeagueOperations\LeagueCompetitionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function __construct(
        private readonly LeagueCompetitionService $competition,
    ) {}

    public function destroy(Request $request, LeagueSession $session): RedirectResponse
    {
        $this->competition->destroySession($request->user(), $session);

        return redirect()
            ->route('league.modules.show', ['module' => 'stats'])
            ->with('status', 'Jornada eliminada.');
    }
}

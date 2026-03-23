<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Tenancy\LeagueContextResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ActiveLeagueController extends Controller
{
    public function __invoke(Request $request, LeagueContextResolver $leagueContextResolver): RedirectResponse
    {
        $validated = $request->validate([
            'league_id' => ['required', 'integer', 'exists:leagues,id'],
        ]);

        abort_unless(
            $leagueContextResolver->switchActiveLeague($request->user(), $validated['league_id']),
            403,
        );

        return redirect()
            ->route('dashboard')
            ->with('status', 'Liga activa actualizada.');
    }
}

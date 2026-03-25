<?php

namespace App\Http\Controllers\Web;

use App\Enums\LeagueMembershipRole;
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

        $context = $leagueContextResolver->contextFor($request->user()->fresh());
        $activeLeague = $context['active_league'];
        $activeRole = $activeLeague !== null
            ? LeagueMembershipRole::from($activeLeague['role'])
            : null;

        $route = $activeLeague !== null
            && $activeLeague['is_active']
            && $activeRole?->canAccessOperationalModules()
                ? 'league.panel.index'
                : 'dashboard';

        return redirect()->route($route)->with('status', 'Liga activa actualizada.');
    }
}

<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\LeagueOperations\LeagueHomeService;
use App\Services\Tenancy\LeagueContextResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly LeagueHomeService $leagueHomeService,
        private readonly LeagueContextResolver $leagueContextResolver,
    ) {}

    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $tenancy = $this->leagueContextResolver->contextFor($user);

        if ($tenancy['can_access_modules'] && ! $tenancy['can_switch']) {
            return redirect()->route('league.panel.index');
        }

        return Inertia::render('Dashboard', [
            'leagueHome' => $this->leagueHomeService->pageData($user),
        ]);
    }
}

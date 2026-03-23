<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\LeagueContextResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRegularAppAccess
{
    public function __construct(
        private readonly LeagueContextResolver $leagueContextResolver,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->isGeneralAdmin()) {
            return redirect()->route('command-center.dashboard');
        }

        if ($user && $this->leagueContextResolver->hasBlockedAccess($user)) {
            return redirect()->route('app.unavailable');
        }

        return $next($request);
    }
}

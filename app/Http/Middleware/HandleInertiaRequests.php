<?php

namespace App\Http\Middleware;

use App\Http\Resources\V1\UserResource;
use App\Services\Branding\AppBrandingService;
use App\Services\Tenancy\LeagueContextResolver;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $brandingService = app(AppBrandingService::class);
        $leagueContextResolver = app(LeagueContextResolver::class);

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'branding' => $brandingService->branding(),
            'auth' => [
                'user' => $user ? (new UserResource($user))->resolve($request) : null,
            ],
            'tenancy' => $user ? $leagueContextResolver->contextFor($user) : null,
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}

<?php

namespace App\Http\Controllers\Web\CommandCenter;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\CommandCenter\StoreLeagueRequest;
use App\Http\Requests\Web\CommandCenter\UpdateLeagueRequest;
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

    public function store(
        StoreLeagueRequest $request,
        CommandCenterLeagueDirectoryService $directoryService,
    ): RedirectResponse {
        $directoryService->create(
            $request->user(),
            $request->string('name')->value(),
            $request->input('emoji'),
        );

        return to_route('command-center.leagues.index')
            ->with('status', 'Liga creada correctamente.');
    }

    public function update(
        UpdateLeagueRequest $request,
        League $league,
        CommandCenterLeagueDirectoryService $directoryService,
    ): RedirectResponse {
        if ($request->has('name')) {
            $directoryService->updateName(
                $league,
                $request->string('name')->value(),
            );

            return to_route('command-center.leagues.index')
                ->with('status', 'Nombre de la liga actualizado.');
        }

        $directoryService->toggle($league);

        return to_route('command-center.leagues.index')
            ->with('status', 'Estado de la liga actualizado.');
    }
}

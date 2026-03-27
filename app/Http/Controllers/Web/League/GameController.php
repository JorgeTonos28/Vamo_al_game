<?php

namespace App\Http\Controllers\Web\League;

use App\Http\Controllers\Controller;
use App\Models\LeagueSessionEntry;
use App\Services\LeagueOperations\LeagueCompetitionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function __construct(
        private readonly LeagueCompetitionService $competition,
    ) {}

    public function draft(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mode' => ['required', 'string', 'in:auto,arrival,manual'],
            'assignments' => ['array'],
        ]);

        $this->competition->confirmDraft(
            $request->user(),
            $validated['mode'],
            $validated['assignments'] ?? [],
        );

        return back();
    }

    public function teamPoint(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'team_side' => ['required', 'string', 'in:A,B'],
        ]);

        $this->competition->addTeamPoint($request->user(), $validated['team_side']);

        return back();
    }

    public function playerPoint(Request $request, LeagueSessionEntry $entry): RedirectResponse
    {
        $validated = $request->validate([
            'points' => ['required', 'integer', 'in:1,2,3'],
        ]);

        $this->competition->addPlayerPoint($request->user(), $entry, $validated['points']);

        return back();
    }

    public function revertPlayerPoint(Request $request, LeagueSessionEntry $entry): RedirectResponse
    {
        $validated = $request->validate([
            'points' => ['required', 'integer', 'in:1,2,3'],
        ]);

        $this->competition->revertPlayerPoints($request->user(), $entry, $validated['points']);

        return back();
    }

    public function removePlayer(Request $request, LeagueSessionEntry $entry): RedirectResponse
    {
        $this->competition->removePlayer($request->user(), $entry);

        return back();
    }

    public function undo(Request $request): RedirectResponse
    {
        $this->competition->undoLastAction($request->user());

        return back();
    }

    public function finish(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'winner_side' => ['nullable', 'string', 'in:A,B'],
        ]);

        $this->competition->finishCurrentGame($request->user(), $validated['winner_side'] ?? null);

        return back();
    }

    public function configureClock(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'duration_seconds' => ['required', 'integer', 'min:60', 'max:7200'],
        ]);

        $this->competition->configureClock($request->user(), $validated['duration_seconds']);

        return back();
    }

    public function startClock(Request $request): RedirectResponse
    {
        $this->competition->startClock($request->user());

        return back();
    }

    public function pauseClock(Request $request): RedirectResponse
    {
        $this->competition->pauseClock($request->user());

        return back();
    }

    public function resetClock(Request $request): RedirectResponse
    {
        $this->competition->resetClock($request->user());

        return back();
    }

    public function endSession(Request $request): RedirectResponse
    {
        $this->competition->endSession($request->user());

        return back();
    }

    public function reset(Request $request): RedirectResponse
    {
        $this->competition->resetCurrentGame($request->user());

        return back();
    }
}

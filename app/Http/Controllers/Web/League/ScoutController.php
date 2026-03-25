<?php

namespace App\Http\Controllers\Web\League;

use App\Http\Controllers\Controller;
use App\Models\LeaguePlayer;
use App\Services\LeagueOperations\LeagueCompetitionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ScoutController extends Controller
{
    public function __construct(
        private readonly LeagueCompetitionService $competition,
    ) {}

    public function update(Request $request, LeaguePlayer $player): RedirectResponse
    {
        $validated = $request->validate([
            'position' => ['nullable', 'string', 'max:50'],
            'role' => ['nullable', 'string', 'max:50'],
            'offensive_consistency' => ['nullable', 'string', 'max:50'],
            'speed_rating' => ['required', 'integer', 'min:0', 'max:5'],
            'dribbling_rating' => ['required', 'integer', 'min:0', 'max:5'],
            'scoring_rating' => ['required', 'integer', 'min:0', 'max:5'],
            'team_play_rating' => ['required', 'integer', 'min:0', 'max:5'],
            'court_knowledge_rating' => ['required', 'integer', 'min:0', 'max:5'],
            'defense_rating' => ['required', 'integer', 'min:0', 'max:5'],
            'triples_rating' => ['required', 'integer', 'min:0', 'max:5'],
        ]);

        $this->competition->updateScoutProfile($request->user(), $player, $validated);

        return back();
    }
}

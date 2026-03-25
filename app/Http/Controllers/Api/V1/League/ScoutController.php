<?php

namespace App\Http\Controllers\Api\V1\League;

use App\Http\Controllers\Controller;
use App\Models\LeaguePlayer;
use App\Services\LeagueOperations\LeagueCompetitionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScoutController extends Controller
{
    public function __construct(
        private readonly LeagueCompetitionService $competition,
    ) {}

    public function update(Request $request, LeaguePlayer $player): JsonResponse
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

        return ApiResponse::success($request, $this->competition->scoutPageData($request->user()), 'Perfil de scout actualizado.');
    }
}

<?php

namespace App\Http\Controllers\Api\V1\League;

use App\Http\Controllers\Controller;
use App\Models\LeagueSessionEntry;
use App\Services\LeagueOperations\LeagueCompetitionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function __construct(
        private readonly LeagueCompetitionService $competition,
    ) {}

    public function draft(Request $request): JsonResponse
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

        return ApiResponse::success($request, $this->competition->gamePageData($request->user()), 'Juego iniciado.');
    }

    public function teamPoint(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'team_side' => ['required', 'string', 'in:A,B'],
        ]);

        $this->competition->addTeamPoint($request->user(), $validated['team_side']);

        return ApiResponse::success($request, $this->competition->gamePageData($request->user()), 'Punto agregado al equipo.');
    }

    public function playerPoint(Request $request, LeagueSessionEntry $entry): JsonResponse
    {
        $validated = $request->validate([
            'points' => ['required', 'integer', 'in:1,2,3'],
        ]);

        $this->competition->addPlayerPoint($request->user(), $entry, $validated['points']);

        return ApiResponse::success($request, $this->competition->gamePageData($request->user()), 'Punto agregado al jugador.');
    }

    public function revertPlayerPoint(Request $request, LeagueSessionEntry $entry): JsonResponse
    {
        $validated = $request->validate([
            'points' => ['required', 'integer', 'in:1,2,3'],
        ]);

        $this->competition->revertPlayerPoints($request->user(), $entry, $validated['points']);

        return ApiResponse::success($request, $this->competition->gamePageData($request->user()), 'Puntos revertidos.');
    }

    public function removePlayer(Request $request, LeagueSessionEntry $entry): JsonResponse
    {
        $this->competition->removePlayer($request->user(), $entry);

        return ApiResponse::success($request, $this->competition->gamePageData($request->user()), 'Jugador retirado del juego actual.');
    }

    public function undo(Request $request): JsonResponse
    {
        $this->competition->undoLastAction($request->user());

        return ApiResponse::success($request, $this->competition->gamePageData($request->user()), 'Ultima accion revertida.');
    }

    public function finish(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'winner_side' => ['nullable', 'string', 'in:A,B'],
        ]);

        $this->competition->finishCurrentGame($request->user(), $validated['winner_side'] ?? null);

        return ApiResponse::success($request, $this->competition->gamePageData($request->user()), 'Juego cerrado.');
    }

    public function configureClock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'duration_seconds' => ['required', 'integer', 'min:60', 'max:7200'],
        ]);

        $this->competition->configureClock($request->user(), $validated['duration_seconds']);

        return ApiResponse::success($request, $this->competition->gamePageData($request->user()), 'Cronometro actualizado.');
    }

    public function startClock(Request $request): JsonResponse
    {
        $this->competition->startClock($request->user());

        return ApiResponse::success($request, $this->competition->gamePageData($request->user()), 'Cronometro iniciado.');
    }

    public function pauseClock(Request $request): JsonResponse
    {
        $this->competition->pauseClock($request->user());

        return ApiResponse::success($request, $this->competition->gamePageData($request->user()), 'Cronometro pausado.');
    }

    public function resetClock(Request $request): JsonResponse
    {
        $this->competition->resetClock($request->user());

        return ApiResponse::success($request, $this->competition->gamePageData($request->user()), 'Cronometro reiniciado.');
    }

    public function endSession(Request $request): JsonResponse
    {
        $this->competition->endSession($request->user());

        return ApiResponse::success($request, $this->competition->gamePageData($request->user()), 'Jornada cerrada.');
    }

    public function reset(Request $request): JsonResponse
    {
        $this->competition->resetCurrentGame($request->user());

        return ApiResponse::success($request, $this->competition->gamePageData($request->user()), 'Juego actual limpiado.');
    }
}

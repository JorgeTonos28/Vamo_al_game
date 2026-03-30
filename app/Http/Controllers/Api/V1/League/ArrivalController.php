<?php

namespace App\Http\Controllers\Api\V1\League;

use App\Http\Controllers\Controller;
use App\Models\LeaguePlayer;
use App\Models\LeagueSessionEntry;
use App\Services\LeagueOperations\LeagueArrivalService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArrivalController extends Controller
{
    public function __construct(
        private readonly LeagueArrivalService $arrivalService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $request,
            $this->arrivalService->pageData($request->user()),
            'Modulo Llegada cargado.',
        );
    }

    public function togglePlayer(Request $request, LeaguePlayer $player): JsonResponse
    {
        $validated = $request->validate([
            'paid' => ['nullable', 'boolean'],
        ]);

        $this->arrivalService->togglePlayerArrival(
            $request->user(),
            $player,
            $validated['paid'] ?? null,
        );

        return ApiResponse::success(
            $request,
            $this->arrivalService->pageData($request->user()),
            'Llegada actualizada.',
        );
    }

    public function storeGuest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'guest_name' => ['required', 'string', 'max:80'],
        ]);

        $this->arrivalService->storeGuest($request->user(), $validated['guest_name']);

        return ApiResponse::success(
            $request,
            $this->arrivalService->pageData($request->user()),
            'Invitado agregado a la lista de llegada.',
            201,
        );
    }

    public function updateGuest(Request $request, LeagueSessionEntry $entry): JsonResponse
    {
        $validated = $request->validate([
            'guest_fee_paid' => ['required', 'boolean'],
        ]);

        $this->arrivalService->updateGuestPayment(
            $request->user(),
            $entry,
            $validated['guest_fee_paid'],
        );

        return ApiResponse::success(
            $request,
            $this->arrivalService->pageData($request->user()),
            'Cobro del invitado actualizado.',
        );
    }

    public function destroyGuest(Request $request, LeagueSessionEntry $entry): JsonResponse
    {
        $this->arrivalService->deleteGuest($request->user(), $entry);

        return ApiResponse::success(
            $request,
            $this->arrivalService->pageData($request->user()),
            'Invitado removido de la lista de llegada.',
        );
    }

    public function prepare(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'guest_payments' => ['array'],
            'guest_payments.*.id' => ['required', 'integer', 'exists:league_session_entries,id'],
            'guest_payments.*.paid' => ['required', 'boolean'],
        ]);

        $this->arrivalService->prepareSession(
            $request->user(),
            $validated['guest_payments'] ?? [],
        );

        return ApiResponse::success(
            $request,
            $this->arrivalService->pageData($request->user()),
            'Jornada preparada. La cola inicial ya quedo lista para el modulo Juego.',
        );
    }

    public function reset(Request $request): JsonResponse
    {
        $this->arrivalService->resetSession($request->user());

        return ApiResponse::success(
            $request,
            $this->arrivalService->pageData($request->user()),
            'Lista de llegada reiniciada.',
        );
    }

    public function reorderQueue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entry_ids' => ['required', 'array', 'min:1'],
            'entry_ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $this->arrivalService->reorderPregameQueue(
            $request->user(),
            $validated['entry_ids'],
        );

        return ApiResponse::success(
            $request,
            $this->arrivalService->pageData($request->user()),
            'Cola inicial reordenada.',
        );
    }
}

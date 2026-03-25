<?php

namespace App\Http\Controllers\Web\League;

use App\Http\Controllers\Controller;
use App\Models\LeaguePlayer;
use App\Models\LeagueSessionEntry;
use App\Services\LeagueOperations\LeagueArrivalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ArrivalController extends Controller
{
    public function __construct(
        private readonly LeagueArrivalService $arrivalService,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('league/Arrival', [
            'module' => $this->arrivalService->pageData($request->user()),
        ]);
    }

    public function togglePlayer(Request $request, LeaguePlayer $player): RedirectResponse
    {
        $validated = $request->validate([
            'paid' => ['nullable', 'boolean'],
        ]);

        $this->arrivalService->togglePlayerArrival(
            $request->user(),
            $player,
            $validated['paid'] ?? null,
        );

        return back()->with('status', 'Llegada actualizada.');
    }

    public function storeGuest(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'guest_name' => ['required', 'string', 'max:80'],
        ]);

        $this->arrivalService->storeGuest(
            $request->user(),
            $validated['guest_name'],
        );

        return back()->with('status', 'Invitado agregado a la lista de llegada.');
    }

    public function updateGuest(Request $request, LeagueSessionEntry $entry): RedirectResponse
    {
        $validated = $request->validate([
            'guest_fee_paid' => ['required', 'boolean'],
        ]);

        $this->arrivalService->updateGuestPayment(
            $request->user(),
            $entry,
            $validated['guest_fee_paid'],
        );

        return back()->with('status', 'Cobro del invitado actualizado.');
    }

    public function destroyGuest(Request $request, LeagueSessionEntry $entry): RedirectResponse
    {
        $this->arrivalService->deleteGuest($request->user(), $entry);

        return back()->with('status', 'Invitado removido de la lista de llegada.');
    }

    public function prepare(Request $request): RedirectResponse
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

        return back()->with('status', 'Jornada preparada. La cola inicial ya quedo lista para el modulo Juego.');
    }

    public function reset(Request $request): RedirectResponse
    {
        $this->arrivalService->resetSession($request->user());

        return back()->with('status', 'Lista de llegada reiniciada.');
    }
}

<?php

namespace App\Http\Controllers\Web\League;

use App\Http\Controllers\Controller;
use App\Services\LeagueOperations\LeagueCompetitionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class QueueController extends Controller
{
    public function __construct(
        private readonly LeagueCompetitionService $competition,
    ) {}

    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'entry_ids' => ['required', 'array', 'min:1'],
            'entry_ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $this->competition->reorderQueue(
            $request->user(),
            $validated['entry_ids'],
        );

        return back()->with('status', 'Cola operativa reordenada.');
    }
}

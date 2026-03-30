<?php

namespace App\Http\Controllers\Api\V1\League;

use App\Http\Controllers\Controller;
use App\Services\LeagueOperations\LeagueCompetitionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QueueController extends Controller
{
    public function __construct(
        private readonly LeagueCompetitionService $competition,
    ) {}

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entry_ids' => ['required', 'array', 'min:1'],
            'entry_ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $this->competition->reorderQueue(
            $request->user(),
            $validated['entry_ids'],
        );

        return ApiResponse::success(
            $request,
            $this->competition->queuePageData($request->user()),
            'Cola operativa reordenada.',
        );
    }
}

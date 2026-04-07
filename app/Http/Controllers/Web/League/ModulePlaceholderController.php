<?php

namespace App\Http\Controllers\Web\League;

use App\Http\Controllers\Controller;
use App\Services\LeagueOperations\LeagueCompetitionService;
use App\Services\LeagueOperations\LeagueOperationsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ModulePlaceholderController extends Controller
{
    private const MODULE_MAP = [
        'juego' => ['label' => 'Juego'],
        'cola' => ['label' => 'Cola'],
        'stats' => ['label' => 'Stats'],
        'tabla' => ['label' => 'Tabla'],
        'temporada' => ['label' => 'Temporada'],
        'scout' => ['label' => 'Scout'],
        'torneo' => ['label' => 'Torneo'],
        'anotador' => ['label' => 'Anotador'],
        'votos' => ['label' => 'Votos'],
        'post' => ['label' => 'Post'],
    ];

    public function __construct(
        private readonly LeagueOperationsService $operations,
        private readonly LeagueCompetitionService $competition,
    ) {}

    public function show(Request $request, string $module): Response
    {
        abort_unless(array_key_exists($module, self::MODULE_MAP), 404);

        if (in_array($module, ['juego', 'cola', 'stats', 'tabla', 'temporada', 'scout'], true)) {
            $page = match ($module) {
                'juego' => ['component' => 'league/Game', 'payload' => $this->competition->gamePageData(
                    $request->user(),
                    $request->integer('abandoned_game_id') ?: null,
                )],
                'cola' => ['component' => 'league/Queue', 'payload' => $this->competition->queuePageData($request->user(), $request->integer('session_id') ?: null)],
                'stats' => ['component' => 'league/Stats', 'payload' => $this->competition->statsPageData($request->user(), $request->integer('session_id') ?: null)],
                'tabla' => ['component' => 'league/Table', 'payload' => $this->competition->tablePageData($request->user(), $request->integer('session_id') ?: null)],
                'temporada' => ['component' => 'league/Season', 'payload' => $this->competition->seasonPageData($request->user())],
                'scout' => ['component' => 'league/Scout', 'payload' => $this->competition->scoutPageData($request->user())],
            };

            return Inertia::render($page['component'], $page['payload']);
        }

        $context = $this->operations->requireOperationalContext($request->user());

        return Inertia::render('league/ModulePlaceholder', [
            'module' => [
                'key' => $module,
                'label' => self::MODULE_MAP[$module]['label'],
                'league' => [
                    'id' => $context['league']->id,
                    'name' => $context['league']->name,
                    'emoji' => $context['league']->emoji,
                ],
                'role' => [
                    'value' => $context['role']->value,
                    'label' => $context['role']->label(),
                    'can_manage' => $context['role']->canManageLeague(),
                ],
            ],
        ]);
    }
}

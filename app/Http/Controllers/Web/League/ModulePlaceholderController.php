<?php

namespace App\Http\Controllers\Web\League;

use App\Http\Controllers\Controller;
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
    ) {}

    public function show(Request $request, string $module): Response
    {
        abort_unless(array_key_exists($module, self::MODULE_MAP), 404);

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

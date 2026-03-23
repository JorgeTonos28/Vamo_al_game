<?php

namespace App\Http\Controllers\Web\CommandCenter;

use App\Http\Controllers\Controller;
use App\Services\CommandCenter\CommandCenterMetricsService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(CommandCenterMetricsService $commandCenterMetricsService): Response
    {
        return Inertia::render('command-center/Dashboard', [
            'metrics' => $commandCenterMetricsService->totals(),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Web\CommandCenter;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\UpdateBrandingSettingsRequest;
use App\Services\Branding\AppBrandingService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(): RedirectResponse
    {
        return to_route('command-center.settings.profile.edit');
    }

    public function appearance(AppBrandingService $brandingService): Response
    {
        return Inertia::render('command-center/settings/Appearance', [
            'branding' => $brandingService->branding(),
        ]);
    }

    public function update(
        UpdateBrandingSettingsRequest $request,
        AppBrandingService $brandingService,
    ): RedirectResponse {
        $brandingService->update([
            'logo' => $request->file('logo'),
            'favicon' => $request->file('favicon'),
        ]);

        return to_route('command-center.settings.appearance.edit')
            ->with('status', 'Branding actualizado correctamente.');
    }
}

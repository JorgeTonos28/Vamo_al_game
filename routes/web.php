<?php

use App\Http\Controllers\Web\ActiveLeagueController;
use App\Http\Controllers\Web\AppUnavailableController;
use App\Http\Controllers\Web\Auth\GoogleAuthController;
use App\Http\Controllers\Web\BrandingAssetController;
use App\Http\Controllers\Web\CommandCenter\DashboardController as CommandCenterDashboardController;
use App\Http\Controllers\Web\CommandCenter\LeagueController as CommandCenterLeagueController;
use App\Http\Controllers\Web\CommandCenter\Settings\ProfileController as CommandCenterSettingsProfileController;
use App\Http\Controllers\Web\CommandCenter\Settings\SecurityController as CommandCenterSettingsSecurityController;
use App\Http\Controllers\Web\CommandCenter\SettingsController as CommandCenterSettingsController;
use App\Http\Controllers\Web\CommandCenter\UserController as CommandCenterUserController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\InvitationAcceptanceController;
use App\Http\Middleware\EnsureGeneralAdmin;
use App\Http\Middleware\EnsureRegularAppAccess;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])
    ->middleware('guest')
    ->name('auth.google.redirect');
Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])
    ->middleware('guest')
    ->name('auth.google.callback');

Route::get('branding/logo', [BrandingAssetController::class, 'logo'])
    ->name('branding.logo.show');
Route::get('branding/favicon', [BrandingAssetController::class, 'favicon'])
    ->name('branding.favicon.show');

Route::get('invitations/{invitation}/accept', [InvitationAcceptanceController::class, 'show'])
    ->name('invitations.accept');
Route::post('invitations/{invitation}/accept', [InvitationAcceptanceController::class, 'store'])
    ->name('invitations.store');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('app-unavailable', AppUnavailableController::class)
        ->name('app.unavailable');

    Route::prefix('command-center')
        ->as('command-center.')
        ->middleware(EnsureGeneralAdmin::class)
        ->group(function (): void {
            Route::get('/', CommandCenterDashboardController::class)
                ->name('dashboard');
            Route::get('users', [CommandCenterUserController::class, 'index'])
                ->name('users.index');
            Route::post('users', [CommandCenterUserController::class, 'store'])
                ->name('users.store');
            Route::get('leagues', [CommandCenterLeagueController::class, 'index'])
                ->name('leagues.index');
            Route::patch('leagues/{league}', [CommandCenterLeagueController::class, 'update'])
                ->name('leagues.update');
            Route::get('settings', [CommandCenterSettingsController::class, 'index'])
                ->name('settings.index');
            Route::prefix('settings')
                ->as('settings.')
                ->group(function (): void {
                    Route::get('profile', CommandCenterSettingsProfileController::class)
                        ->name('profile.edit');
                    Route::get('security', CommandCenterSettingsSecurityController::class)
                        ->name('security.edit');
                    Route::get('appearance', [CommandCenterSettingsController::class, 'appearance'])
                        ->name('appearance.edit');
                    Route::post('/', [CommandCenterSettingsController::class, 'update'])
                        ->name('update');
                });
        });

    Route::post('active-league', ActiveLeagueController::class)->name('active-league.store');

    Route::middleware(EnsureRegularAppAccess::class)->group(function (): void {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
    });
});

require __DIR__.'/settings.php';

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
use App\Http\Controllers\Web\League\ArrivalController as LeagueArrivalController;
use App\Http\Controllers\Web\League\GameController as LeagueGameController;
use App\Http\Controllers\Web\League\ManagementController as LeagueManagementController;
use App\Http\Controllers\Web\League\ModulePlaceholderController as LeagueModulePlaceholderController;
use App\Http\Controllers\Web\League\PanelController as LeaguePanelController;
use App\Http\Controllers\Web\League\QueueController as LeagueQueueController;
use App\Http\Controllers\Web\League\ScoutController as LeagueScoutController;
use App\Http\Controllers\Web\League\SessionController as LeagueSessionController;
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
            Route::post('users/{user}/leagues', [CommandCenterUserController::class, 'assignLeague'])
                ->name('users.leagues.store');
            Route::get('leagues', [CommandCenterLeagueController::class, 'index'])
                ->name('leagues.index');
            Route::post('leagues', [CommandCenterLeagueController::class, 'store'])
                ->name('leagues.store');
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

        Route::prefix('liga')
            ->as('league.')
            ->group(function (): void {
                Route::get('panel', [LeaguePanelController::class, 'index'])
                    ->name('panel.index');
                Route::get('llegada', [LeagueArrivalController::class, 'index'])
                    ->name('arrival.index');
                Route::post('llegada/players/{player}/toggle', [LeagueArrivalController::class, 'togglePlayer'])
                    ->name('arrival.players.toggle');
                Route::post('llegada/guests', [LeagueArrivalController::class, 'storeGuest'])
                    ->name('arrival.guests.store');
                Route::patch('llegada/guests/{entry}', [LeagueArrivalController::class, 'updateGuest'])
                    ->name('arrival.guests.update');
                Route::delete('llegada/guests/{entry}', [LeagueArrivalController::class, 'destroyGuest'])
                    ->name('arrival.guests.destroy');
                Route::post('llegada/prepare', [LeagueArrivalController::class, 'prepare'])
                    ->name('arrival.prepare');
                Route::post('llegada/reset', [LeagueArrivalController::class, 'reset'])
                    ->name('arrival.reset');
                Route::post('llegada/cola/reorder', [LeagueArrivalController::class, 'reorderQueue'])
                    ->name('arrival.queue.reorder');

                Route::get('gestion', [LeagueManagementController::class, 'index'])
                    ->name('management.index');
                Route::post('gestion/payments/{player}', [LeagueManagementController::class, 'storePayment'])
                    ->name('management.payments.store');
                Route::delete('gestion/payments/{player}', [LeagueManagementController::class, 'destroyPayment'])
                    ->name('management.payments.destroy');
                Route::post('gestion/expenses', [LeagueManagementController::class, 'storeExpense'])
                    ->name('management.expenses.store');
                Route::delete('gestion/expenses/{expense}', [LeagueManagementController::class, 'destroyExpense'])
                    ->name('management.expenses.destroy');
                Route::post('gestion/settings', [LeagueManagementController::class, 'updateSettings'])
                    ->name('management.settings.update');
                Route::get('gestion/report', [LeagueManagementController::class, 'report'])
                    ->name('management.report');
                Route::post('gestion/referrals', [LeagueManagementController::class, 'storeReferral'])
                    ->name('management.referrals.store');
                Route::delete('gestion/referrals/{referral}', [LeagueManagementController::class, 'destroyReferral'])
                    ->name('management.referrals.destroy');
                Route::post('gestion/players', [LeagueManagementController::class, 'storePlayer'])
                    ->name('management.players.store');
                Route::patch('gestion/players/{player}', [LeagueManagementController::class, 'updatePlayer'])
                    ->name('management.players.update');
                Route::patch('gestion/players/{player}/status', [LeagueManagementController::class, 'updatePlayerStatus'])
                    ->name('management.players.status.update');

                Route::get('modulos/{module}', [LeagueModulePlaceholderController::class, 'show'])
                    ->name('modules.show');
                Route::post('modulos/juego/draft', [LeagueGameController::class, 'draft'])
                    ->name('modules.game.draft');
                Route::post('modulos/juego/team-point', [LeagueGameController::class, 'teamPoint'])
                    ->name('modules.game.team-point');
                Route::post('modulos/juego/players/{entry}/point', [LeagueGameController::class, 'playerPoint'])
                    ->name('modules.game.players.point');
                Route::post('modulos/juego/players/{entry}/revert', [LeagueGameController::class, 'revertPlayerPoint'])
                    ->name('modules.game.players.revert');
                Route::post('modulos/juego/players/{entry}/remove', [LeagueGameController::class, 'removePlayer'])
                    ->name('modules.game.players.remove');
                Route::post('modulos/juego/undo', [LeagueGameController::class, 'undo'])
                    ->name('modules.game.undo');
                Route::post('modulos/juego/finish', [LeagueGameController::class, 'finish'])
                    ->name('modules.game.finish');
                Route::post('modulos/juego/clock', [LeagueGameController::class, 'configureClock'])
                    ->name('modules.game.clock.configure');
                Route::post('modulos/juego/clock/start', [LeagueGameController::class, 'startClock'])
                    ->name('modules.game.clock.start');
                Route::post('modulos/juego/clock/pause', [LeagueGameController::class, 'pauseClock'])
                    ->name('modules.game.clock.pause');
                Route::post('modulos/juego/clock/reset', [LeagueGameController::class, 'resetClock'])
                    ->name('modules.game.clock.reset');
                Route::post('modulos/juego/end-session', [LeagueGameController::class, 'endSession'])
                    ->name('modules.game.end-session');
                Route::post('modulos/juego/reset', [LeagueGameController::class, 'reset'])
                    ->name('modules.game.reset');
                Route::post('modulos/juego/abandoned/{game}/resolve', [LeagueGameController::class, 'resolveAbandoned'])
                    ->name('modules.game.abandoned.resolve');
                Route::post('modulos/cola/reorder', [LeagueQueueController::class, 'reorder'])
                    ->name('modules.queue.reorder');
                Route::delete('modulos/stats/sessions/{session}', [LeagueSessionController::class, 'destroy'])
                    ->name('modules.stats.sessions.destroy');
                Route::patch('modulos/scout/players/{player}', [LeagueScoutController::class, 'update'])
                    ->name('modules.scout.players.update');
            });
    });
});

require __DIR__.'/settings.php';

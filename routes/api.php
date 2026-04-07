<?php

use App\Http\Controllers\Api\V1\ActiveLeagueController;
use App\Http\Controllers\Api\V1\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Api\V1\Auth\GoogleExchangeController;
use App\Http\Controllers\Api\V1\Auth\RegisteredUserController;
use App\Http\Controllers\Api\V1\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Api\V1\BrandingController;
use App\Http\Controllers\Api\V1\CommandCenter\DashboardController as CommandCenterDashboardController;
use App\Http\Controllers\Api\V1\CommandCenter\LeagueController as CommandCenterLeagueController;
use App\Http\Controllers\Api\V1\CommandCenter\SettingsController as CommandCenterSettingsController;
use App\Http\Controllers\Api\V1\CommandCenter\UserController as CommandCenterUserController;
use App\Http\Controllers\Api\V1\CurrentUserController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\League\ArrivalController as LeagueArrivalController;
use App\Http\Controllers\Api\V1\League\GameController as LeagueGameController;
use App\Http\Controllers\Api\V1\League\HomeController as LeagueHomeController;
use App\Http\Controllers\Api\V1\League\ManagementController as LeagueManagementController;
use App\Http\Controllers\Api\V1\League\QueueController as LeagueQueueController;
use App\Http\Controllers\Api\V1\League\ScoutController as LeagueScoutController;
use App\Http\Controllers\Api\V1\League\SessionController as LeagueSessionController;
use App\Http\Controllers\Api\V1\Settings\EmailVerificationNotificationController;
use App\Http\Controllers\Api\V1\Settings\PasswordController;
use App\Http\Controllers\Api\V1\Settings\ProfileController as SettingsProfileController;
use App\Http\Controllers\Api\V1\Settings\TwoFactorController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Middleware\EnsureGeneralAdmin;
use App\Http\Middleware\ForceJsonResponse;
use App\Services\LeagueOperations\LeagueCompetitionService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::middleware([ForceJsonResponse::class])
    ->prefix('v1')
    ->name('api.v1.')
    ->group(function (): void {
        Route::get('health', HealthController::class)->name('health.show');
        Route::get('branding', BrandingController::class)->name('branding.show');

        Route::prefix('auth')
            ->name('auth.')
            ->group(function (): void {
                if (Features::enabled(Features::registration())) {
                    Route::post('register', RegisteredUserController::class)
                        ->name('register');
                }

                Route::post('google/exchange', GoogleExchangeController::class)
                    ->name('google.exchange');

                Route::post('login', [AuthenticatedSessionController::class, 'store'])
                    ->name('login');
                Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'store'])
                    ->name('two-factor.store');
            });

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::prefix('auth')
                ->name('auth.')
                ->group(function (): void {
                    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
                        ->name('logout');
                });

            Route::get('me', CurrentUserController::class)->name('me.show');
            Route::patch('me/active-league', ActiveLeagueController::class)
                ->name('me.active-league.update');
            Route::get('users/{user}', UserController::class)
                ->middleware('can:view,user')
                ->name('users.show');
            Route::prefix('league')
                ->name('league.')
                ->group(function (): void {
                    Route::get('home', [LeagueHomeController::class, 'show'])
                        ->name('home.show');
                    Route::get('arrival', [LeagueArrivalController::class, 'show'])
                        ->name('arrival.show');
                    Route::post('arrival/players/{player}/toggle', [LeagueArrivalController::class, 'togglePlayer'])
                        ->name('arrival.players.toggle');
                    Route::post('arrival/guests', [LeagueArrivalController::class, 'storeGuest'])
                        ->name('arrival.guests.store');
                    Route::patch('arrival/guests/{entry}', [LeagueArrivalController::class, 'updateGuest'])
                        ->name('arrival.guests.update');
                    Route::delete('arrival/guests/{entry}', [LeagueArrivalController::class, 'destroyGuest'])
                        ->name('arrival.guests.destroy');
                    Route::post('arrival/prepare', [LeagueArrivalController::class, 'prepare'])
                        ->name('arrival.prepare');
                    Route::post('arrival/reset', [LeagueArrivalController::class, 'reset'])
                        ->name('arrival.reset');
                    Route::post('arrival/queue/reorder', [LeagueArrivalController::class, 'reorderQueue'])
                        ->name('arrival.queue.reorder');

                    Route::get('management', [LeagueManagementController::class, 'show'])
                        ->name('management.show');
                    Route::post('management/payments/{player}', [LeagueManagementController::class, 'storePayment'])
                        ->name('management.payments.store');
                    Route::delete('management/payments/{player}', [LeagueManagementController::class, 'destroyPayment'])
                        ->name('management.payments.destroy');
                    Route::post('management/expenses', [LeagueManagementController::class, 'storeExpense'])
                        ->name('management.expenses.store');
                    Route::delete('management/expenses/{expense}', [LeagueManagementController::class, 'destroyExpense'])
                        ->name('management.expenses.destroy');
                    Route::post('management/settings', [LeagueManagementController::class, 'updateSettings'])
                        ->name('management.settings.update');
                    Route::get('management/report', [LeagueManagementController::class, 'report'])
                        ->name('management.report');
                    Route::post('management/referrals', [LeagueManagementController::class, 'storeReferral'])
                        ->name('management.referrals.store');
                    Route::delete('management/referrals/{referral}', [LeagueManagementController::class, 'destroyReferral'])
                        ->name('management.referrals.destroy');
                    Route::post('management/players', [LeagueManagementController::class, 'storePlayer'])
                        ->name('management.players.store');
                    Route::patch('management/players/{player}', [LeagueManagementController::class, 'updatePlayer'])
                        ->name('management.players.update');
                    Route::patch('management/players/{player}/status', [LeagueManagementController::class, 'updatePlayerStatus'])
                        ->name('management.players.status.update');

                    Route::get('modules/game', fn (Request $request) => ApiResponse::success(
                        $request,
                        app(LeagueCompetitionService::class)->gamePageData(
                            $request->user(),
                            $request->integer('abandoned_game_id') ?: null,
                        ),
                        'Modulo Juego cargado.',
                    ))->name('modules.game.show');
                    Route::get('modules/queue', fn (Request $request) => ApiResponse::success(
                        $request,
                        app(LeagueCompetitionService::class)->queuePageData(
                            $request->user(),
                            $request->integer('session_id') ?: null,
                        ),
                        'Modulo Cola cargado.',
                    ))->name('modules.queue.show');
                    Route::get('modules/stats', fn (Request $request) => ApiResponse::success(
                        $request,
                        app(LeagueCompetitionService::class)->statsPageData(
                            $request->user(),
                            $request->integer('session_id') ?: null,
                        ),
                        'Modulo Stats cargado.',
                    ))->name('modules.stats.show');
                    Route::get('modules/table', fn (Request $request) => ApiResponse::success(
                        $request,
                        app(LeagueCompetitionService::class)->tablePageData(
                            $request->user(),
                            $request->integer('session_id') ?: null,
                        ),
                        'Modulo Tabla cargado.',
                    ))->name('modules.table.show');
                    Route::get('modules/season', fn (Request $request) => ApiResponse::success(
                        $request,
                        app(LeagueCompetitionService::class)->seasonPageData($request->user()),
                        'Modulo Temporada cargado.',
                    ))->name('modules.season.show');
                    Route::get('modules/scout', fn (Request $request) => ApiResponse::success(
                        $request,
                        app(LeagueCompetitionService::class)->scoutPageData($request->user()),
                        'Modulo Scout cargado.',
                    ))->name('modules.scout.show');
                    Route::post('modules/game/draft', [LeagueGameController::class, 'draft'])
                        ->name('modules.game.draft');
                    Route::post('modules/game/team-point', [LeagueGameController::class, 'teamPoint'])
                        ->name('modules.game.team-point');
                    Route::post('modules/game/players/{entry}/point', [LeagueGameController::class, 'playerPoint'])
                        ->name('modules.game.players.point');
                    Route::post('modules/game/players/{entry}/revert', [LeagueGameController::class, 'revertPlayerPoint'])
                        ->name('modules.game.players.revert');
                    Route::post('modules/game/players/{entry}/remove', [LeagueGameController::class, 'removePlayer'])
                        ->name('modules.game.players.remove');
                    Route::post('modules/game/undo', [LeagueGameController::class, 'undo'])
                        ->name('modules.game.undo');
                    Route::post('modules/game/finish', [LeagueGameController::class, 'finish'])
                        ->name('modules.game.finish');
                    Route::post('modules/game/clock', [LeagueGameController::class, 'configureClock'])
                        ->name('modules.game.clock.configure');
                    Route::post('modules/game/clock/start', [LeagueGameController::class, 'startClock'])
                        ->name('modules.game.clock.start');
                    Route::post('modules/game/clock/pause', [LeagueGameController::class, 'pauseClock'])
                        ->name('modules.game.clock.pause');
                    Route::post('modules/game/clock/reset', [LeagueGameController::class, 'resetClock'])
                        ->name('modules.game.clock.reset');
                    Route::post('modules/game/end-session', [LeagueGameController::class, 'endSession'])
                        ->name('modules.game.end-session');
                    Route::post('modules/game/reset', [LeagueGameController::class, 'reset'])
                        ->name('modules.game.reset');
                    Route::post('modules/game/abandoned/{game}/resolve', [LeagueGameController::class, 'resolveAbandoned'])
                        ->name('modules.game.abandoned.resolve');
                    Route::post('modules/queue/reorder', [LeagueQueueController::class, 'reorder'])
                        ->name('modules.queue.reorder');
                    Route::delete('modules/stats/sessions/{session}', [LeagueSessionController::class, 'destroy'])
                        ->name('modules.stats.sessions.destroy');
                    Route::patch('modules/scout/players/{player}', [LeagueScoutController::class, 'update'])
                        ->name('modules.scout.players.update');
                });

            Route::prefix('settings')
                ->name('settings.')
                ->group(function (): void {
                    Route::patch('profile', [SettingsProfileController::class, 'update'])
                        ->name('profile.update');
                    Route::delete('profile', [SettingsProfileController::class, 'destroy'])
                        ->name('profile.destroy');
                    Route::put('password', PasswordController::class)
                        ->name('password.update');
                    Route::post('email/verification-notification', EmailVerificationNotificationController::class)
                        ->name('verification.send');
                    Route::get('two-factor', [TwoFactorController::class, 'show'])
                        ->name('two-factor.show');
                    Route::post('two-factor', [TwoFactorController::class, 'store'])
                        ->name('two-factor.store');
                    Route::get('two-factor/setup', [TwoFactorController::class, 'setup'])
                        ->name('two-factor.setup');
                    Route::post('two-factor/confirm', [TwoFactorController::class, 'confirm'])
                        ->name('two-factor.confirm');
                    Route::delete('two-factor', [TwoFactorController::class, 'destroy'])
                        ->name('two-factor.destroy');
                    Route::get('two-factor/recovery-codes', [TwoFactorController::class, 'recoveryCodes'])
                        ->name('two-factor.recovery-codes.index');
                    Route::post('two-factor/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])
                        ->name('two-factor.recovery-codes.store');
                });

            Route::prefix('command-center')
                ->name('command-center.')
                ->middleware(EnsureGeneralAdmin::class)
                ->group(function (): void {
                    Route::get('dashboard', CommandCenterDashboardController::class)
                        ->name('dashboard.show');
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
                        ->name('settings.show');
                    Route::post('settings', [CommandCenterSettingsController::class, 'update'])
                        ->name('settings.update');
                });
        });
    });

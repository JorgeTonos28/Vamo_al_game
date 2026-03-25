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
use App\Http\Controllers\Api\V1\League\HomeController as LeagueHomeController;
use App\Http\Controllers\Api\V1\League\ManagementController as LeagueManagementController;
use App\Http\Controllers\Api\V1\Settings\EmailVerificationNotificationController;
use App\Http\Controllers\Api\V1\Settings\PasswordController;
use App\Http\Controllers\Api\V1\Settings\ProfileController as SettingsProfileController;
use App\Http\Controllers\Api\V1\Settings\TwoFactorController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Middleware\EnsureGeneralAdmin;
use App\Http\Middleware\ForceJsonResponse;
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

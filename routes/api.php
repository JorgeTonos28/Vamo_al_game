<?php

use App\Http\Controllers\Api\V1\ActiveLeagueController;
use App\Http\Controllers\Api\V1\BrandingController;
use App\Http\Controllers\Api\V1\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Api\V1\Auth\GoogleExchangeController;
use App\Http\Controllers\Api\V1\Auth\RegisteredUserController;
use App\Http\Controllers\Api\V1\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Api\V1\CommandCenter\DashboardController as CommandCenterDashboardController;
use App\Http\Controllers\Api\V1\CommandCenter\LeagueController as CommandCenterLeagueController;
use App\Http\Controllers\Api\V1\CommandCenter\SettingsController as CommandCenterSettingsController;
use App\Http\Controllers\Api\V1\CommandCenter\UserController as CommandCenterUserController;
use App\Http\Controllers\Api\V1\CurrentUserController;
use App\Http\Controllers\Api\V1\HealthController;
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
                    Route::get('leagues', [CommandCenterLeagueController::class, 'index'])
                        ->name('leagues.index');
                    Route::patch('leagues/{league}', [CommandCenterLeagueController::class, 'update'])
                        ->name('leagues.update');
                    Route::get('settings', [CommandCenterSettingsController::class, 'index'])
                        ->name('settings.show');
                    Route::post('settings', [CommandCenterSettingsController::class, 'update'])
                        ->name('settings.update');
                });
        });
    });

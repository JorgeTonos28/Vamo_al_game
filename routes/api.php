<?php

use App\Http\Controllers\Api\V1\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Api\V1\CurrentUserController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Support\Facades\Route;

Route::middleware([ForceJsonResponse::class])
    ->prefix('v1')
    ->name('api.v1.')
    ->group(function (): void {
        Route::get('health', HealthController::class)->name('health.show');

        Route::prefix('auth')
            ->name('auth.')
            ->group(function (): void {
                Route::post('login', [AuthenticatedSessionController::class, 'store'])
                    ->name('login');
            });

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::prefix('auth')
                ->name('auth.')
                ->group(function (): void {
                    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
                        ->name('logout');
                });

            Route::get('me', CurrentUserController::class)->name('me.show');
            Route::get('users/{user}', UserController::class)
                ->middleware('can:view,user')
                ->name('users.show');
        });
    });

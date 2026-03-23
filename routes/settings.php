<?php

use App\Http\Controllers\Web\Settings\ProfileController;
use App\Http\Controllers\Web\Settings\SecurityController;
use App\Http\Middleware\EnsureRegularAppAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');
});

Route::middleware(['auth', 'verified', EnsureRegularAppAccess::class])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');
});

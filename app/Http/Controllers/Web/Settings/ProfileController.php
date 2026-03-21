<?php

namespace App\Http\Controllers\Web\Settings;

use App\Actions\Auth\DeleteUserAccount;
use App\Actions\Auth\UpdateUserProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Settings\ProfileDeleteRequest;
use App\Http\Requests\Web\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    public function update(
        ProfileUpdateRequest $request,
        UpdateUserProfile $updateUserProfile,
    ): RedirectResponse
    {
        $updateUserProfile->handle($request->user(), $request->validated());

        return to_route('profile.edit');
    }

    public function destroy(
        ProfileDeleteRequest $request,
        DeleteUserAccount $deleteUserAccount,
    ): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $deleteUserAccount->handle($user);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

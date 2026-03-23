<?php

namespace App\Http\Controllers\Web\CommandCenter\Settings;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('command-center/settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }
}

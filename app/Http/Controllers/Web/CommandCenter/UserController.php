<?php

namespace App\Http\Controllers\Web\CommandCenter;

use App\Actions\Admin\InviteUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\InviteUserRequest;
use App\Services\CommandCenter\CommandCenterUserDirectoryService;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(CommandCenterUserDirectoryService $directoryService): Response
    {
        $payload = $directoryService->payload();

        return Inertia::render('command-center/Users', [
            'roleOptions' => $payload['role_options'],
            'users' => $payload['users'],
        ]);
    }

    public function store(InviteUserRequest $request, InviteUser $inviteUser): \Illuminate\Http\RedirectResponse
    {
        $inviteUser->handle($request->user(), $request->validated());

        return to_route('command-center.users.index')
            ->with('status', 'Invitacion enviada correctamente.');
    }
}

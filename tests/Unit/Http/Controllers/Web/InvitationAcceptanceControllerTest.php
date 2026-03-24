<?php

namespace Tests\Unit\Http\Controllers\Web;

use App\Actions\Auth\AcceptInvitation;
use App\Http\Controllers\Web\InvitationAcceptanceController;
use App\Http\Requests\Web\Auth\AcceptInvitationRequest;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;

class InvitationAcceptanceControllerTest extends TestCase
{
    public function test_store_regenerates_the_session_after_logging_in_the_invited_user(): void
    {
        $user = User::factory()->make();
        $invitation = new UserInvitation([
            'user_id' => 1,
        ]);

        $session = Mockery::mock(Store::class);
        $session->shouldReceive('regenerate')->once();

        $request = Mockery::mock(AcceptInvitationRequest::class);
        $request->shouldReceive('string')
            ->once()
            ->with('token')
            ->andReturn(str('valid-token'));
        $request->shouldReceive('validated')
            ->once()
            ->andReturn([
                'first_name' => 'Invited',
                'last_name' => 'User',
                'password' => 'TestUSER12345678',
                'password_confirmation' => 'TestUSER12345678',
            ]);
        $request->shouldReceive('session')
            ->once()
            ->andReturn($session);

        $acceptInvitation = Mockery::mock(AcceptInvitation::class);
        $acceptInvitation->shouldReceive('handle')
            ->once()
            ->with($invitation, 'valid-token', Mockery::type('array'))
            ->andReturn($user);

        Auth::shouldReceive('login')
            ->once()
            ->with($user, true);

        $response = app(InvitationAcceptanceController::class)->store(
            $request,
            $invitation,
            $acceptInvitation,
        );

        $this->assertSame(302, $response->getStatusCode());
    }
}

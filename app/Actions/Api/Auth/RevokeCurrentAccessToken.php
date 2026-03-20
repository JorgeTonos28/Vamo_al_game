<?php

namespace App\Actions\Api\Auth;

use Illuminate\Http\Request;

class RevokeCurrentAccessToken
{
    public function handle(Request $request): void
    {
        $request->user()?->currentAccessToken()?->delete();
    }
}

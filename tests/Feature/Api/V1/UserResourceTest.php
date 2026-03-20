<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_is_public(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.api_version', 'v1');
    }

    public function test_users_can_fetch_their_own_protected_resource(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Ionic Dev');

        $this->withToken($token->plainTextToken)
            ->getJson("/api/v1/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_users_can_not_fetch_other_users_protected_resources(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $token = $user->createToken('Ionic Dev');

        $this->withToken($token->plainTextToken)
            ->getJson("/api/v1/users/{$otherUser->id}")
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No tienes permiso para acceder a este recurso.');
    }
}

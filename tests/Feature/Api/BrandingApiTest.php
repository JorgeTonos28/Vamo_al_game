<?php

namespace Tests\Feature\Api;

use App\Models\BrandingSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_branding_endpoint_returns_public_branding_payload(): void
    {
        BrandingSetting::query()->create();

        $this->getJson('/api/v1/branding')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'logo_url',
                    'favicon_url',
                    'favicon_type',
                    'has_custom_logo',
                    'has_custom_favicon',
                    'updated_at',
                ],
            ]);
    }
}

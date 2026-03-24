<?php

namespace Tests\Feature\CommandCenter;

use App\Models\BrandingSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_general_admin_can_view_branding_settings_page(): void
    {
        $user = User::factory()->generalAdmin()->create();

        $this->actingAs($user)
            ->get(route('command-center.settings.appearance.edit'))
            ->assertOk();
    }

    public function test_general_admin_can_update_branding_assets(): void
    {
        Storage::fake('local');

        $user = User::factory()->generalAdmin()->create();
        $logo = UploadedFile::fake()->image('logo.png', 960, 240);
        $favicon = UploadedFile::fake()->image('favicon.png', 512, 512);

        $this->actingAs($user)
            ->post(route('command-center.settings.update'), [
                'logo' => $logo,
                'favicon' => $favicon,
            ])
            ->assertRedirect(route('command-center.settings.appearance.edit'))
            ->assertSessionHas('status', 'Branding actualizado correctamente.');

        $settings = BrandingSetting::query()->first();

        $this->assertNotNull($settings);
        $this->assertSame('branding/app-logo.png', $settings->logo_path);
        $this->assertSame('branding/app-favicon.png', $settings->favicon_path);

        Storage::disk('local')->assertExists('branding/app-logo.png');
        Storage::disk('local')->assertExists('branding/app-favicon.png');
    }
}

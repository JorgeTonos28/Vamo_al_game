<?php

namespace Tests\Feature;

use App\Models\BrandingSetting;
use App\Models\League;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_only_loads_system_defaults_when_starter_data_is_disabled(): void
    {
        config()->set('starter-data.enabled', false);

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('branding_settings', 1);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('leagues', 0);
    }

    public function test_database_seeder_can_load_local_starter_data_when_enabled(): void
    {
        config()->set('starter-data.enabled', true);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, BrandingSetting::query()->count());
        $this->assertSame(4, User::query()->count());
        $this->assertSame(3, League::query()->count());
    }
}

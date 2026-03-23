<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Safe path for upgrades: only structural defaults live here.
        // Demo accounts and sample leagues are opt-in and must not ship by default to production.
        $this->call([
            SystemDefaultsSeeder::class,
        ]);

        if (! config('starter-data.enabled')) {
            $this->command?->info(
                'Starter data omitida. Usa `php artisan db:seed --class=LocalStarterDataSeeder` o APP_ENABLE_STARTER_DATA=true solo en desarrollo local.'
            );

            return;
        }

        $this->call([
            LocalStarterDataSeeder::class,
        ]);

        $this->command?->info(
            'Starter data cargada para desarrollo local.'
        );
    }
}

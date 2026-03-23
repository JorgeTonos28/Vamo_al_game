<?php

namespace Database\Seeders;

use App\Models\BrandingSetting;
use Illuminate\Database\Seeder;

class SystemDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        BrandingSetting::updateOrCreate(
            ['id' => 1],
            [],
        );
    }
}

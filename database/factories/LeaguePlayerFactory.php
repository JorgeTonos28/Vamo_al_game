<?php

namespace Database\Factories;

use App\Models\League;
use App\Models\LeaguePlayer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaguePlayer>
 */
class LeaguePlayerFactory extends Factory
{
    protected $model = LeaguePlayer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'league_id' => League::factory(),
            'display_name' => fake()->unique()->name(),
            'jersey_number' => fake()->optional()->numberBetween(0, 99),
            'status' => 'active',
            'joined_at' => now(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
            'removed_at' => now(),
        ]);
    }
}

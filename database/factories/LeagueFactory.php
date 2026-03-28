<?php

namespace Database\Factories;

use App\Models\League;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<League>
 */
class LeagueFactory extends Factory
{
    protected $model = League::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company().' League';

        return [
            'name' => $name,
            'emoji' => fake()->randomElement(['⚽', '🏀', '🏐', '🥎']),
            'incoming_team_guest_limit' => 2,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
            'is_active' => true,
            'created_by_user_id' => User::factory(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}

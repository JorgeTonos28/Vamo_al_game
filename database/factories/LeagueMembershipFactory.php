<?php

namespace Database\Factories;

use App\Enums\LeagueMembershipRole;
use App\Models\League;
use App\Models\LeagueMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeagueMembership>
 */
class LeagueMembershipFactory extends Factory
{
    protected $model = LeagueMembership::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'league_id' => League::factory(),
            'user_id' => User::factory(),
            'role' => fake()->randomElement([
                LeagueMembershipRole::Admin,
                LeagueMembershipRole::Member,
                LeagueMembershipRole::Guest,
            ]),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => LeagueMembershipRole::Admin,
        ]);
    }

    public function member(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => LeagueMembershipRole::Member,
        ]);
    }

    public function guest(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => LeagueMembershipRole::Guest,
        ]);
    }
}

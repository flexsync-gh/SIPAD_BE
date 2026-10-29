<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_type' => fake()->randomElement(['internal', 'external']),
            'is_locked' => false,
            'supervisor' => User::factory()->assistant(),
        ];
    }
}

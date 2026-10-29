<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Skill>
 */
class SkillFactory extends Factory
{
    public function definition(): array
    {
        return [
            'role_id' => Role::factory(),
            'name' => fake()->unique()->word(),
        ];
    }
}

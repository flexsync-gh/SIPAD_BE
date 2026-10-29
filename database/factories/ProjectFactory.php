<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_name' => fake()->unique()->sentence(3),
            'project_owner' => User::factory()->lecturer(),
            'project_associate' => fake()->company(),
            'project_description' => fake()->paragraph(),
            'project_team' => Team::factory(),
        ];
    }
}

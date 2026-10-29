<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentProfile>
 */
class StudentProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'nim' => fake()->unique()->numerify('##/######/SV/#####'),
            'is_bypass' => false,
            'role_1_id' => Role::factory(),
            'role_2_id' => Role::factory(),
            'team_id' => null,
            'portfolio_link' => fake()->url(),
        ];
    }

    public function bypass(): static
    {
        return $this->state(fn () => [
            'is_bypass' => true,
        ]);
    }
}

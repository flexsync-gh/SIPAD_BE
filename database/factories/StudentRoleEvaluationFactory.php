<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\StudentRoleEvaluation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentRoleEvaluation>
 */
class StudentRoleEvaluationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'recommended_role_id' => Role::factory(),
            'recommended_score' => fake()->randomFloat(2, 50, 100),
            'score_role_1' => fake()->randomFloat(2, 50, 100),
            'score_role_2' => fake()->randomFloat(2, 50, 100),
            'final_role_id' => Role::factory(),
            'is_overridden' => false,
            'is_locked' => false,
            'evaluation_note' => fake()->sentence(),
            'updated_by' => User::factory()->lecturer(),
        ];
    }
}

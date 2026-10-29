<?php

namespace Database\Factories;

use App\Models\Complaint;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Complaint>
 */
class ComplaintFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reporter_id' => User::factory()->student(),
            'reported_id' => User::factory()->student(),
            'team_id' => Team::factory(),
            'description' => fake()->paragraph(),
            'reviewed_by' => null,
            'evidence_file_path' => 'complaints/evidence_'.fake()->uuid().'.pdf',
            'created_at' => now(),
            'reviewed_at' => null,
        ];
    }
}

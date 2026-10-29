<?php

namespace Database\Factories;

use App\Models\Skill;
use App\Models\StudentProfile;
use App\Models\StudentSkill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentSkill>
 */
class StudentSkillFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_profile_id' => StudentProfile::factory(),
            'skill_id' => Skill::factory(),
        ];
    }
}

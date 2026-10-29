<?php

namespace App\Services;

use App\Models\Skill;
use App\Models\StudentRoleEvaluation;
use App\Models\User;

class ScoringEngineService
{
    /**
     * Calculate role scores and recommendation for a student based on profiling input.
     */
    public function calculateForStudent(User $user): ?StudentRoleEvaluation
    {
        $profile = $user->studentProfile;

        if (! $profile || ! $profile->role_1_id) {
            return null;
        }

        // Get student's checked skill IDs
        $checkedSkillIds = $profile->skills()->pluck('skills.id')->toArray();

        // 1. Calculate Score for Role 1
        $scoreRole1 = $this->calculateScoreForRole((int) $profile->role_1_id, $checkedSkillIds, priority: 1);

        // 2. Calculate Score for Role 2 (if specified and not bypass)
        $scoreRole2 = null;
        if (! $profile->is_bypass && $profile->role_2_id) {
            $scoreRole2 = $this->calculateScoreForRole((int) $profile->role_2_id, $checkedSkillIds, priority: 2);
        }

        // 3. Determine Recommended Role & Score
        if ($scoreRole2 !== null && $scoreRole2 > $scoreRole1) {
            $recommendedRoleId = $profile->role_2_id;
            $recommendedScore = $scoreRole2;
        } else {
            $recommendedRoleId = $profile->role_1_id;
            $recommendedScore = $scoreRole1;
        }

        // 4. Update or Create StudentRoleEvaluation
        $evaluation = StudentRoleEvaluation::firstOrNew(['user_id' => $user->id]);

        $evaluation->score_role_1 = $scoreRole1;
        $evaluation->score_role_2 = $scoreRole2;
        $evaluation->recommended_role_id = $recommendedRoleId;
        $evaluation->recommended_score = $recommendedScore;

        // If not manually overridden by lecturer yet, sync final_role_id with recommended_role_id
        if (! $evaluation->is_overridden) {
            $evaluation->final_role_id = $recommendedRoleId;
        }

        $evaluation->save();

        return $evaluation;
    }

    /**
     * Calculate score for a specific role and priority.
     * Formula: Skor peran = (Nilai Keahlian x 70%) + (Nilai Prioritas x 30%)
     * Pilihan 1: Prioritas 100% -> Bobot prioritas = 100% * 30% = 30%
     * Pilihan 2: Prioritas 50%  -> Bobot prioritas = 50%  * 30% = 15%
     *
     * @param  list<int>  $checkedSkillIds
     * @param  int  $priority  1 or 2
     */
    public function calculateScoreForRole(int $roleId, array $checkedSkillIds, int $priority = 1): float
    {
        $totalRoleSkills = Skill::where('role_id', $roleId)->count();

        $matchingSkillsCount = Skill::where('role_id', $roleId)
            ->whereIn('id', $checkedSkillIds)
            ->count();

        // Nilai Keahlian (0 - 100)
        $skillPercentage = $totalRoleSkills > 0
            ? ($matchingSkillsCount / $totalRoleSkills) * 100.0
            : 0.0;

        $skillPoints = $skillPercentage * 0.70;

        // Nilai Prioritas (Pilihan 1: 30 poin, Pilihan 2: 15 poin)
        $priorityPoints = ($priority === 1) ? 30.0 : 15.0;

        $totalScore = $skillPoints + $priorityPoints;

        return round($totalScore, 2);
    }
}

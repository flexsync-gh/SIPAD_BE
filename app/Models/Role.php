<?php

namespace App\Models;

use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
    ];

    /**
     * Get the skills associated with this role.
     *
     * @return HasMany<Skill, $this>
     */
    public function skills(): HasMany
    {
        return $this->hasMany(Skill::class, 'role_id');
    }

    /**
     * Get student profiles having this role as primary preference.
     *
     * @return HasMany<StudentProfile, $this>
     */
    public function primaryProfiles(): HasMany
    {
        return $this->hasMany(StudentProfile::class, 'role_1_id');
    }

    /**
     * Get student profiles having this role as secondary preference.
     *
     * @return HasMany<StudentProfile, $this>
     */
    public function secondaryProfiles(): HasMany
    {
        return $this->hasMany(StudentProfile::class, 'role_2_id');
    }

    /**
     * Get evaluations where this role was recommended by the system.
     *
     * @return HasMany<StudentRoleEvaluation, $this>
     */
    public function recommendedEvaluations(): HasMany
    {
        return $this->hasMany(StudentRoleEvaluation::class, 'recommended_role_id');
    }

    /**
     * Get evaluations where this role was set as the final assigned role.
     *
     * @return HasMany<StudentRoleEvaluation, $this>
     */
    public function finalEvaluations(): HasMany
    {
        return $this->hasMany(StudentRoleEvaluation::class, 'final_role_id');
    }
}

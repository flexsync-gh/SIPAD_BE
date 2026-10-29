<?php

namespace App\Models;

use Database\Factories\SkillFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Skill extends Model
{
    /** @use HasFactory<SkillFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'role_id',
        'name',
    ];

    /**
     * Get the role associated with the skill.
     *
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * The student profiles associated with this skill.
     *
     * @return BelongsToMany<StudentProfile, $this>
     */
    public function studentProfiles(): BelongsToMany
    {
        return $this->belongsToMany(StudentProfile::class, 'student_skills', 'skill_id', 'student_profile_id')
            ->withTimestamps();
    }
}

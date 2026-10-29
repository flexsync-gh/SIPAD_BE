<?php

namespace App\Models;

use Database\Factories\StudentProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class StudentProfile extends Model
{
    /** @use HasFactory<StudentProfileFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'nim',
        'is_bypass',
        'role_1_id',
        'role_2_id',
        'team_id',
        'portfolio_link',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_bypass' => 'boolean',
        ];
    }

    /**
     * Get the user account for this profile.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the first preferred role.
     *
     * @return BelongsTo<Role, $this>
     */
    public function role1(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_1_id');
    }

    /**
     * Get the second preferred role.
     *
     * @return BelongsTo<Role, $this>
     */
    public function role2(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_2_id');
    }

    /**
     * Get the assigned team.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    /**
     * The skills associated with this student profile.
     *
     * @return BelongsToMany<Skill, $this>
     */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'student_skills', 'student_profile_id', 'skill_id')
            ->withTimestamps();
    }
}

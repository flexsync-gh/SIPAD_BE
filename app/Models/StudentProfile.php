<?php

namespace App\Models;

use App\Rules\NimFormat;
use Database\Factories\StudentProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

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
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::saving(function (StudentProfile $profile): void {
            if ($profile->nim !== null && $profile->nim !== '') {
                if (! preg_match(NimFormat::PATTERN, $profile->nim)) {
                    throw ValidationException::withMessages([
                        'nim' => ['Format NIM tidak valid. Format yang benar: 12/123456/SV/12345 (2 digit angka/6 digit angka/2 huruf/5 digit angka).'],
                    ]);
                }
            }
        });
    }

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

    /**
     * Get the role evaluation for this profile's user.
     *
     * @return HasOne<StudentRoleEvaluation, $this>
     */
    public function evaluation(): HasOne
    {
        return $this->hasOne(StudentRoleEvaluation::class, 'user_id', 'user_id');
    }
}

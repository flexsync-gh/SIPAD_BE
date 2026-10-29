<?php

namespace App\Models;

use Database\Factories\StudentRoleEvaluationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentRoleEvaluation extends Model
{
    /** @use HasFactory<StudentRoleEvaluationFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'recommended_role_id',
        'recommended_score',
        'score_role_1',
        'score_role_2',
        'final_role_id',
        'is_overridden',
        'is_locked',
        'evaluation_note',
        'updated_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_overridden' => 'boolean',
            'is_locked' => 'boolean',
            'recommended_score' => 'float',
            'score_role_1' => 'float',
            'score_role_2' => 'float',
        ];
    }

    /**
     * Get the student user.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the system recommended role.
     *
     * @return BelongsTo<Role, $this>
     */
    public function recommendedRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'recommended_role_id');
    }

    /**
     * Get the final assigned role.
     *
     * @return BelongsTo<Role, $this>
     */
    public function finalRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'final_role_id');
    }

    /**
     * Get the user (lecturer) who last modified this evaluation.
     *
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

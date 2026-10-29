<?php

namespace App\Models;

use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'team_type',
        'is_locked',
        'supervisor',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_locked' => 'boolean',
        ];
    }

    /**
     * Get the supervisor (assistant/lecturer) assigned to this team.
     *
     * @return BelongsTo<User, $this>
     */
    public function supervisorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor');
    }

    /**
     * Get member student profiles of this team.
     *
     * @return HasMany<StudentProfile, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(StudentProfile::class, 'team_id');
    }

    /**
     * Get project assigned to this team.
     *
     * @return HasOne<Project, $this>
     */
    public function project(): HasOne
    {
        return $this->hasOne(Project::class, 'project_team');
    }

    /**
     * Get complaints related to this team.
     *
     * @return HasMany<Complaint, $this>
     */
    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'team_id');
    }
}

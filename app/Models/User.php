<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id_sub',
        'user_role',
        'avatar_link',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'user_role' => UserRole::class,
        ];
    }

    /**
     * Get the student profile for this user.
     *
     * @return HasOne<StudentProfile, $this>
     */
    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class, 'user_id');
    }

    /**
     * Get the role evaluation for this user.
     *
     * @return HasOne<StudentRoleEvaluation, $this>
     */
    public function evaluation(): HasOne
    {
        return $this->hasOne(StudentRoleEvaluation::class, 'user_id');
    }

    /**
     * Get teams supervised by this user.
     *
     * @return HasMany<Team, $this>
     */
    public function supervisedTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'supervisor');
    }

    /**
     * Get projects owned by this user.
     *
     * @return HasMany<Project, $this>
     */
    public function ownedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'project_owner');
    }

    /**
     * Get complaints filed by this user.
     *
     * @return HasMany<Complaint, $this>
     */
    public function filedComplaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'reporter_id');
    }

    /**
     * Get complaints reported against this user.
     *
     * @return HasMany<Complaint, $this>
     */
    public function complaintsAgainst(): HasMany
    {
        return $this->hasMany(Complaint::class, 'reported_id');
    }

    /**
     * Get complaints reviewed by this user.
     *
     * @return HasMany<Complaint, $this>
     */
    public function reviewedComplaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'reviewed_by');
    }

    /**
     * Check if user is full admin.
     */
    public function isAdmin(): bool
    {
        return $this->user_role === UserRole::Admin;
    }

    /**
     * Check if user is lecturer.
     */
    public function isLecturer(): bool
    {
        return $this->user_role === UserRole::Lecturer;
    }

    /**
     * Check if user is student.
     */
    public function isStudent(): bool
    {
        return $this->user_role === UserRole::Student;
    }

    /**
     * Check if user is assistant.
     */
    public function isAssistant(): bool
    {
        return $this->user_role === UserRole::Assistant;
    }

    /**
     * Check if user is practitioner.
     */
    public function isPractitioner(): bool
    {
        return $this->user_role === UserRole::Practitioner;
    }
}

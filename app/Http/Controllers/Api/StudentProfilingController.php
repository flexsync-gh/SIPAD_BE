<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Models\AppSetting;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\StudentRoleEvaluation;
use App\Models\Team;
use App\Services\ScoringEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentProfilingController extends Controller
{
    /**
     * Get profiling form configuration and master data.
     * Checks if the student is bypass and if role 2 is required by system settings.
     */
    public function profilingConfig(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->studentProfile;
        $isBypass = (bool) ($profile?->is_bypass ?? false);

        $allSettings = AppSetting::all()->pluck('value', 'key')->toArray();
        $rawRole2 = $allSettings['is_role_2_required'] ?? $allSettings['is_role_2_mandatory'] ?? 'false';
        $isSettingRequired = in_array(strtolower((string) $rawRole2), ['true', '1', 'wajib', 'yes'], true);

        $isRole2Required = ! $isBypass && $isSettingRequired;
        $allowRole2 = ! $isBypass;

        $isLocked = (bool) ($user->evaluation?->is_locked ?? false);

        $selectedSkillIds = $profile ? $profile->skills()->pluck('skills.id')->toArray() : [];

        $currentProfile = $profile ? [
            'nim' => $profile->nim,
            'role_1_id' => $profile->role_1_id,
            'role_2_id' => $profile->role_2_id,
            'portfolio_link' => $profile->portfolio_link,
            'is_bypass' => $isBypass,
            'selected_skill_ids' => $selectedSkillIds,
        ] : null;

        $roles = Role::with('skills')->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Konfigurasi form profiling berhasil diambil.',
            'data' => [
                'config' => [
                    'is_bypass' => $isBypass,
                    'is_role_2_required' => $isRole2Required,
                    'allow_role_2' => $allowRole2,
                    'role_2_status' => $isBypass ? 'disabled' : ($isRole2Required ? 'required' : 'optional'),
                    'is_locked' => $isLocked,
                ],
                'current_profile' => $currentProfile,
                'roles' => RoleResource::collection($roles),
            ],
        ]);
    }

    /**
     * Store student profiling submission and trigger scoring calculation.
     */
    public function storeProfile(Request $request, ScoringEngineService $scoringService): JsonResponse
    {
        $user = $request->user();

        // 1. Validation: Reject if already locked
        $isLocked = StudentRoleEvaluation::where('user_id', $user->id)
            ->where('is_locked', true)
            ->exists();

        if ($isLocked) {
            return response()->json([
                'status' => 'error',
                'message' => 'Form profiling telah dikunci oleh dosen pengampu dan tidak dapat diubah.',
            ], 422);
        }

        // 2. Validate input fields
        $validated = $request->validate([
            'nim' => ['required', 'string', 'max:50'],
            'role_1_id' => ['required', 'integer', 'exists:roles,id'],
            'role_2_id' => ['nullable', 'integer', 'exists:roles,id'],
            'skill_ids' => ['nullable', 'array'],
            'skill_ids.*' => ['integer', 'exists:skills,id'],
            'portfolio_link' => ['nullable', 'string', 'max:500'],
        ], [
            'nim.required' => 'NIM mahasiswa wajib diisi.',
            'role_1_id.required' => 'Pilihan role ke-1 wajib dipilih.',
            'role_1_id.exists' => 'Pilihan role ke-1 tidak valid.',
            'role_2_id.exists' => 'Pilihan role ke-2 tidak valid.',
            'skill_ids.*.exists' => 'Skill yang dipilih tidak valid.',
        ]);

        // 3. Anti-duplicate validation: role_1_id != role_2_id
        if ($request->filled('role_2_id') && (int) $request->input('role_1_id') === (int) $request->input('role_2_id')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pilihan role 1 dan role 2 tidak boleh sama.',
                'errors' => [
                    'role_2_id' => ['Pilihan role 1 dan role 2 tidak boleh sama.'],
                ],
            ], 422);
        }

        // 4. Bypass & Setting Check
        $existingProfile = $user->studentProfile ?? StudentProfile::where('user_id', $user->id)->first();
        $isBypass = (bool) ($existingProfile?->is_bypass ?? false);

        if ($isBypass) {
            // Force role_2_id = null for bypass students
            $role2Id = null;
        } else {
            $allSettings = AppSetting::all()->pluck('value', 'key')->toArray();
            $rawRole2 = $allSettings['is_role_2_required'] ?? $allSettings['is_role_2_mandatory'] ?? 'false';
            $isRole2Required = in_array(strtolower((string) $rawRole2), ['true', '1', 'wajib', 'yes'], true);

            if ($isRole2Required && ! $request->filled('role_2_id')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Pilihan role ke-2 wajib diisi sesuai pengaturan sistem.',
                    'errors' => [
                        'role_2_id' => ['Pilihan role ke-2 wajib diisi sesuai pengaturan sistem.'],
                    ],
                ], 422);
            }

            $role2Id = $request->input('role_2_id');
        }

        // 5. Save profiling in transaction
        /** @var StudentProfile $profile */
        $profile = DB::transaction(function () use ($user, $validated, $role2Id) {
            $profile = StudentProfile::firstOrNew(['user_id' => $user->id]);
            $profile->nim = $validated['nim'];
            $profile->role_1_id = $validated['role_1_id'];
            $profile->role_2_id = $role2Id;
            $profile->portfolio_link = $validated['portfolio_link'] ?? null;
            $profile->save();

            if (isset($validated['skill_ids'])) {
                $profile->skills()->sync($validated['skill_ids']);
            }

            return $profile;
        });

        // 6. Trigger Scoring Calculation
        $user->refresh();
        $evaluation = $scoringService->calculateForStudent($user);
        $evaluation?->load(['recommendedRole', 'finalRole']);

        return response()->json([
            'status' => 'success',
            'message' => 'Profil dan keahlian mahasiswa berhasil disimpan dan dievaluasi.',
            'data' => [
                'profile' => [
                    'id' => $profile->id,
                    'user_id' => $profile->user_id,
                    'nim' => $profile->nim,
                    'is_bypass' => (bool) $profile->is_bypass,
                    'role_1_id' => $profile->role_1_id,
                    'role_2_id' => $profile->role_2_id,
                    'portfolio_link' => $profile->portfolio_link,
                    'selected_skill_ids' => $profile->skills()->pluck('skills.id')->toArray(),
                ],
                'evaluation' => $evaluation ? [
                    'id' => $evaluation->id,
                    'user_id' => $evaluation->user_id,
                    'score_role_1' => $evaluation->score_role_1,
                    'score_role_2' => $evaluation->score_role_2,
                    'recommended_score' => $evaluation->recommended_score,
                    'recommended_role_id' => $evaluation->recommended_role_id,
                    'recommended_role' => $evaluation->recommendedRole ? [
                        'id' => $evaluation->recommendedRole->id,
                        'code' => $evaluation->recommendedRole->code,
                        'name' => $evaluation->recommendedRole->name,
                    ] : null,
                    'final_role_id' => $evaluation->final_role_id,
                    'final_role' => $evaluation->finalRole ? [
                        'id' => $evaluation->finalRole->id,
                        'code' => $evaluation->finalRole->code,
                        'name' => $evaluation->finalRole->name,
                    ] : null,
                    'is_overridden' => (bool) $evaluation->is_overridden,
                    'is_locked' => (bool) $evaluation->is_locked,
                ] : null,
            ],
        ]);
    }

    /**
     * Get student's final team announcement, supervisor, project details, and fellow members.
     */
    public function myTeam(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->studentProfile;
        $evaluation = $user->evaluation;

        $finalRole = $evaluation?->finalRole ?? $evaluation?->recommendedRole;

        // If student does not have a team assigned yet
        if (! $profile || ! $profile->team_id) {
            return response()->json([
                'status' => 'success',
                'message' => 'Anda belum terdaftar dalam kelompok atau pengumuman kelompok belum dirilis.',
                'data' => [
                    'has_team' => false,
                    'final_role' => $finalRole ? [
                        'id' => $finalRole->id,
                        'code' => $finalRole->code,
                        'name' => $finalRole->name,
                    ] : null,
                    'team' => null,
                ],
            ]);
        }

        $team = Team::with([
            'supervisorUser',
            'project.owner',
            'members.user',
            'members.evaluation.finalRole',
            'members.role1',
        ])->find($profile->team_id);

        if (! $team) {
            return response()->json([
                'status' => 'success',
                'message' => 'Data kelompok tidak ditemukan.',
                'data' => [
                    'has_team' => false,
                    'final_role' => $finalRole ? [
                        'id' => $finalRole->id,
                        'code' => $finalRole->code,
                        'name' => $finalRole->name,
                    ] : null,
                    'team' => null,
                ],
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Informasi tim dan pengumuman peran berhasil dimuat.',
            'data' => [
                'has_team' => true,
                'final_role' => $finalRole ? [
                    'id' => $finalRole->id,
                    'code' => $finalRole->code,
                    'name' => $finalRole->name,
                ] : null,
                'team' => [
                    'id' => $team->id,
                    'team_type' => $team->team_type,
                    'is_locked' => (bool) $team->is_locked,
                    'supervisor' => $team->supervisorUser ? [
                        'id' => $team->supervisorUser->id,
                        'name' => $team->supervisorUser->name,
                        'email' => $team->supervisorUser->email,
                        'role' => $team->supervisorUser->user_role instanceof UserRole ? $team->supervisorUser->user_role->value : (string) $team->supervisorUser->user_role,
                        'role_label' => $team->supervisorUser->user_role instanceof UserRole ? $team->supervisorUser->user_role->label() : null,
                    ] : null,
                    'project' => $team->project ? [
                        'id' => $team->project->id,
                        'name' => $team->project->project_name,
                        'description' => $team->project->project_description,
                        'associate' => $team->project->project_associate,
                        'owner' => $team->project->owner ? [
                            'id' => $team->project->owner->id,
                            'name' => $team->project->owner->name,
                            'email' => $team->project->owner->email,
                        ] : null,
                    ] : null,
                    'members' => $team->members->map(function ($member) {
                        $memberFinalRole = $member->evaluation?->finalRole
                            ?? $member->evaluation?->recommendedRole
                            ?? $member->role1;

                        return [
                            'id' => $member->id,
                            'user_id' => $member->user_id,
                            'name' => $member->user?->name,
                            'email' => $member->user?->email,
                            'nim' => $member->nim,
                            'portfolio_link' => $member->portfolio_link,
                            'final_role' => $memberFinalRole ? [
                                'id' => $memberFinalRole->id,
                                'code' => $memberFinalRole->code,
                                'name' => $memberFinalRole->name,
                            ] : null,
                        ];
                    }),
                ],
            ],
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Role;
use App\Models\Skill;
use App\Models\StudentRoleEvaluation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvaluationController extends Controller
{
    /**
     * Get recap table of all students for evaluation (Dosen, Praktisi).
     * Supports search and filter.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::where('user_role', UserRole::Student->value)
            ->with([
                'studentProfile.role1',
                'studentProfile.role2',
                'evaluation.recommendedRole',
                'evaluation.finalRole',
                'evaluation.updater',
            ])
            ->withExists('complaintsAgainst as has_complaints');

        // Search by name, email, or NIM
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('studentProfile', function ($sp) use ($search) {
                        $sp->where('nim', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by role (final_role_id or role code e.g. 'PM', 'FE')
        if ($request->filled('role')) {
            $roleParam = $request->input('role');
            $query->whereHas('evaluation', function ($eq) use ($roleParam) {
                if (is_numeric($roleParam)) {
                    $eq->where('final_role_id', (int) $roleParam);
                } else {
                    $eq->whereHas('finalRole', function ($rq) use ($roleParam) {
                        $rq->where('code', strtoupper($roleParam));
                    });
                }
            });
        }

        // Filter by is_overridden
        if ($request->has('is_overridden') && $request->input('is_overridden') !== null && $request->input('is_overridden') !== '') {
            $isOverridden = filter_var($request->input('is_overridden'), FILTER_VALIDATE_BOOLEAN);
            $query->whereHas('evaluation', function ($eq) use ($isOverridden) {
                $eq->where('is_overridden', $isOverridden);
            });
        }

        // Filter by is_locked
        if ($request->has('is_locked') && $request->input('is_locked') !== null && $request->input('is_locked') !== '') {
            $isLocked = filter_var($request->input('is_locked'), FILTER_VALIDATE_BOOLEAN);
            $query->whereHas('evaluation', function ($eq) use ($isLocked) {
                $eq->where('is_locked', $isLocked);
            });
        }

        // Filter by has_complaints
        if ($request->has('has_complaints') && $request->input('has_complaints') !== null && $request->input('has_complaints') !== '') {
            $hasComplaints = filter_var($request->input('has_complaints'), FILTER_VALIDATE_BOOLEAN);
            if ($hasComplaints) {
                $query->has('complaintsAgainst');
            } else {
                $query->doesntHave('complaintsAgainst');
            }
        }

        // Filter by is_bypass
        if ($request->has('is_bypass') && $request->input('is_bypass') !== null && $request->input('is_bypass') !== '') {
            $isBypass = filter_var($request->input('is_bypass'), FILTER_VALIDATE_BOOLEAN);
            $query->whereHas('studentProfile', function ($sp) use ($isBypass) {
                $sp->where('is_bypass', $isBypass);
            });
        }

        $query->orderBy('name', 'asc');

        $perPage = $request->input('per_page', 15);
        if ($perPage === 'all' || (int) $perPage === -1) {
            $students = $query->get();
            $data = $students->map(fn (User $student) => $this->formatEvaluationRow($student));

            return response()->json([
                'status' => 'success',
                'message' => 'Data rekapitulasi evaluasi mahasiswa berhasil diambil.',
                'data' => $data,
                'meta' => [
                    'total' => $students->count(),
                ],
            ]);
        }

        $paginated = $query->paginate((int) $perPage);
        $data = $paginated->getCollection()->map(fn (User $student) => $this->formatEvaluationRow($student));

        return response()->json([
            'status' => 'success',
            'message' => 'Data rekapitulasi evaluasi mahasiswa berhasil diambil.',
            'data' => $data,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Get student profiling and evaluation detail for pop-up modal (Dosen, Praktisi).
     */
    public function show(int $userId): JsonResponse
    {
        $student = User::with([
            'studentProfile.role1',
            'studentProfile.role2',
            'studentProfile.skills',
            'evaluation.recommendedRole',
            'evaluation.finalRole',
            'evaluation.updater',
            'complaintsAgainst.reporter',
        ])
            ->withExists('complaintsAgainst as has_complaints')
            ->find($userId);

        if (! $student) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data mahasiswa tidak ditemukan.',
            ], 404);
        }

        $checkedSkillIds = $student->studentProfile
            ? $student->studentProfile->skills->pluck('id')->toArray()
            : [];

        $roles = Role::with('skills')->get();
        $skillsByRole = $roles->map(function (Role $role) use ($checkedSkillIds) {
            $skills = $role->skills->map(function (Skill $skill) use ($checkedSkillIds) {
                return [
                    'id' => $skill->id,
                    'name' => $skill->name,
                    'is_checked' => in_array($skill->id, $checkedSkillIds, true),
                ];
            });

            return [
                'role_id' => $role->id,
                'role_code' => $role->code,
                'role_name' => $role->name,
                'total_skills' => $role->skills->count(),
                'checked_count' => $skills->where('is_checked', true)->count(),
                'skills' => $skills->values(),
            ];
        });

        $complaints = $student->complaintsAgainst->map(function (Complaint $complaint) {
            return [
                'id' => $complaint->id,
                'description' => $complaint->description,
                'evidence_file_path' => $complaint->evidence_file_path,
                'reporter' => $complaint->reporter ? [
                    'id' => $complaint->reporter->id,
                    'name' => $complaint->reporter->name,
                    'email' => $complaint->reporter->email,
                ] : null,
                'created_at' => $complaint->created_at?->toISOString(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Detail evaluasi dan profil mahasiswa berhasil diambil.',
            'data' => [
                'student' => [
                    'user_id' => $student->id,
                    'name' => $student->name,
                    'email' => $student->email,
                    'nim' => $student->studentProfile?->nim,
                    'portfolio_link' => $student->studentProfile?->portfolio_link,
                    'is_bypass' => (bool) ($student->studentProfile?->is_bypass ?? false),
                    'role_1' => $student->studentProfile?->role1 ? [
                        'id' => $student->studentProfile->role1->id,
                        'code' => $student->studentProfile->role1->code,
                        'name' => $student->studentProfile->role1->name,
                    ] : null,
                    'role_2' => $student->studentProfile?->role2 ? [
                        'id' => $student->studentProfile->role2->id,
                        'code' => $student->studentProfile->role2->code,
                        'name' => $student->studentProfile->role2->name,
                    ] : null,
                ],
                'evaluation' => [
                    'id' => $student->evaluation?->id,
                    'score_role_1' => $student->evaluation?->score_role_1 !== null ? (float) $student->evaluation->score_role_1 : null,
                    'score_role_2' => $student->evaluation?->score_role_2 !== null ? (float) $student->evaluation->score_role_2 : null,
                    'recommended_score' => $student->evaluation?->recommended_score !== null ? (float) $student->evaluation->recommended_score : null,
                    'recommended_role_id' => $student->evaluation?->recommended_role_id,
                    'recommended_role' => $student->evaluation?->recommendedRole ? [
                        'id' => $student->evaluation->recommendedRole->id,
                        'code' => $student->evaluation->recommendedRole->code,
                        'name' => $student->evaluation->recommendedRole->name,
                    ] : null,
                    'final_role_id' => $student->evaluation?->final_role_id,
                    'final_role' => $student->evaluation?->finalRole ? [
                        'id' => $student->evaluation->finalRole->id,
                        'code' => $student->evaluation->finalRole->code,
                        'name' => $student->evaluation->finalRole->name,
                    ] : null,
                    'is_overridden' => (bool) ($student->evaluation?->is_overridden ?? false),
                    'is_locked' => (bool) ($student->evaluation?->is_locked ?? false),
                    'evaluation_note' => $student->evaluation?->evaluation_note,
                    'updated_by' => $student->evaluation?->updater ? [
                        'id' => $student->evaluation->updater->id,
                        'name' => $student->evaluation->updater->name,
                        'email' => $student->evaluation->updater->email,
                    ] : null,
                ],
                'skills_by_role' => $skillsByRole,
                'checked_skill_ids' => $checkedSkillIds,
                'has_complaints' => (bool) $student->has_complaints,
                'complaints' => $complaints,
            ],
        ]);
    }

    /**
     * Save or update evaluation note (Dosen, Praktisi).
     */
    public function updateNote(Request $request, int $userId): JsonResponse
    {
        $student = User::find($userId);
        if (! $student) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data mahasiswa tidak ditemukan.',
            ], 404);
        }

        $request->validate([
            'note' => ['nullable', 'string', 'max:255'],
            'evaluation_note' => ['nullable', 'string', 'max:255'],
        ]);

        $note = $request->input('note') ?? $request->input('evaluation_note');

        $evaluation = StudentRoleEvaluation::firstOrNew(['user_id' => $student->id]);
        $evaluation->evaluation_note = $note;
        $evaluation->updated_by = $request->user()->id;
        $evaluation->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Catatan evaluasi portofolio berhasil disimpan.',
            'data' => [
                'user_id' => $student->id,
                'evaluation_note' => $evaluation->evaluation_note,
                'updated_by' => [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                ],
                'updated_at' => $evaluation->updated_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Manually override student final role (Dosen).
     * Rejected if is_locked = true.
     */
    public function override(Request $request, int $userId): JsonResponse
    {
        $student = User::find($userId);
        if (! $student) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data mahasiswa tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'final_role_id' => ['required', 'integer', 'exists:roles,id'],
        ], [
            'final_role_id.required' => 'Role final wajib dipilih untuk melakukan override.',
            'final_role_id.exists' => 'Role yang dipilih tidak valid.',
        ]);

        $evaluation = StudentRoleEvaluation::firstOrNew(['user_id' => $student->id]);

        if ($evaluation->is_locked) {
            return response()->json([
                'status' => 'error',
                'message' => 'Evaluasi peran mahasiswa telah dikunci dan tidak dapat di-override.',
            ], 422);
        }

        $evaluation->final_role_id = $validated['final_role_id'];
        $evaluation->is_overridden = true;
        $evaluation->updated_by = $request->user()->id;
        $evaluation->save();

        $evaluation->load(['recommendedRole', 'finalRole', 'updater']);

        return response()->json([
            'status' => 'success',
            'message' => 'Posisi final mahasiswa berhasil diubah (override) oleh dosen.',
            'data' => [
                'user_id' => $student->id,
                'final_role_id' => $evaluation->final_role_id,
                'final_role' => [
                    'id' => $evaluation->finalRole->id,
                    'code' => $evaluation->finalRole->code,
                    'name' => $evaluation->finalRole->name,
                ],
                'is_overridden' => true,
                'is_locked' => (bool) $evaluation->is_locked,
                'updated_by' => [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                ],
            ],
        ]);
    }

    /**
     * Reset student final role back to initial recommended role (Dosen).
     * Sets is_overridden = false and records updated_by.
     */
    public function reset(Request $request, int $userId): JsonResponse
    {
        $student = User::find($userId);
        if (! $student) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data mahasiswa tidak ditemukan.',
            ], 404);
        }

        $evaluation = StudentRoleEvaluation::where('user_id', $student->id)->first();
        if (! $evaluation) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data evaluasi peran untuk mahasiswa ini belum tersedia.',
            ], 422);
        }

        if ($evaluation->is_locked) {
            return response()->json([
                'status' => 'error',
                'message' => 'Evaluasi peran mahasiswa telah dikunci dan tidak dapat direset.',
            ], 422);
        }

        if (! $evaluation->recommended_role_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Mahasiswa belum memiliki rekomendasi peran awal dari sistem.',
            ], 422);
        }

        $evaluation->final_role_id = $evaluation->recommended_role_id;
        $evaluation->is_overridden = false;
        $evaluation->updated_by = $request->user()->id;
        $evaluation->save();

        $evaluation->load(['recommendedRole', 'finalRole', 'updater']);

        return response()->json([
            'status' => 'success',
            'message' => 'Posisi final mahasiswa berhasil dikembalikan ke saran awal sistem.',
            'data' => [
                'user_id' => $student->id,
                'final_role_id' => $evaluation->final_role_id,
                'final_role' => [
                    'id' => $evaluation->finalRole->id,
                    'code' => $evaluation->finalRole->code,
                    'name' => $evaluation->finalRole->name,
                ],
                'is_overridden' => false,
                'is_locked' => (bool) $evaluation->is_locked,
                'updated_by' => [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                ],
            ],
        ]);
    }

    /**
     * Lock all student role evaluations (Dosen).
     * UPDATE student_role_evaluations SET is_locked = true.
     */
    public function lockAll(Request $request): JsonResponse
    {
        $lockStatus = true;
        if ($request->has('is_locked')) {
            $lockStatus = filter_var($request->input('is_locked'), FILTER_VALIDATE_BOOLEAN);
        }

        $affected = StudentRoleEvaluation::query()->update([
            'is_locked' => $lockStatus,
            'updated_by' => $request->user()->id,
        ]);

        $message = $lockStatus
            ? 'Seluruh evaluasi peran mahasiswa berhasil dikunci.'
            : 'Seluruh evaluasi peran mahasiswa berhasil dibuka kuncinya.';

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => [
                'affected_rows' => $affected,
                'is_locked' => $lockStatus,
            ],
        ]);
    }

    /**
     * Format a single student user record into an evaluation row array.
     *
     * @return array<string, mixed>
     */
    protected function formatEvaluationRow(User $student): array
    {
        $profile = $student->studentProfile;
        $evaluation = $student->evaluation;

        return [
            'user_id' => $student->id,
            'name' => $student->name,
            'email' => $student->email,
            'nim' => $profile?->nim,
            'is_bypass' => (bool) ($profile?->is_bypass ?? false),
            'role_1' => $profile?->role1 ? [
                'id' => $profile->role1->id,
                'code' => $profile->role1->code,
                'name' => $profile->role1->name,
            ] : null,
            'role_2' => $profile?->role2 ? [
                'id' => $profile->role2->id,
                'code' => $profile->role2->code,
                'name' => $profile->role2->name,
            ] : null,
            'score_role_1' => $evaluation?->score_role_1 !== null ? (float) $evaluation->score_role_1 : null,
            'score_role_2' => $evaluation?->score_role_2 !== null ? (float) $evaluation->score_role_2 : null,
            'recommended_score' => $evaluation?->recommended_score !== null ? (float) $evaluation->recommended_score : null,
            'recommended_role_id' => $evaluation?->recommended_role_id,
            'recommended_role' => $evaluation?->recommendedRole ? [
                'id' => $evaluation->recommendedRole->id,
                'code' => $evaluation->recommendedRole->code,
                'name' => $evaluation->recommendedRole->name,
            ] : null,
            'final_role_id' => $evaluation?->final_role_id,
            'final_role' => $evaluation?->finalRole ? [
                'id' => $evaluation->finalRole->id,
                'code' => $evaluation->finalRole->code,
                'name' => $evaluation->finalRole->name,
            ] : null,
            'is_overridden' => (bool) ($evaluation?->is_overridden ?? false),
            'is_locked' => (bool) ($evaluation?->is_locked ?? false),
            'has_complaints' => (bool) ($student->has_complaints ?? false),
            'evaluation_note' => $evaluation?->evaluation_note,
            'updated_by' => $evaluation?->updater ? [
                'id' => $evaluation->updater->id,
                'name' => $evaluation->updater->name,
                'email' => $evaluation->updater->email,
            ] : null,
        ];
    }
}

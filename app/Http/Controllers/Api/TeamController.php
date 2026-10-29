<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Project;
use App\Models\StudentProfile;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    /**
     * Get list of all teams, their members, and projects.
     * Filtered by supervisor = auth()->id() if user is an Assistant (Asprak).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $userRole = $user->user_role instanceof UserRole ? $user->user_role->value : (string) $user->user_role;
        $normalized = UserRole::normalize($userRole);

        $query = Team::with([
            'supervisorUser',
            'project.owner',
            'members.user',
            'members.evaluation.finalRole',
            'members.role1',
        ]);

        // Asprak only sees teams they supervise
        if ($normalized === UserRole::Assistant->value) {
            $query->where('supervisor', $user->id);
        } elseif ($request->filled('supervisor_id')) {
            $query->where('supervisor', (int) $request->input('supervisor_id'));
        }

        if ($request->filled('team_type')) {
            $query->where('team_type', $request->input('team_type'));
        }

        if ($request->has('is_locked') && $request->input('is_locked') !== null && $request->input('is_locked') !== '') {
            $query->where('is_locked', filter_var($request->input('is_locked'), FILTER_VALIDATE_BOOLEAN));
        }

        $teams = $query->orderBy('id', 'asc')->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar kelompok berhasil diambil.',
            'data' => $this->formatTeamsCollection($teams),
        ]);
    }

    /**
     * Execute Auto-Grouping Engine (Fisher-Yates Shuffle) for regular students.
     * Forms balanced 4-member teams (PM, UIUX, FE, BE).
     */
    public function autoGroup(Request $request): JsonResponse
    {
        // 1. Check if any regular teams are already locked
        $hasLockedRegularTeams = Team::where('team_type', 'internal')
            ->where('is_locked', true)
            ->exists();

        if ($hasLockedRegularTeams) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pembagian tim reguler telah dikunci dan tidak dapat diacak ulang.',
            ], 422);
        }

        // 2. Retrieve all eligible regular students (is_bypass = false, final_role is set and locked)
        $students = StudentProfile::where('is_bypass', false)
            ->whereHas('evaluation', function ($q) {
                $q->whereNotNull('final_role_id')
                    ->where('is_locked', true);
            })
            ->with(['evaluation.finalRole', 'user'])
            ->get();

        if ($students->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tidak ada mahasiswa reguler dengan posisi yang telah dikunci untuk dikelompokkan.',
            ], 422);
        }

        // 3. Partition into 4 role arrays: PM, UIUX, FE, BE
        $arrayPm = [];
        $arrayUiux = [];
        $arrayFe = [];
        $arrayBe = [];

        foreach ($students as $student) {
            $code = strtoupper((string) ($student->evaluation?->finalRole?->code ?? ''));
            match ($code) {
                'PM' => $arrayPm[] = $student,
                'UIUX' => $arrayUiux[] = $student,
                'FE' => $arrayFe[] = $student,
                'BE' => $arrayBe[] = $student,
                default => null,
            };
        }

        // 4. Determine number of complete teams that can be formed
        $teamCount = min(count($arrayPm), count($arrayUiux), count($arrayFe), count($arrayBe));

        if ($teamCount === 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Jumlah mahasiswa untuk setiap peran tidak mencukupi untuk membentuk tim seimbang (PM: '.count($arrayPm).', UI/UX: '.count($arrayUiux).', FE: '.count($arrayFe).', BE: '.count($arrayBe).'). Minimal dibutuhkan masing-masing 1 mahasiswa.',
            ], 422);
        }

        // 5. Shuffle each array using Fisher-Yates Algorithm
        $this->fisherYatesShuffle($arrayPm);
        $this->fisherYatesShuffle($arrayUiux);
        $this->fisherYatesShuffle($arrayFe);
        $this->fisherYatesShuffle($arrayBe);

        // 6. Persistence in Database
        $createdTeams = DB::transaction(function () use ($teamCount, $arrayPm, $arrayUiux, $arrayFe, $arrayBe) {
            // Reset any previous unlocked regular teams
            $existingUnlockedTeamIds = Team::where('team_type', 'internal')
                ->where('is_locked', false)
                ->pluck('id');

            if ($existingUnlockedTeamIds->isNotEmpty()) {
                StudentProfile::whereIn('team_id', $existingUnlockedTeamIds)->update(['team_id' => null]);
                Project::whereIn('project_team', $existingUnlockedTeamIds)->delete();
                Team::whereIn('id', $existingUnlockedTeamIds)->delete();
            }

            $teams = [];
            for ($i = 0; $i < $teamCount; $i++) {
                $team = Team::create([
                    'team_type' => 'internal',
                    'is_locked' => false,
                    'supervisor' => null,
                ]);

                StudentProfile::whereIn('id', [
                    $arrayPm[$i]->id,
                    $arrayUiux[$i]->id,
                    $arrayFe[$i]->id,
                    $arrayBe[$i]->id,
                ])->update(['team_id' => $team->id]);

                $teams[] = $team;
            }

            return $teams;
        });

        $remainingCount = $students->count() - ($teamCount * 4);

        $teamIds = collect($createdTeams)->pluck('id');
        $teamsWithRelations = Team::whereIn('id', $teamIds)
            ->with([
                'supervisorUser',
                'project.owner',
                'members.user',
                'members.evaluation.finalRole',
            ])
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => "Auto-Grouping Engine berhasil membentuk {$teamCount} tim reguler seimbang.",
            'data' => [
                'teams_created_count' => $teamCount,
                'students_grouped_count' => $teamCount * 4,
                'remaining_students_count' => $remainingCount,
                'teams' => $this->formatTeamsCollection($teamsWithRelations),
            ],
        ]);
    }

    /**
     * Reset grouping results for regular teams (Ulangi / Reset Pembagian Tim).
     */
    public function resetGrouping(Request $request): JsonResponse
    {
        $hasLockedRegularTeams = Team::where('team_type', 'internal')
            ->where('is_locked', true)
            ->exists();

        if ($hasLockedRegularTeams) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pembagian tim reguler telah dikunci dan tidak dapat direset.',
            ], 422);
        }

        $regularTeamIds = Team::where('team_type', 'internal')
            ->where('is_locked', false)
            ->pluck('id');

        if ($regularTeamIds->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Tidak ada tim reguler yang perlu direset.',
                'data' => [
                    'reset_teams_count' => 0,
                ],
            ]);
        }

        DB::transaction(function () use ($regularTeamIds) {
            StudentProfile::whereIn('team_id', $regularTeamIds)->update(['team_id' => null]);
            Project::whereIn('project_team', $regularTeamIds)->delete();
            Team::whereIn('id', $regularTeamIds)->delete();
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Hasil pembagian tim reguler berhasil direset.',
            'data' => [
                'reset_teams_count' => $regularTeamIds->count(),
            ],
        ]);
    }

    /**
     * Assign Assistant (supervisor) to a specific team (Dosen).
     */
    public function assignSupervisor(Request $request, int $teamId): JsonResponse
    {
        $validated = $request->validate([
            'supervisor_id' => ['required', 'integer', 'exists:users,id'],
        ], [
            'supervisor_id.required' => 'Asisten Praktikum (supervisor) wajib dipilih.',
            'supervisor_id.exists' => 'Asisten Praktikum yang dipilih tidak ditemukan.',
        ]);

        $team = Team::find($teamId);
        if (! $team) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kelompok tidak ditemukan.',
            ], 404);
        }

        $supervisor = User::find($validated['supervisor_id']);
        $supRole = $supervisor->user_role instanceof UserRole ? $supervisor->user_role->value : (string) $supervisor->user_role;
        $normalized = UserRole::normalize($supRole);

        if (! in_array($normalized, [UserRole::Assistant->value, UserRole::Lecturer->value], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'User yang dipilih bukan Asisten Praktikum atau Dosen.',
            ], 422);
        }

        $team->supervisor = $supervisor->id;
        $team->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Asisten Praktikum berhasil ditetapkan ke kelompok.',
            'data' => [
                'team_id' => $team->id,
                'supervisor' => [
                    'id' => $supervisor->id,
                    'name' => $supervisor->name,
                    'email' => $supervisor->email,
                    'role' => $normalized,
                ],
            ],
        ]);
    }

    /**
     * Store or update project metadata for a team (Dosen, Asprak).
     */
    public function storeOrUpdateProject(Request $request, int $teamId): JsonResponse
    {
        $team = Team::with('members')->find($teamId);
        if (! $team) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kelompok tidak ditemukan.',
            ], 404);
        }

        $user = $request->user();
        $userRole = $user->user_role instanceof UserRole ? $user->user_role->value : (string) $user->user_role;
        $normalized = UserRole::normalize($userRole);

        // Assistant authorization: only allowed for their supervised teams
        if ($normalized === UserRole::Assistant->value && $team->supervisor && $team->supervisor !== $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak. Anda hanya dapat mengelola proyek kelompok yang Anda bimbing.',
            ], 403);
        }

        $existingProject = Project::where('project_team', $team->id)->first();

        $validated = $request->validate([
            'project_name' => [
                'required',
                'string',
                'max:128',
                $existingProject ? Rule::unique('projects', 'project_name')->ignore($existingProject->id) : Rule::unique('projects', 'project_name'),
            ],
            'project_owner' => ['nullable', 'integer', 'exists:users,id'],
            'project_associate' => ['nullable', 'string', 'max:64'],
            'project_description' => ['nullable', 'string', 'max:255'],
        ], [
            'project_name.required' => 'Nama proyek wajib diisi.',
            'project_name.unique' => 'Nama proyek sudah digunakan oleh kelompok lain.',
            'project_owner.exists' => 'User pemilik proyek tidak valid.',
        ]);

        // Resolve project owner
        $ownerId = $request->input('project_owner');
        if (! $ownerId) {
            if ($existingProject) {
                $ownerId = $existingProject->project_owner;
            } else {
                $memberUserIds = $team->members()->pluck('user_id');
                $existingOwnerIds = Project::pluck('project_owner')->toArray();
                $availableMemberId = $memberUserIds->first(fn ($id) => ! in_array($id, $existingOwnerIds, true));

                if ($availableMemberId) {
                    $ownerId = $availableMemberId;
                } elseif (! in_array($user->id, $existingOwnerIds, true)) {
                    $ownerId = $user->id;
                } else {
                    $freeUser = User::whereNotIn('id', $existingOwnerIds)->first();
                    $ownerId = $freeUser?->id ?? $user->id;
                }
            }
        }

        $project = Project::updateOrCreate(
            ['project_team' => $team->id],
            [
                'project_name' => $validated['project_name'],
                'project_owner' => $ownerId,
                'project_associate' => $validated['project_associate'] ?? null,
                'project_description' => $validated['project_description'] ?? null,
            ]
        );

        $project->load('owner');

        return response()->json([
            'status' => 'success',
            'message' => 'Metadata proyek berhasil disimpan.',
            'data' => [
                'id' => $project->id,
                'project_team' => $project->project_team,
                'project_name' => $project->project_name,
                'project_associate' => $project->project_associate,
                'project_description' => $project->project_description,
                'owner' => $project->owner ? [
                    'id' => $project->owner->id,
                    'name' => $project->owner->name,
                    'email' => $project->owner->email,
                ] : null,
                'updated_at' => $project->updated_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Register an exclusive team (GEMASTIK / LIDM Software).
     * Creates team record, sets student_profiles.is_bypass = true, and binds student team_id.
     */
    public function storeExclusive(Request $request): JsonResponse
    {
        $request->validate([
            'team_type' => ['nullable', 'in:internal,external'],
            'team_name' => ['nullable', 'string', 'max:128'],
            'name' => ['nullable', 'string', 'max:128'],
            'project_name' => ['nullable', 'string', 'max:128'],
            'supervisor' => ['nullable', 'exists:users,id'],
            'supervisor_id' => ['nullable', 'exists:users,id'],
            'members' => ['nullable', 'array'],
            'student_ids' => ['nullable', 'array'],
            'emails' => ['nullable', 'array'],
        ]);

        $team = DB::transaction(function () use ($request) {
            $team = Team::create([
                'team_type' => $request->input('team_type', 'external'),
                'is_locked' => true,
                'supervisor' => $request->input('supervisor') ?? $request->input('supervisor_id'),
            ]);

            // Consolidate member inputs
            $rawMembers = [];

            if ($request->has('members')) {
                $rawMembers = array_merge($rawMembers, (array) $request->input('members'));
            }

            if ($request->has('student_ids')) {
                foreach ((array) $request->input('student_ids') as $id) {
                    $rawMembers[] = ['user_id' => $id];
                }
            }

            if ($request->has('emails')) {
                foreach ((array) $request->input('emails') as $email) {
                    $rawMembers[] = ['email' => $email];
                }
            }

            $firstUserId = null;

            foreach ($rawMembers as $memberItem) {
                $user = null;
                $nim = null;
                $roleId = null;

                if (is_numeric($memberItem)) {
                    $user = User::find($memberItem);
                } elseif (is_string($memberItem)) {
                    $email = strtolower(trim($memberItem));
                    $user = User::where('email', $email)->first();
                    if (! $user) {
                        $user = User::create([
                            'email' => $email,
                            'user_role' => UserRole::Student,
                            'status' => 'active',
                        ]);
                    }
                } elseif (is_array($memberItem)) {
                    if (! empty($memberItem['user_id'])) {
                        $user = User::find($memberItem['user_id']);
                    } elseif (! empty($memberItem['email'])) {
                        $email = strtolower(trim((string) $memberItem['email']));
                        $user = User::where('email', $email)->first();
                        if (! $user) {
                            $user = User::create([
                                'email' => $email,
                                'name' => $memberItem['name'] ?? null,
                                'user_role' => UserRole::Student,
                                'status' => 'active',
                            ]);
                        }
                    }

                    $nim = $memberItem['nim'] ?? null;
                    $roleId = $memberItem['role_id'] ?? $memberItem['role_1_id'] ?? null;
                }

                if ($user) {
                    if (! $firstUserId) {
                        $firstUserId = $user->id;
                    }

                    // Ensure user role is student
                    if ($user->user_role !== UserRole::Student) {
                        $user->user_role = UserRole::Student;
                        $user->save();
                    }

                    $profile = StudentProfile::firstOrNew(['user_id' => $user->id]);
                    $profile->team_id = $team->id;
                    $profile->is_bypass = true;

                    if ($nim) {
                        $profile->nim = $nim;
                    }

                    if ($roleId) {
                        $profile->role_1_id = $roleId;
                    }

                    $profile->save();
                }
            }

            // Create Project metadata if project/team name provided
            $projectName = $request->input('team_name') ?? $request->input('project_name') ?? $request->input('name');
            if ($projectName && $firstUserId) {
                Project::updateOrCreate(
                    ['project_team' => $team->id],
                    [
                        'project_name' => (string) $projectName,
                        'project_owner' => $firstUserId,
                        'project_associate' => (string) $request->input('project_associate', 'Jalur GEMASTIK / LIDM Software'),
                        'project_description' => (string) $request->input('project_description', 'Tim Jalur Eksklusif GEMASTIK / LIDM'),
                    ]
                );
            }

            return $team;
        });

        $team->load(['members.user', 'project', 'supervisorUser']);

        return response()->json([
            'status' => 'success',
            'message' => 'Tim eksklusif jalur GEMASTIK / LIDM Software berhasil didaftarkan.',
            'data' => [
                'id' => $team->id,
                'team_type' => $team->team_type,
                'is_locked' => (bool) $team->is_locked,
                'supervisor' => $team->supervisor,
                'project' => $team->project,
                'members' => UserResource::collection($team->members->map->user->filter()),
            ],
        ], 201);
    }

    /**
     * In-place Fisher-Yates array shuffling.
     *
     * @template T
     *
     * @param  list<T>  $items
     */
    protected function fisherYatesShuffle(array &$items): void
    {
        $count = count($items);
        for ($i = $count - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            $temp = $items[$i];
            $items[$i] = $items[$j];
            $items[$j] = $temp;
        }
    }

    /**
     * Format a collection of Team models into standard JSON array.
     *
     * @param  Collection<int, Team>|\Illuminate\Database\Eloquent\Collection<int, Team>  $teams
     * @return list<array<string, mixed>>
     */
    protected function formatTeamsCollection($teams): array
    {
        return $teams->map(function (Team $team) {
            return [
                'id' => $team->id,
                'team_type' => $team->team_type,
                'is_locked' => (bool) $team->is_locked,
                'supervisor' => $team->supervisorUser ? [
                    'id' => $team->supervisorUser->id,
                    'name' => $team->supervisorUser->name,
                    'email' => $team->supervisorUser->email,
                ] : null,
                'project' => $team->project ? [
                    'id' => $team->project->id,
                    'project_name' => $team->project->project_name,
                    'project_associate' => $team->project->project_associate,
                    'project_description' => $team->project->project_description,
                    'owner' => $team->project->owner ? [
                        'id' => $team->project->owner->id,
                        'name' => $team->project->owner->name,
                        'email' => $team->project->owner->email,
                    ] : null,
                ] : null,
                'members_count' => $team->members->count(),
                'members' => $team->members->map(function ($member) {
                    $finalRole = $member->evaluation?->finalRole
                        ?? $member->evaluation?->recommendedRole
                        ?? $member->role1;

                    return [
                        'id' => $member->id,
                        'user_id' => $member->user_id,
                        'name' => $member->user?->name,
                        'email' => $member->user?->email,
                        'nim' => $member->nim,
                        'is_bypass' => (bool) $member->is_bypass,
                        'role' => $finalRole ? [
                            'id' => $finalRole->id,
                            'code' => $finalRole->code,
                            'name' => $finalRole->name,
                        ] : null,
                    ];
                })->values(),
            ];
        })->values()->all();
    }
}

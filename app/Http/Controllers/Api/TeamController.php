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
use Illuminate\Support\Facades\DB;

class TeamController extends Controller
{
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
}

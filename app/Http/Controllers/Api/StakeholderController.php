<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignStakeholderRoleRequest;
use App\Http\Requests\InitiateLecturerRequest;
use App\Http\Requests\StoreStakeholderRequest;
use App\Http\Resources\UserResource;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StakeholderController extends Controller
{
    /**
     * Display a listing of stakeholders with optional filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->with('studentProfile');

        if ($request->filled('role')) {
            $normalizedRole = UserRole::normalize((string) $request->input('role'));
            if ($normalizedRole !== null) {
                $query->where('user_role', $normalizedRole);
            }
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('is_bypass')) {
            $isBypass = filter_var($request->input('is_bypass'), FILTER_VALIDATE_BOOLEAN);
            $query->whereHas('studentProfile', function ($q) use ($isBypass) {
                $q->where('is_bypass', $isBypass);
            });
        }

        $perPage = (int) $request->input('per_page', 15);
        $stakeholders = $query->latest('id')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar stakeholder berhasil diambil.',
            'data' => UserResource::collection($stakeholders),
            'meta' => [
                'current_page' => $stakeholders->currentPage(),
                'last_page' => $stakeholders->lastPage(),
                'per_page' => $stakeholders->perPage(),
                'total' => $stakeholders->total(),
            ],
        ]);
    }

    /**
     * Initiate PAD lecturer email as full admin.
     */
    public function initiateLecturer(InitiateLecturerRequest $request): JsonResponse
    {
        if ($request->has('lecturers')) {
            $lecturersInput = (array) $request->input('lecturers');
            $createdUsers = DB::transaction(function () use ($lecturersInput) {
                $results = [];
                foreach ($lecturersInput as $lecturer) {
                    $email = strtolower(trim((string) $lecturer['email']));
                    $name = isset($lecturer['name']) ? (string) $lecturer['name'] : null;

                    $user = User::updateOrCreate(
                        ['email' => $email],
                        [
                            'name' => $name,
                            'user_role' => UserRole::Admin,
                            'status' => 'active',
                        ]
                    );
                    $results[] = $user;
                }

                return $results;
            });

            return response()->json([
                'status' => 'success',
                'message' => count($createdUsers).' dosen pengampu berhasil diinisiasi sebagai full admin.',
                'data' => UserResource::collection(collect($createdUsers)),
            ], 201);
        }

        $email = strtolower(trim((string) $request->input('email')));
        $name = $request->input('name');

        $user = DB::transaction(function () use ($email, $name) {
            return User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'user_role' => UserRole::Admin,
                    'status' => 'active',
                ]
            );
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Email dosen pengampu berhasil diinisiasi sebagai full admin.',
            'data' => new UserResource($user),
        ], 201);
    }

    /**
     * Store one or multiple stakeholders (whitelist input).
     */
    public function store(StoreStakeholderRequest $request): JsonResponse
    {
        if ($request->has('stakeholders')) {
            $stakeholdersInput = (array) $request->input('stakeholders');
            $createdUsers = DB::transaction(function () use ($stakeholdersInput) {
                $results = [];
                foreach ($stakeholdersInput as $item) {
                    $results[] = $this->createOrUpdateStakeholder($item);
                }

                return $results;
            });

            return response()->json([
                'status' => 'success',
                'message' => count($createdUsers).' stakeholder berhasil didata ke dalam sistem.',
                'data' => UserResource::collection(collect($createdUsers)),
            ], 201);
        }

        $user = DB::transaction(function () use ($request) {
            return $this->createOrUpdateStakeholder($request->validated());
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Data stakeholder berhasil disimpan ke dalam whitelist sistem.',
            'data' => new UserResource($user),
        ], 201);
    }

    /**
     * Assign / update role for an existing stakeholder by ID or email.
     */
    public function assignRole(AssignStakeholderRoleRequest $request, ?User $user = null): JsonResponse
    {
        if ($user === null || ! $user->exists) {
            $email = $request->input('email');
            if (! $email) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User ID pada parameter URL atau email pada request body wajib disertakan.',
                ], 400);
            }

            $user = User::where('email', strtolower(trim((string) $email)))->first();

            if (! $user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Pengguna dengan email tersebut tidak ditemukan dalam sistem.',
                ], 404);
            }
        }

        $rawRole = (string) $request->input('role');
        $normalizedRole = UserRole::normalize($rawRole);

        if ($normalizedRole === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Peran (role) tidak valid.',
            ], 422);
        }

        DB::transaction(function () use ($user, $normalizedRole, $request) {
            $user->user_role = $normalizedRole;
            $user->save();

            if ($normalizedRole === UserRole::Student->value) {
                $profileData = [];

                if ($request->has('is_bypass')) {
                    $profileData['is_bypass'] = filter_var($request->input('is_bypass'), FILTER_VALIDATE_BOOLEAN);
                }

                if ($request->filled('nim')) {
                    $profileData['nim'] = (string) $request->input('nim');
                }

                StudentProfile::updateOrCreate(
                    ['user_id' => $user->id],
                    $profileData
                );
            }
        });

        $user->load('studentProfile');

        return response()->json([
            'status' => 'success',
            'message' => "Peran stakeholder berhasil diperbarui menjadi {$user->user_role->label()}.",
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Display the specified stakeholder.
     */
    public function show(User $user): JsonResponse
    {
        $user->load('studentProfile');

        return response()->json([
            'status' => 'success',
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Remove or archive the specified stakeholder.
     */
    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Stakeholder berhasil dihapus dari sistem.',
        ]);
    }

    /**
     * Helper to create or update a single stakeholder.
     *
     * @param  array<string, mixed>  $data
     */
    protected function createOrUpdateStakeholder(array $data): User
    {
        $email = strtolower(trim((string) $data['email']));
        $rawRole = (string) $data['role'];
        $role = UserRole::normalize($rawRole) ?? UserRole::Student->value;

        $userAttributes = [
            'user_role' => $role,
            'status' => 'active',
        ];

        if (! empty($data['name'])) {
            $userAttributes['name'] = (string) $data['name'];
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            $userAttributes
        );

        if ($role === UserRole::Student->value) {
            $profileAttributes = [];

            if (isset($data['is_bypass'])) {
                $profileAttributes['is_bypass'] = filter_var($data['is_bypass'], FILTER_VALIDATE_BOOLEAN);
            }

            if (! empty($data['nim'])) {
                $profileAttributes['nim'] = (string) $data['nim'];
            }

            StudentProfile::updateOrCreate(
                ['user_id' => $user->id],
                $profileAttributes
            );
        }

        $user->load('studentProfile');

        return $user;
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Imports\KrsWhitelistImport;
use App\Models\StudentProfile;
use App\Models\StudentRoleEvaluation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class UserController extends Controller
{
    /**
     * Display a listing of registered users with optional filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->with('studentProfile');

        if ($request->filled('user_role') || $request->filled('role')) {
            $rawRole = (string) ($request->input('user_role') ?? $request->input('role'));
            $normalizedRole = UserRole::normalize($rawRole);
            if ($normalizedRole !== null) {
                $query->where('user_role', $normalizedRole);
            }
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('search') || $request->filled('q')) {
            $search = (string) ($request->input('search') ?? $request->input('q'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('studentProfile', function ($sp) use ($search) {
                        $sp->where('nim', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('is_bypass')) {
            $isBypass = filter_var($request->input('is_bypass'), FILTER_VALIDATE_BOOLEAN);
            $query->whereHas('studentProfile', function ($q) use ($isBypass) {
                $q->where('is_bypass', $isBypass);
            });
        }

        if (filter_var($request->input('all'), FILTER_VALIDATE_BOOLEAN)) {
            $users = $query->latest('id')->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Daftar seluruh pengguna berhasil diambil.',
                'data' => UserResource::collection($users),
                'total' => $users->count(),
            ]);
        }

        $perPage = (int) $request->input('per_page', 15);
        $users = $query->latest('id')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar seluruh pengguna berhasil diambil.',
            'data' => UserResource::collection($users),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * Initiate lecturer email and grant Lecturer/Admin access role.
     */
    public function initiateLecturers(Request $request): JsonResponse
    {
        if ($request->has('lecturers')) {
            $request->validate([
                'lecturers' => ['required', 'array', 'min:1'],
                'lecturers.*.email' => ['required', 'email', 'max:128'],
                'lecturers.*.name' => ['nullable', 'string', 'max:128'],
                'lecturers.*.role' => ['nullable', 'string'],
                'lecturers.*.user_role' => ['nullable', 'string'],
            ]);

            $lecturersInput = (array) $request->input('lecturers');
            $createdUsers = DB::transaction(function () use ($lecturersInput) {
                $results = [];
                foreach ($lecturersInput as $lecturer) {
                    $email = strtolower(trim((string) $lecturer['email']));
                    $name = isset($lecturer['name']) ? (string) $lecturer['name'] : null;
                    $rawRole = (string) ($lecturer['role'] ?? $lecturer['user_role'] ?? 'admin');
                    $role = UserRole::normalize($rawRole) ?? UserRole::Admin->value;

                    $user = User::updateOrCreate(
                        ['email' => $email],
                        [
                            'name' => $name,
                            'user_role' => $role,
                            'status' => 'active',
                        ]
                    );
                    $results[] = $user;
                }

                return $results;
            });

            return response()->json([
                'status' => 'success',
                'message' => count($createdUsers).' Dosen Pengampu berhasil diinisiasi.',
                'data' => UserResource::collection(collect($createdUsers)),
            ], 201);
        }

        $request->validate([
            'email' => ['required', 'email', 'max:128'],
            'name' => ['nullable', 'string', 'max:128'],
            'role' => ['nullable', 'string'],
            'user_role' => ['nullable', 'string'],
        ]);

        $email = strtolower(trim((string) $request->input('email')));
        $name = $request->input('name');
        $rawRole = (string) ($request->input('role') ?? $request->input('user_role') ?? 'admin');
        $role = UserRole::normalize($rawRole) ?? UserRole::Admin->value;

        $user = DB::transaction(function () use ($email, $name, $role) {
            return User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'user_role' => $role,
                    'status' => 'active',
                ]
            );
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Email Dosen Pengampu berhasil diinisiasi.',
            'data' => new UserResource($user),
        ], 201);
    }

    /**
     * Input manual (typing) email stakeholder into whitelist.
     */
    public function storeWhitelist(Request $request): JsonResponse
    {
        $roleValidator = function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_string($value) || UserRole::normalize($value) === null) {
                $fail('Peran (user_role) "'.$value.'" tidak valid. Pilihan valid: mahasiswa, praktisi, asisten, dosen, admin.');
            }
        };

        if ($request->has('users') || $request->has('whitelist') || $request->has('stakeholders')) {
            $inputList = (array) ($request->input('users') ?? $request->input('whitelist') ?? $request->input('stakeholders'));

            $createdUsers = DB::transaction(function () use ($inputList) {
                $results = [];
                foreach ($inputList as $item) {
                    $results[] = $this->createOrUpdateWhitelistUser($item);
                }

                return $results;
            });

            return response()->json([
                'status' => 'success',
                'message' => count($createdUsers).' pengguna berhasil didaftarkan ke whitelist.',
                'data' => UserResource::collection(collect($createdUsers)),
            ], 201);
        }

        $request->validate([
            'email' => ['required', 'email', 'max:128'],
            'user_role' => ['nullable', $roleValidator],
            'role' => ['nullable', $roleValidator],
            'name' => ['nullable', 'string', 'max:128'],
            'nim' => ['nullable', 'string', 'max:50'],
            'is_bypass' => ['nullable', 'boolean'],
        ]);

        $rawRole = (string) ($request->input('user_role') ?? $request->input('role') ?? 'student');
        if (empty($rawRole)) {
            $rawRole = 'student';
        }

        $user = DB::transaction(function () use ($request, $rawRole) {
            $data = $request->all();
            $data['user_role'] = $rawRole;

            return $this->createOrUpdateWhitelistUser($data);
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Data pengguna berhasil dimasukkan ke dalam daftar whitelist.',
            'data' => new UserResource($user),
        ], 201);
    }

    /**
     * Import student whitelist from CSV or Excel file using Maatwebsite/Laravel-Excel.
     */
    public function importWhitelist(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,xlsx,xls,txt', 'max:10240'],
        ]);

        try {
            $import = new KrsWhitelistImport;
            Excel::import($import, $request->file('file'));

            $totalImported = $import->getImportedCount();
            $errors = $import->getErrors();

            return response()->json([
                'status' => 'success',
                'message' => "Import selesai. Berhasil memasukkan {$totalImported} mahasiswa dari KRS ke tabel users dan student_profiles.",
                'total_imported' => $totalImported,
                'errors' => $errors,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengimpor file KRS: '.$e->getMessage(),
            ], 400);
        }
    }

    /**
     * Remove email from whitelist (only permitted if role is not locked).
     */
    public function destroy(int|string $id): JsonResponse
    {
        $user = User::with(['studentProfile.team', 'evaluation'])->find($id);

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pengguna tidak ditemukan dalam sistem.',
            ], 404);
        }

        // Cek apakah role telah dikunci di evaluasi peran
        $isEvaluationLocked = (bool) ($user->evaluation?->is_locked ?? false)
            || StudentRoleEvaluation::where('user_id', $user->id)->where('is_locked', true)->exists();

        // Cek apakah role/tim telah dikunci pada tim yang terkait
        $isTeamLocked = (bool) ($user->studentProfile?->team?->is_locked ?? false);

        if ($isEvaluationLocked || $isTeamLocked) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak. Pengguna tidak dapat dihapus dari whitelist karena peran (role) sudah dikunci.',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Email pengguna berhasil dihapus dari daftar whitelist.',
        ]);
    }

    /**
     * Helper to create or update a whitelist user and student profile if applicable.
     *
     * @param  array<string, mixed>  $data
     */
    protected function createOrUpdateWhitelistUser(array $data): User
    {
        $email = strtolower(trim((string) $data['email']));
        $rawRole = (string) ($data['user_role'] ?? $data['role'] ?? 'student');
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

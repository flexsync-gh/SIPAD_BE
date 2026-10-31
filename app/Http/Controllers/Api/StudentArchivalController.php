<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class StudentArchivalController extends Controller
{
    /**
     * Get list of active students eligible for archival (Dosen).
     * Supports filtering by Academic Year / Angkatan and search.
     */
    public function archivable(Request $request): JsonResponse
    {
        $query = User::where('user_role', UserRole::Student->value)
            ->where('status', 'active')
            ->with([
                'studentProfile',
                'evaluation.finalRole',
            ]);

        // Filter by Tahun Ajaran / Angkatan
        if ($request->filled('angkatan') || $request->filled('academic_year') || $request->filled('year')) {
            $yearParam = trim((string) ($request->input('angkatan') ?? $request->input('academic_year') ?? $request->input('year')));

            // Extract 2-digit angkatan (e.g., '2024' -> '24', '24' -> '24', '2024/2025' -> '24')
            $twoDigit = null;
            if (preg_match('/(?:20)?(\d{2})/', $yearParam, $matches)) {
                $twoDigit = $matches[1];
            }

            $query->whereHas('studentProfile', function ($sp) use ($yearParam, $twoDigit) {
                $sp->where(function ($q) use ($yearParam, $twoDigit) {
                    $q->where('nim', 'like', "{$yearParam}%");
                    if ($twoDigit !== null) {
                        $q->orWhere('nim', 'like', "{$twoDigit}/%");
                    }
                });
            });
        }

        // Search by name, email, or NIM
        if ($request->filled('search') || $request->filled('q')) {
            $search = trim((string) ($request->input('search') ?? $request->input('q')));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('studentProfile', function ($sp) use ($search) {
                        $sp->where('nim', 'like', "%{$search}%");
                    });
            });
        }

        $students = $query->orderBy('name', 'asc')->get();

        $data = $students->map(function (User $student) {
            $nim = $student->studentProfile?->nim;
            $angkatan = null;
            if ($nim && preg_match('/^(\d{2})\//', $nim, $matches)) {
                $angkatan = '20'.$matches[1];
            }

            $finalRole = $student->evaluation?->finalRole;

            return [
                'user_id' => $student->id,
                'name' => $student->name,
                'email' => $student->email,
                'status' => $student->status,
                'nim' => $nim,
                'angkatan' => $angkatan,
                'team_id' => $student->studentProfile?->team_id,
                'final_role' => $finalRole ? [
                    'id' => $finalRole->id,
                    'code' => $finalRole->code,
                    'name' => $finalRole->name,
                ] : null,
                'created_at' => $student->created_at?->toISOString(),
            ];
        });

        $currentAcademicYear = AppSetting::where('key', 'academic_year')->value('value') ?? '2026/2027';

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar mahasiswa aktif yang dapat diarsipkan berhasil diambil.',
            'data' => $data,
            'meta' => [
                'total' => $students->count(),
                'academic_year' => $currentAcademicYear,
            ],
        ]);
    }

    /**
     * Archive selected active students (Dosen).
     * Executes UPDATE users SET status = 'archived' WHERE id IN (...) in Database Transaction.
     */
    public function archive(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ], [
            'user_ids.required' => 'Daftar ID mahasiswa yang akan diarsipkan wajib dipilih.',
            'user_ids.array' => 'Format user_ids harus berupa array.',
            'user_ids.min' => 'Pilih minimal satu mahasiswa untuk diarsipkan.',
            'user_ids.*.exists' => 'Salah satu ID mahasiswa yang dipilih tidak ditemukan.',
        ]);

        $userIds = $validated['user_ids'];

        try {
            $affected = DB::transaction(function () use ($userIds) {
                return User::whereIn('id', $userIds)
                    ->where('user_role', UserRole::Student->value)
                    ->where('status', 'active')
                    ->update(['status' => 'archived']);
            });

            return response()->json([
                'status' => 'success',
                'message' => "Sebanyak {$affected} akun mahasiswa berhasil diarsipkan.",
                'data' => [
                    'archived_count' => $affected,
                    'archived_user_ids' => $userIds,
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengarsipkan akun: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Unarchive / restore selected archived students (Dosen).
     */
    public function unarchive(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $userIds = $validated['user_ids'];

        try {
            $affected = DB::transaction(function () use ($userIds) {
                return User::whereIn('id', $userIds)
                    ->where('user_role', UserRole::Student->value)
                    ->where('status', 'archived')
                    ->update(['status' => 'active']);
            });

            return response()->json([
                'status' => 'success',
                'message' => "Sebanyak {$affected} akun mahasiswa berhasil dipulihkan ke status aktif.",
                'data' => [
                    'restored_count' => $affected,
                    'user_ids' => $userIds,
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memulihkan status akun: '.$e->getMessage(),
            ], 500);
        }
    }
}

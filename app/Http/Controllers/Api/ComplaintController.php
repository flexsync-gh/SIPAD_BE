<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ComplaintController extends Controller
{
    /**
     * Submit a complaint ticket against a fellow team member (Mahasiswa).
     * Validates required evidence file and that reported student is in the same team.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        // 1. Validate input fields
        $validated = $request->validate([
            'reported_id' => ['required', 'integer', 'exists:users,id'],
            'description' => ['required', 'string', 'min:5', 'max:2000'],
            'evidence_file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ], [
            'reported_id.required' => 'Mahasiswa yang dilaporkan wajib dipilih.',
            'reported_id.exists' => 'Mahasiswa yang dilaporkan tidak ditemukan.',
            'description.required' => 'Deskripsi keluhan wajib diisi.',
            'evidence_file.required' => 'Berkas bukti pengaduan wajib dilampirkan.',
            'evidence_file.file' => 'Berkas bukti harus berupa file yang valid.',
            'evidence_file.mimes' => 'Format berkas bukti harus berupa JPG, JPEG, PNG, atau PDF.',
            'evidence_file.max' => 'Ukuran berkas bukti tidak boleh melebihi 10 MB.',
        ]);

        // 2. Prevent self-reporting
        if ((int) $validated['reported_id'] === (int) $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak dapat melaporkan diri Anda sendiri.',
                'errors' => [
                    'reported_id' => ['Anda tidak dapat melaporkan diri Anda sendiri.'],
                ],
            ], 422);
        }

        // 3. Verify reporter is assigned to a team
        $reporterProfile = $user->studentProfile;
        if (! $reporterProfile || ! $reporterProfile->team_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda belum terdaftar dalam kelompok sehingga tidak dapat mengajukan keluhan tim.',
            ], 422);
        }

        // 4. Verify reported student is in the exact same team
        $reportedUser = User::with('studentProfile')->find($validated['reported_id']);
        $reportedProfile = $reportedUser?->studentProfile;

        if (! $reportedProfile || (int) $reportedProfile->team_id !== (int) $reporterProfile->team_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Mahasiswa yang dilaporkan harus berada dalam satu kelompok yang sama dengan Anda.',
                'errors' => [
                    'reported_id' => ['Mahasiswa yang dilaporkan harus berada dalam satu kelompok yang sama dengan Anda.'],
                ],
            ], 422);
        }

        // 5. Store evidence file in private storage (local disk)
        $evidencePath = $request->file('evidence_file')->store('complaints', 'local');

        // 6. Create complaint record
        $complaint = Complaint::create([
            'reporter_id' => $user->id,
            'reported_id' => $reportedProfile->user_id,
            'team_id' => $reporterProfile->team_id,
            'description' => $validated['description'],
            'evidence_file_path' => $evidencePath,
        ]);

        $complaint->load(['reporter.studentProfile', 'reported.studentProfile', 'team', 'reviewer']);

        return response()->json([
            'status' => 'success',
            'message' => 'Tiket pengaduan anggota tim berhasil dikirim.',
            'data' => $this->formatComplaint($complaint),
        ], 201);
    }

    /**
     * Get list of complaint tickets based on user role:
     * - Dosen / Praktisi: all complaints or filter by reported student.
     * - Asprak: only complaints in teams they supervise.
     * - Mahasiswa: only complaints filed by themselves.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $userRole = $user->user_role instanceof UserRole ? $user->user_role->value : (string) $user->user_role;
        $normalized = UserRole::normalize($userRole);

        $query = Complaint::with([
            'reporter.studentProfile',
            'reported.studentProfile',
            'team',
            'reviewer',
        ])->latest('id');

        // Role-based visibility filtering
        if ($normalized === UserRole::Student->value) {
            // Mahasiswa only sees complaints filed by themselves
            $query->where('reporter_id', $user->id);
        } elseif ($normalized === UserRole::Assistant->value) {
            // Asprak only sees complaints in their supervised teams
            $query->whereHas('team', function ($q) use ($user) {
                $q->where('supervisor', $user->id);
            });
        } elseif (in_array($normalized, [UserRole::Lecturer->value, UserRole::Practitioner->value, UserRole::Admin->value], true)) {
            // Dosen / Praktisi / Admin can view all or filter by reported_id
            if ($request->filled('reported_id')) {
                $query->where('reported_id', (int) $request->input('reported_id'));
            }

            if ($request->filled('team_id')) {
                $query->where('team_id', (int) $request->input('team_id'));
            }

            if ($request->has('is_reviewed') && $request->input('is_reviewed') !== null && $request->input('is_reviewed') !== '') {
                $isReviewed = filter_var($request->input('is_reviewed'), FILTER_VALIDATE_BOOLEAN);
                if ($isReviewed) {
                    $query->whereNotNull('reviewed_at');
                } else {
                    $query->whereNull('reviewed_at');
                }
            }
        }

        $complaints = $query->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar tiket pengaduan berhasil diambil.',
            'data' => $complaints->map(fn (Complaint $c) => $this->formatComplaint($c))->values(),
        ]);
    }

    /**
     * Get complaint ticket details along with download URL.
     * Accessible by Dosen, Praktisi, Asprak (for their team), and Pelapor.
     */
    public function show(Request $request, int $id): JsonResponse|Response
    {
        $complaint = Complaint::with([
            'reporter.studentProfile',
            'reported.studentProfile',
            'team',
            'reviewer',
        ])->find($id);

        if (! $complaint) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tiket pengaduan tidak ditemukan.',
            ], 404);
        }

        $user = $request->user();
        if (! $this->canViewComplaint($user, $complaint)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak. Anda tidak memiliki izin untuk melihat tiket pengaduan rahasia ini.',
            ], 403);
        }

        // Direct stream / download request via ?download=1
        if ($request->boolean('download')) {
            return $this->downloadEvidence($request, $id);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Detail tiket pengaduan berhasil diambil.',
            'data' => $this->formatComplaint($complaint),
        ]);
    }

    /**
     * Stream or download evidence file from private storage.
     */
    public function downloadEvidence(Request $request, int $id): Response
    {
        $complaint = Complaint::with('team')->find($id);

        if (! $complaint) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tiket pengaduan tidak ditemukan.',
            ], 404);
        }

        $user = $request->user();
        if (! $this->canViewComplaint($user, $complaint)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak. Anda tidak memiliki izin untuk mengunduh berkas bukti pengaduan ini.',
            ], 403);
        }

        if (! Storage::disk('local')->exists($complaint->evidence_file_path)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Berkas bukti pengaduan tidak ditemukan pada server penyimpanan.',
            ], 404);
        }

        if ($request->boolean('download')) {
            return Storage::disk('local')->download($complaint->evidence_file_path);
        }

        return Storage::disk('local')->response($complaint->evidence_file_path);
    }

    /**
     * Mark complaint as reviewed (Dosen, Asprak).
     */
    public function review(Request $request, int $id): JsonResponse
    {
        $complaint = Complaint::with('team')->find($id);

        if (! $complaint) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tiket pengaduan tidak ditemukan.',
            ], 404);
        }

        $user = $request->user();
        if (! $this->canReviewComplaint($user, $complaint)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak. Anda tidak memiliki izin untuk meninjau tiket pengaduan ini.',
            ], 403);
        }

        $complaint->reviewed_by = $user->id;
        $complaint->reviewed_at = now();
        $complaint->save();

        $complaint->load(['reporter.studentProfile', 'reported.studentProfile', 'team', 'reviewer']);

        return response()->json([
            'status' => 'success',
            'message' => 'Laporan pengaduan berhasil ditandai telah ditinjau.',
            'data' => $this->formatComplaint($complaint),
        ]);
    }

    /**
     * Check if user can view the given complaint.
     */
    protected function canViewComplaint(User $user, Complaint $complaint): bool
    {
        $role = $user->user_role instanceof UserRole ? $user->user_role->value : (string) $user->user_role;
        $normalized = UserRole::normalize($role);

        // Admin, Lecturer, Practitioner can view all complaints
        if (in_array($normalized, [UserRole::Admin->value, UserRole::Lecturer->value, UserRole::Practitioner->value], true)) {
            return true;
        }

        // Assistant (Asprak) can only view complaints from their supervised team
        if ($normalized === UserRole::Assistant->value) {
            return (int) $complaint->team?->supervisor === (int) $user->id;
        }

        // Student can only view complaints filed by themselves (pelapor)
        if ($normalized === UserRole::Student->value) {
            return (int) $complaint->reporter_id === (int) $user->id;
        }

        return false;
    }

    /**
     * Check if user can review the given complaint.
     */
    protected function canReviewComplaint(User $user, Complaint $complaint): bool
    {
        $role = $user->user_role instanceof UserRole ? $user->user_role->value : (string) $user->user_role;
        $normalized = UserRole::normalize($role);

        // Admin and Lecturer can review any complaint
        if (in_array($normalized, [UserRole::Admin->value, UserRole::Lecturer->value], true)) {
            return true;
        }

        // Assistant (Asprak) can only review complaints from their supervised team
        if ($normalized === UserRole::Assistant->value) {
            return (int) $complaint->team?->supervisor === (int) $user->id;
        }

        return false;
    }

    /**
     * Format a Complaint model into standard JSON array.
     *
     * @return array<string, mixed>
     */
    protected function formatComplaint(Complaint $complaint): array
    {
        return [
            'id' => $complaint->id,
            'team_id' => $complaint->team_id,
            'description' => $complaint->description,
            'evidence_file_path' => $complaint->evidence_file_path,
            'evidence_download_url' => url("/api/complaints/{$complaint->id}/evidence"),
            'reporter' => $complaint->reporter ? [
                'id' => $complaint->reporter->id,
                'name' => $complaint->reporter->name,
                'email' => $complaint->reporter->email,
                'nim' => $complaint->reporter->studentProfile?->nim,
            ] : null,
            'reported' => $complaint->reported ? [
                'id' => $complaint->reported->id,
                'name' => $complaint->reported->name,
                'email' => $complaint->reported->email,
                'nim' => $complaint->reported->studentProfile?->nim,
            ] : null,
            'team' => $complaint->team ? [
                'id' => $complaint->team->id,
                'team_type' => $complaint->team->team_type,
                'supervisor_id' => $complaint->team->supervisor,
            ] : null,
            'reviewed_by' => $complaint->reviewer ? [
                'id' => $complaint->reviewer->id,
                'name' => $complaint->reviewer->name,
                'email' => $complaint->reviewer->email,
            ] : null,
            'reviewed_at' => $complaint->reviewed_at?->toISOString(),
            'is_reviewed' => $complaint->reviewed_at !== null,
            'created_at' => $complaint->created_at?->toISOString(),
        ];
    }
}

<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EvaluationController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SkillController;
use App\Http\Controllers\Api\StakeholderController;
use App\Http\Controllers\Api\StudentProfilingController;
use App\Http\Controllers\Api\SystemSettingController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PAD API Routes (v1 & Root aliases)
|--------------------------------------------------------------------------
*/

$registerRoutes = function () {
    // A. Modul Autentikasi & SSO UGM (Proses 1.0 & 3.0)
    Route::prefix('auth')->as('auth.')->group(function () {
        Route::get('/google/redirect', [AuthController::class, 'googleRedirect'])->name('google.redirect');
        Route::post('/google/callback', [AuthController::class, 'googleCallback'])->name('google.callback');

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me', [AuthController::class, 'me'])->name('me');
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        });
    });

    // B. Modul Pengelolaan Pengguna, Whitelist KRS & Tim Eksklusif (Proses 1.0 — Fase 1)
    Route::middleware('auth:sanctum')->group(function () {
        // 1. Inisiasi Dosen Pengampu oleh Master Admin
        Route::post('/admin/lecturers', [UserController::class, 'initiateLecturers'])
            ->middleware('role:admin')
            ->name('admin.lecturers');

        // 2. Mengambil daftar seluruh pengguna terdaftar (Dosen, Master Admin)
        Route::get('/users', [UserController::class, 'index'])
            ->middleware('role:lecturer,admin')
            ->name('users.index');

        // 3. Input manual (typing) email stakeholder ke whitelist (Dosen)
        Route::post('/users/whitelist', [UserController::class, 'storeWhitelist'])
            ->middleware('role:lecturer,admin')
            ->name('users.whitelist');

        // 4. Import Engine CSV/Excel daftar email & NIM mahasiswa (Dosen, Master Admin)
        Route::post('/users/whitelist/import', [UserController::class, 'importWhitelist'])
            ->middleware('role:lecturer,admin')
            ->name('users.whitelist.import');

        // 5. Mendaftarkan tim eksklusif jalur GEMASTIK / LIDM Software (Dosen)
        Route::post('/teams/exclusive', [TeamController::class, 'storeExclusive'])
            ->middleware('role:lecturer,admin')
            ->name('teams.exclusive');

        // 6. Menghapus email dari daftar whitelist jika role belum dikunci (Dosen, Master Admin)
        Route::delete('/users/{id}', [UserController::class, 'destroy'])
            ->middleware('role:lecturer,admin')
            ->name('users.destroy');
    });

    // C. Modul Konfigurasi Sistem & Master Data Role/Skill (Proses 2.0 — Fase 1)
    Route::middleware('auth:sanctum')->group(function () {
        // 1. Mengambil parameter konfigurasi dari app_settings (Semua User Login)
        Route::get('/settings', [SystemSettingController::class, 'index'])->name('settings.index');

        // 2. Memperbarui konfigurasi toggle kewajiban pilihan role ke-2 dan jadwal (Dosen)
        Route::put('/settings', [SystemSettingController::class, 'update'])
            ->middleware('role:lecturer,admin')
            ->name('settings.update');

        // 3. Mengambil daftar 4 roles beserta daftar skills di setiap role (Semua User Login)
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');

        // 4. Menambah atau mengubah daftar item skillset pada role tertentu (Dosen)
        Route::middleware('role:lecturer,admin')->group(function () {
            Route::post('/skills', [SkillController::class, 'store'])->name('skills.store');
            Route::match(['put', 'patch'], '/skills/{id}', [SkillController::class, 'update'])->name('skills.update');
            Route::delete('/skills/{id}', [SkillController::class, 'destroy'])->name('skills.destroy');
        });
    });

    // D. Modul Pendaftaran & Form Profiling Mahasiswa (Proses 3.0 — Fase 2)
    Route::middleware(['auth:sanctum', 'role:student,admin'])->prefix('student')->as('student.')->group(function () {
        // 1. Cek bypass / toggle role 2 & kembalikan kondisi form beserta master roles/skills
        Route::get('/profiling-config', [StudentProfilingController::class, 'profilingConfig'])->name('profiling-config');

        // 2. Simpan pengisian form profiling dan langsung hitung skor via ScoringEngineService
        Route::post('/profile', [StudentProfilingController::class, 'storeProfile'])->name('profile.store');

        // 3. Menampilkan pengumuman posisi final, daftar anggota kelompok, pembimbing, dan proyek
        Route::get('/my-team', [StudentProfilingController::class, 'myTeam'])->name('my-team');
    });

    // E. Modul Evaluasi Skor, Override Dosen & Penguncian Role (Proses 4.0 — Fase 3)
    Route::middleware('auth:sanctum')->prefix('evaluations')->as('evaluations.')->group(function () {
        // Akses Dosen & Praktisi
        Route::middleware('role:lecturer,practitioner')->group(function () {
            // 1. Rekapitulasi seluruh mahasiswa (support search & filter)
            Route::get('/', [EvaluationController::class, 'index'])->name('index');

            // 2. Rincian profil untuk Pop-Up Modal Detail
            Route::get('/{userId}', [EvaluationController::class, 'show'])->name('show');

            // 3. Simpan atau perbarui catatan evaluasi portofolio / rekomendasi role
            Route::patch('/{userId}/note', [EvaluationController::class, 'updateNote'])->name('note.update');
        });

        // Akses Khusus Dosen
        Route::middleware('role:lecturer')->group(function () {
            // 6. Tombol Lock Final Roles: Mengunci seluruh evaluasi peran mahasiswa
            Route::post('/lock-all', [EvaluationController::class, 'lockAll'])->name('lock-all');

            // 4. Override final_role_id secara manual oleh dosen
            Route::patch('/{userId}/override', [EvaluationController::class, 'override'])->name('override');

            // 5. Reset final_role_id ke recommended_role_id saran sistem awal
            Route::post('/{userId}/reset', [EvaluationController::class, 'reset'])->name('reset');
        });
    });

    // Alias endpoints for backward compatibility with earlier tasks
    Route::post('/admin/initiate-lecturer', [UserController::class, 'initiateLecturers'])->name('admin.initiate-lecturer');
    Route::post('/initiate-lecturer', [UserController::class, 'initiateLecturers'])->name('initiate-lecturer');

    Route::get('/stakeholders', [UserController::class, 'index'])->name('stakeholders.index');
    Route::post('/stakeholders', [UserController::class, 'storeWhitelist'])->name('stakeholders.store');
    Route::get('/stakeholders/{user}', [StakeholderController::class, 'show'])->name('stakeholders.show');
    Route::delete('/stakeholders/{user}', [UserController::class, 'destroy'])->name('stakeholders.destroy');

    Route::match(['put', 'patch'], '/stakeholders/{user}/role', [StakeholderController::class, 'assignRole'])->name('stakeholders.assign-role');
    Route::post('/stakeholders/assign-role', [StakeholderController::class, 'assignRole'])->name('stakeholders.assign-role-by-email');
};

// Route group with API versioning prefix (v1)
Route::prefix('v1')->as('v1.')->group($registerRoutes);

// Root api routes without v1 prefix for direct accessibility
$registerRoutes();

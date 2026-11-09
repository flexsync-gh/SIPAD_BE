<?php

use App\Enums\UserRole;
use App\Models\AppSetting;
use App\Models\Complaint;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\StudentRoleEvaluation;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\AppSettingSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('tester');
})->name('tester');

Route::get('/auth/google/callback', function (Request $request) {
    return view('tester', [
        'initialTab' => 'auth',
        'oauthCode' => $request->query('code'),
        'oauthError' => $request->query('error'),
    ]);
})->name('auth.google.callback.web');

// Developer & testing helpers for local environment
if (app()->environment('local', 'testing')) {
    Route::prefix('dev')->as('dev.')->group(function () {
        Route::get('/users', function () {
            return response()->json([
                'status' => 'success',
                'data' => User::select('id', 'name', 'email', 'user_role', 'status')->latest('id')->limit(50)->get(),
            ]);
        })->name('users');

        Route::post('/generate-token', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email')));
            $user = User::where('email', $email)->first();

            $rawRole = (string) ($request->input('user_role') ?? $request->input('role') ?? 'admin');
            $role = UserRole::normalize($rawRole) ?? UserRole::Admin;

            if (! $user) {
                if ($request->boolean('create_if_missing', true)) {
                    $user = User::create([
                        'name' => $request->input('name') ?: 'Dev '.ucfirst($role->value),
                        'email' => $email ?: 'dev.'.$role->value.'@mail.ugm.ac.id',
                        'user_role' => $role,
                        'status' => 'active',
                    ]);

                    if ($role === UserRole::Student) {
                        StudentProfile::create([
                            'user_id' => $user->id,
                            'nim' => $request->input('nim') ?: '21/'.rand(100000, 999999).'/SV/19000',
                            'is_bypass' => $request->boolean('is_bypass', false),
                        ]);
                    }
                } else {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Pengguna dengan email "'.$email.'" tidak ditemukan.',
                    ], 404);
                }
            } else {
                // If is_bypass explicitly passed for student, sync it
                if ($user->studentProfile && $request->has('is_bypass')) {
                    $user->studentProfile->is_bypass = $request->boolean('is_bypass');
                    $user->studentProfile->save();
                }
            }

            $token = $user->createToken('dev-testing-token')->plainTextToken;

            return response()->json([
                'status' => 'success',
                'message' => 'Token Sanctum berhasil dibuat untuk '.$user->email,
                'token' => $token,
                'user' => $user->load('studentProfile'),
            ]);
        })->name('generate-token');
        Route::post('/seed-sample-data', function () {
            // 1. Ensure Roles & Skills exist
            if (Role::count() === 0) {
                (new RoleSeeder)->run();
            }
            if (AppSetting::count() === 0) {
                (new AppSettingSeeder)->run();
            }

            $pmRole = Role::where('code', 'PM')->first();
            $uiuxRole = Role::where('code', 'UIUX')->first();
            $feRole = Role::where('code', 'FE')->first();
            $beRole = Role::where('code', 'BE')->first();

            // 2. Create Lecturer, Asprak, Practitioner
            $lecturer = User::updateOrCreate(
                ['email' => 'dosen.pengampu@mail.ugm.ac.id'],
                ['name' => 'Dr. Ir. Budi Santoso, M.T.', 'user_role' => UserRole::Lecturer, 'status' => 'active']
            );

            $asprak = User::updateOrCreate(
                ['email' => 'asisten.lab@mail.ugm.ac.id'],
                ['name' => 'Dimas Asisten, S.Tr.Kom.', 'user_role' => UserRole::Assistant, 'status' => 'active']
            );

            $practitioner = User::updateOrCreate(
                ['email' => 'praktisi.industri@mail.ugm.ac.id'],
                ['name' => 'Andi Pratama (Lead Tech PT Maju)', 'user_role' => UserRole::Practitioner, 'status' => 'active']
            );

            // 3. Create 8 Students for perfect 2-team grouping (2 PM, 2 UIUX, 2 FE, 2 BE)
            $rolesList = [
                ['role' => $pmRole, 'name' => 'Ahmad PM 1', 'nim' => '21/471001/SV/19001'],
                ['role' => $pmRole, 'name' => 'Bintang PM 2', 'nim' => '21/471002/SV/19002'],
                ['role' => $uiuxRole, 'name' => 'Citra UIUX 1', 'nim' => '21/471003/SV/19003'],
                ['role' => $uiuxRole, 'name' => 'Dewi UIUX 2', 'nim' => '21/471004/SV/19004'],
                ['role' => $feRole, 'name' => 'Eko Frontend 1', 'nim' => '21/471005/SV/19005'],
                ['role' => $feRole, 'name' => 'Fajar Frontend 2', 'nim' => '21/471006/SV/19006'],
                ['role' => $beRole, 'name' => 'Gilang Backend 1', 'nim' => '21/471007/SV/19007'],
                ['role' => $beRole, 'name' => 'Hendra Backend 2', 'nim' => '21/471008/SV/19008'],
            ];

            $createdStudents = [];
            foreach ($rolesList as $idx => $item) {
                $num = $idx + 1;
                $stu = User::updateOrCreate(
                    ['email' => "mhs{$num}.pad@mail.ugm.ac.id"],
                    ['name' => $item['name'], 'user_role' => UserRole::Student, 'status' => 'active']
                );

                $profile = StudentProfile::updateOrCreate(
                    ['user_id' => $stu->id],
                    [
                        'nim' => $item['nim'],
                        'is_bypass' => false,
                        'role_1_id' => $item['role']->id,
                        'portfolio_link' => 'https://github.com/mhs'.$num,
                    ]
                );

                // Attach some skills
                $skillIds = $item['role']->skills()->pluck('id')->take(3)->toArray();
                if (! empty($skillIds)) {
                    $profile->skills()->sync($skillIds);
                }

                // Create role evaluation
                StudentRoleEvaluation::updateOrCreate(
                    ['user_id' => $stu->id],
                    [
                        'score_role_1' => 85 + ($idx % 10),
                        'score_role_2' => 70,
                        'recommended_score' => 85 + ($idx % 10),
                        'recommended_role_id' => $item['role']->id,
                        'final_role_id' => $item['role']->id,
                        'is_overridden' => false,
                        'is_locked' => true, // locked so auto-group can immediately run
                        'evaluation_note' => 'Portofolio sesuai dengan kualifikasi peran.',
                    ]
                );

                $createdStudents[] = $stu;
            }

            // 4. Create an existing team with 1 complaint for demonstration
            $demoTeam = Team::firstOrCreate(
                ['team_type' => 'internal'],
                ['is_locked' => false, 'supervisor' => $asprak->id]
            );

            // Assign first 2 students to demo team
            StudentProfile::where('user_id', $createdStudents[0]->id)->update(['team_id' => $demoTeam->id]);
            StudentProfile::where('user_id', $createdStudents[1]->id)->update(['team_id' => $demoTeam->id]);

            // Create 1 Complaint from student 0 against student 1
            Complaint::firstOrCreate(
                [
                    'reporter_id' => $createdStudents[0]->id,
                    'reported_id' => $createdStudents[1]->id,
                ],
                [
                    'team_id' => $demoTeam->id,
                    'description' => 'Anggota kelompok tidak hadir dalam 3 kali sprint planning berturut-turut tanpa keterangan.',
                    'evidence_file_path' => 'complaints/sample_evidence.pdf',
                ]
            );

            // Also create a dummy file if not exists
            if (! Storage::disk('local')->exists('complaints/sample_evidence.pdf')) {
                Storage::disk('local')->put('complaints/sample_evidence.pdf', 'Sample evidence document for PAD Complaint Box testing.');
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Data simulasi lengkap (Dosen, Asprak, Praktisi, 8 Mahasiswa terevaluasi, Tim Demo, & 1 Tiket Aduan) berhasil dibuat!',
            ]);
        })->name('seed-sample-data');
    });
}

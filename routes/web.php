<?php

use App\Enums\UserRole;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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
    });
}

<?php

use App\Enums\UserRole;
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

            if (! $user) {
                if ($request->boolean('create_if_missing')) {
                    $user = User::create([
                        'name' => $request->input('name') ?: 'Developer Tester',
                        'email' => $email ?: 'dev.test@mail.ugm.ac.id',
                        'user_role' => UserRole::Admin,
                        'status' => 'active',
                    ]);
                } else {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Pengguna dengan email "'.$email.'" tidak ditemukan.',
                    ], 404);
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

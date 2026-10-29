<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StakeholderController;
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

    // B. Manajemen Stakeholder & Admin Initiation (Proses 1.0 & 2.0)
    Route::post('/admin/initiate-lecturer', [StakeholderController::class, 'initiateLecturer'])->name('admin.initiate-lecturer');
    Route::post('/initiate-lecturer', [StakeholderController::class, 'initiateLecturer'])->name('initiate-lecturer');

    Route::get('/stakeholders', [StakeholderController::class, 'index'])->name('stakeholders.index');
    Route::post('/stakeholders', [StakeholderController::class, 'store'])->name('stakeholders.store');
    Route::get('/stakeholders/{user}', [StakeholderController::class, 'show'])->name('stakeholders.show');
    Route::delete('/stakeholders/{user}', [StakeholderController::class, 'destroy'])->name('stakeholders.destroy');

    Route::match(['put', 'patch'], '/stakeholders/{user}/role', [StakeholderController::class, 'assignRole'])->name('stakeholders.assign-role');
    Route::post('/stakeholders/assign-role', [StakeholderController::class, 'assignRole'])->name('stakeholders.assign-role-by-email');
};

// Route group with API versioning prefix (v1)
Route::prefix('v1')->as('v1.')->group($registerRoutes);

// Root api routes without v1 prefix for direct accessibility
$registerRoutes();

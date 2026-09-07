<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\PatrolPointController;
use App\Http\Controllers\Api\MonitoringController;
use App\Http\Controllers\Api\SatpamController;
use App\Http\Controllers\Api\SupervisorController;
use Illuminate\Support\Facades\Route;


// Public routes
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Admin routes
    Route::prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        Route::get('/patrol-points', [PatrolPointController::class, 'index']);
        // User management
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        // Titik patroli
        Route::get('/patrol-points', [AdminController::class, 'patrolPoints']);
        Route::post('/patrol-points', [AdminController::class, 'storePatrolPoint']);
        Route::get('/patrol-points/{id}/qr', [AdminController::class, 'patrolPointQr']);
        Route::put('/patrol-points/{id}', [PatrolPointController::class, 'update']);
        Route::get('/patrol-points/{id}', [PatrolPointController::class, 'show']);
        Route::delete('/patrol-points/{id}', [PatrolPointController::class, 'destroy']);
        //Monitoring
        Route::get('/monitoring/{scheduleId}/{satpamId}', [MonitoringController::class, 'show']);
        // Aktivitas patroli
        Route::get('/activities', [AdminController::class, 'activities']);
        // Rute patroli
        Route::get('/routes', [AdminController::class, 'routes']);
        Route::post('/routes', [AdminController::class, 'storeRoute']);
        Route::put('/routes/{id}', [AdminController::class, 'updateRoute']);
        Route::delete('/routes/{id}', [AdminController::class, 'destroyRoute']);
    });

    // Satpam routes
    Route::prefix('satpam')->group(function () {
        Route::get('/summary', [SatpamController::class, 'summary']);
        Route::get('/patrol-points', [SatpamController::class, 'patrolPoints']);
        Route::get('/skip-options', [SatpamController::class, 'skipOptions']);
        Route::post('/skip-scan', [SatpamController::class, 'skipScan']);
        Route::post('/scan', [SatpamController::class, 'scan']);
        Route::post('/reports', [SatpamController::class, 'createReport']);
        Route::get('/history', [SatpamController::class, 'history']);
        Route::post('/reports', [SatpamController::class, 'createReport']);
        Route::get('/schedule', [SatpamController::class, 'schedule']);
    });

    // Supervisor routes
     Route::prefix('supervisor')->group(function () {

        Route::get('/dashboard', [SupervisorController::class, 'dashboard']);
        Route::get('/monitoring', [SupervisorController::class, 'monitoring']);

        // Kelola jadwal
        Route::get('/schedules', [SupervisorController::class, 'scheduleIndex']);
        Route::post('/schedules', [SupervisorController::class, 'scheduleStore']);
        Route::put('/schedules/{id}', [SupervisorController::class, 'scheduleUpdate']);
        Route::delete('/schedules/{id}', [SupervisorController::class, 'scheduleDestroy']);
        Route::get('/schedules-import-template', [SupervisorController::class, 'downloadImportTemplate']);
        Route::post('/schedules-import', [SupervisorController::class, 'importSchedules']);
        Route::get('/satpam', [SupervisorController::class, 'satpamList']);
        Route::get('/patrol-points', [SupervisorController::class, 'patrolPointList']);
        Route::get('/routes', [SupervisorController::class, 'routeList']);

        // Laporan
        Route::get('/reports', [SupervisorController::class, 'reports']);
        Route::put('/reports/{id}/review', [SupervisorController::class, 'reviewReport']);
       
        // Skip
        Route::put('/skips/{id}/review', [SupervisorController::class, 'reviewSkip']);
});

});


<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TaskController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

/*
|--------------------------------------------------------------------------
| Authenticated & Tenant-Isolated Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });

    // Dashboard Statistics (Optimized SQL Aggregation)
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);

    // Task Export (Streamed CSV)
    Route::get('/tasks/export', [TaskController::class, 'export']);

    // Project Management
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{id}', [ProjectController::class, 'show']);
    Route::delete('/projects/{id}', [ProjectController::class, 'destroy']);

    // Project Tasks
    Route::get('/projects/{id}/tasks', [TaskController::class, 'index']);
    Route::post('/projects/{id}/tasks', [TaskController::class, 'store']);

    // Task Actions
    Route::patch('/tasks/{id}/status', [TaskController::class, 'updateStatus']);
    Route::post('/tasks/{id}/assign', [TaskController::class, 'assign']);
});

<?php

use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\IndicatorController;
use App\Http\Controllers\MetricController;
use App\Http\Controllers\PeriodController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecapController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:3,1')
        ->name('register.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])
        ->middleware('throttle:5,1')
        ->name('profile.password.update');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::middleware(Authorize::class.':reporting-access')->group(function (): void {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/{report}', [ReportController::class, 'show'])->name('reports.show');
        Route::get('/report-attachments/{attachment}', [ReportController::class, 'attachment'])->name('reports.attachment');
        Route::get('/report-history/{history}', [ReportController::class, 'history'])->name('reports.history');
        Route::get('/recaps', [RecapController::class, 'index'])->name('recaps.index');
        Route::get('/recaps/reference-2025-2026', [RecapController::class, 'referenceWorkbook'])
            ->name('recaps.reference-workbook');
        Route::get('/recaps/export', [RecapController::class, 'export'])->name('recaps.export');
        Route::get('/recaps/print', [RecapController::class, 'print'])->name('recaps.print');
        Route::get('/recap-exports/{export}', [RecapController::class, 'download'])->name('recaps.download');
    });
    Route::middleware(Authorize::class.':reporting-admin')->group(function (): void {
        Route::get('/units', [UnitController::class, 'index'])->name('units.index');
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/periods', [PeriodController::class, 'index'])->name('periods.index');
        Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::get('/indicators', [IndicatorController::class, 'index'])->name('indicators.index');
        Route::get('/assignments', [AssignmentController::class, 'index'])->name('assignments.index');
        Route::get('/metrics', [MetricController::class, 'index'])->name('metrics.index');
        Route::get('/imports', [ImportController::class, 'index'])->name('imports.index');
    });
});

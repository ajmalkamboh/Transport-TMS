<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BranchSwitchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\Saas\SaasController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

// Guest Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// Authenticated Routes
Route::middleware(['auth', 'user.active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Tenant-Scoped Routes
    Route::middleware('tenant')->group(function () {
        Route::get('/', [DashboardController::class, 'index']);
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Multi-Branch Switching
        Route::post('/branch/switch', [BranchSwitchController::class, 'switch'])->name('branch.switch');

        // Tenant-Safe Private Document Download Gateway
        Route::get('/documents/download', [DocumentDownloadController::class, 'download'])->name('documents.download');

        // Company Administration (Users & RBAC)
        Route::get('/admin/users', [UserManagementController::class, 'index'])->name('admin.users');
    });

    // Platform Super Admin SaaS Governance Routes
    Route::middleware('super_admin')->prefix('saas')->name('saas.')->group(function () {
        Route::get('/dashboard', [SaasController::class, 'index'])->name('dashboard');
    });
});


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

        // Onboarding Setup Wizard
        Route::get('/onboarding', fn() => view('onboarding.index'))->name('onboarding');

        // Fleet, Vehicle Owner and Driver Management
        Route::prefix('fleet')->name('fleet.')->group(function () {
            Route::get('/vehicles', fn() => view('fleet.vehicles.index'))->name('vehicles');
            Route::get('/owners', fn() => view('fleet.owners.index'))->name('owners');
            Route::get('/drivers', fn() => view('fleet.drivers.index'))->name('drivers');
            Route::get('/alerts', fn() => view('fleet.alerts.index'))->name('alerts');
            Route::get('/fuel', fn() => view('fleet.fuel.index'))->name('fuel');
            Route::get('/workshop', fn() => view('fleet.workshop.index'))->name('workshop');
        });

        // Customer & Commercial Management (Quotations, Bookings, Consignments)
        Route::prefix('commercial')->name('commercial.')->group(function () {
            Route::get('/customers', fn() => view('commercial.customers.index'))->name('customers');
            Route::get('/quotations', fn() => view('commercial.quotations.index'))->name('quotations');
            Route::get('/bookings', fn() => view('commercial.bookings.index'))->name('bookings');
            Route::get('/consignments', fn() => view('commercial.consignments.index'))->name('consignments');
        });

        // Operations Management (Trips, Dispatch, Route Checkpoints, Deliveries & Expenses)
        Route::prefix('operations')->name('operations.')->group(function () {
            Route::get('/trips', fn() => view('operations.trips.index'))->name('trips');
            Route::get('/deliveries', fn() => view('operations.deliveries.index'))->name('deliveries');
            Route::get('/expenses', fn() => view('operations.expenses.index'))->name('expenses');
        });

        // Finance & Owner Settlements
        Route::prefix('finance')->name('finance.')->group(function () {
            Route::get('/settlements', fn() => view('finance.settlements.index'))->name('settlements');
        });

        // Professional Document Printing Gateway
        Route::prefix('print')->name('print.')->group(function () {
            Route::get('/quotation/{id}', [\App\Http\Controllers\PrintDocumentController::class, 'quotation'])->name('quotation');
            Route::get('/booking/{id}', [\App\Http\Controllers\PrintDocumentController::class, 'booking'])->name('booking');
            Route::get('/consignment/{id}', [\App\Http\Controllers\PrintDocumentController::class, 'consignment'])->name('consignment');
            Route::get('/cargo-receipt/{id}', [\App\Http\Controllers\PrintDocumentController::class, 'cargoReceipt'])->name('cargo-receipt');
            Route::get('/booking-summary/{id}', [\App\Http\Controllers\PrintDocumentController::class, 'bookingSummary'])->name('booking-summary');
            Route::get('/loading-slip/{id}', [\App\Http\Controllers\PrintDocumentController::class, 'loadingSlip'])->name('loading-slip');
            Route::get('/trip-sheet/{id}', [\App\Http\Controllers\PrintDocumentController::class, 'tripSheet'])->name('trip-sheet');
            Route::get('/pod-receipt/{id}', [\App\Http\Controllers\PrintDocumentController::class, 'podReceipt'])->name('pod-receipt');
            Route::get('/fuel-slip/{id}', [\App\Http\Controllers\PrintDocumentController::class, 'fuelSlip'])->name('fuel-slip');
            Route::get('/expense-voucher/{id}', [\App\Http\Controllers\PrintDocumentController::class, 'expenseVoucher'])->name('expense-voucher');
            Route::get('/job-card/{id}', [\App\Http\Controllers\PrintDocumentController::class, 'jobCard'])->name('job-card');
            Route::get('/owner-settlement/{id}', [\App\Http\Controllers\PrintDocumentController::class, 'ownerSettlement'])->name('owner-settlement');
            Route::get('/owner-payment/{id}', [\App\Http\Controllers\PrintDocumentController::class, 'ownerPayment'])->name('owner-payment');
            Route::get('/owner-ledger/{ownerId}', [\App\Http\Controllers\PrintDocumentController::class, 'ownerLedger'])->name('owner-ledger');
        });

        // Organizational Configuration & Settings
        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('/company', fn() => view('settings.company'))->name('company');
            Route::get('/branches', fn() => view('settings.branches'))->name('branches');
            Route::get('/financial', fn() => view('settings.financial'))->name('financial');
            Route::get('/master-data', fn() => view('settings.master-data'))->name('master-data');
        });
    });

    // Platform Super Admin SaaS Governance Routes
    Route::middleware('super_admin')->prefix('saas')->name('saas.')->group(function () {
        Route::get('/dashboard', [SaasController::class, 'index'])->name('dashboard');
        Route::get('/tenants', [SaasController::class, 'tenants'])->name('tenants');
        Route::get('/plans', [SaasController::class, 'plans'])->name('plans');
        Route::get('/billing', [SaasController::class, 'billing'])->name('billing');
    });
});


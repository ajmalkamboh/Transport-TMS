<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BranchSwitchController;
use App\Http\Controllers\CustomerPortalController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\MobileDriverController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\Saas\SaasController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\VehicleOwnerPortalController;
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

        // Finance, General Ledger, Billing & Settlements
        Route::prefix('finance')->name('finance.')->group(function () {
            Route::get('/chart-of-accounts', fn() => view('finance.chart-of-accounts.index'))->name('chart-of-accounts');
            Route::get('/vouchers', fn() => view('finance.vouchers.index'))->name('vouchers');
            Route::get('/invoices', fn() => view('finance.invoices.index'))->name('invoices');
            Route::get('/banking', fn() => view('finance.banking.index'))->name('banking');
            Route::get('/reports', fn() => view('finance.reports.index'))->name('reports');
            Route::get('/driver-settlements', fn() => view('finance.driver-settlements.index'))->name('driver-settlements');
            Route::get('/settlements', fn() => view('finance.settlements.index'))->name('settlements');
        });

        // Inventory, Warehouses & Procurement Management
        Route::prefix('inventory')->name('inventory.')->group(function () {
            Route::get('/items', fn() => view('inventory.items.index'))->name('items');
            Route::get('/stocks', fn() => view('inventory.stocks.index'))->name('stocks');
            Route::get('/procurement', fn() => view('inventory.procurement.index'))->name('procurement');
            Route::get('/ledger', fn() => view('inventory.ledger.index'))->name('ledger');
        });

        // Comprehensive TMS Reports & Business Intelligence
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/', fn() => view('reports.index'))->name('index');
            Route::get('/export', [\App\Http\Controllers\ReportExportController::class, 'exportCsv'])->name('export');
            Route::get('/print', [\App\Http\Controllers\ReportExportController::class, 'printReport'])->name('print');
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
            Route::get('/customer-invoice/{id}', [\App\Http\Controllers\PrintDocumentController::class, 'customerInvoice'])->name('customer-invoice');
            Route::get('/customer-receipt/{id}', [\App\Http\Controllers\PrintDocumentController::class, 'customerReceipt'])->name('customer-receipt');
            Route::get('/journal-voucher/{id}', [\App\Http\Controllers\PrintDocumentController::class, 'journalVoucher'])->name('journal-voucher');
            Route::get('/driver-settlement/{id}', [\App\Http\Controllers\PrintDocumentController::class, 'driverSettlement'])->name('driver-settlement');
            Route::get('/trip-profitability/{tripId}', [\App\Http\Controllers\PrintDocumentController::class, 'tripProfitability'])->name('trip-profitability');
            Route::get('/purchase-order/{id}', [\App\Http\Controllers\PrintDocumentController::class, 'purchaseOrder'])->name('purchase-order');
            Route::get('/goods-receipt/{id}', [\App\Http\Controllers\PrintDocumentController::class, 'goodsReceipt'])->name('goods-receipt');
            Route::get('/stock-transfer/{id}', [\App\Http\Controllers\PrintDocumentController::class, 'stockTransfer'])->name('stock-transfer');
        });

        // Organizational Configuration & Settings
        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('/company', fn() => view('settings.company'))->name('company');
            Route::get('/branches', fn() => view('settings.branches'))->name('branches');
            Route::get('/financial', fn() => view('settings.financial'))->name('financial');
            Route::get('/master-data', fn() => view('settings.master-data'))->name('master-data');
        });

        // Mobile Driver Portal Routes (PWA / Touch Optimized)
        Route::prefix('mobile/driver')->name('mobile.driver.')->group(function () {
            Route::get('/dashboard', [MobileDriverController::class, 'dashboard'])->name('dashboard');
            Route::get('/trips/{trip}', [MobileDriverController::class, 'tripDetail'])->name('trip.detail');
            Route::post('/trips/{trip}/start', [MobileDriverController::class, 'startTrip'])->name('trip.start');
            Route::post('/trips/{trip}/checkpoint', [MobileDriverController::class, 'addCheckpoint'])->name('trip.checkpoint');
            Route::post('/trips/{trip}/expense', [MobileDriverController::class, 'submitExpense'])->name('trip.expense');
            Route::post('/trips/{trip}/deliver', [MobileDriverController::class, 'confirmDelivery'])->name('trip.deliver');
            Route::post('/trips/{trip}/complete', [MobileDriverController::class, 'completeTrip'])->name('trip.complete');
            Route::post('/location', [MobileDriverController::class, 'recordLocation'])->name('location');
        });

        // Customer Portal Routes
        Route::prefix('portal/customer')->name('portal.customer.')->group(function () {
            Route::get('/dashboard', [CustomerPortalController::class, 'dashboard'])->name('dashboard');
            Route::get('/consignments', [CustomerPortalController::class, 'consignments'])->name('consignments');
            Route::get('/consignments/{consignment}', [CustomerPortalController::class, 'consignmentDetail'])->name('consignment.detail');
            Route::get('/invoices', [CustomerPortalController::class, 'invoices'])->name('invoices');
            Route::get('/pod/{pod}/download', [CustomerPortalController::class, 'downloadPod'])->name('pod.download');
        });

        // Vehicle Owner Portal Routes
        Route::prefix('portal/owner')->name('portal.owner.')->group(function () {
            Route::get('/dashboard', [VehicleOwnerPortalController::class, 'dashboard'])->name('dashboard');
            Route::get('/vehicles', [VehicleOwnerPortalController::class, 'vehicles'])->name('vehicles');
            Route::get('/trips', [VehicleOwnerPortalController::class, 'trips'])->name('trips');
            Route::get('/settlements', [VehicleOwnerPortalController::class, 'settlements'])->name('settlements');
            Route::get('/settlements/{settlement}', [VehicleOwnerPortalController::class, 'settlementDetail'])->name('settlement.detail');
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

// Production Health Check Monitoring Probe
Route::get('/health', function () {
    $dbOk = false;
    $cacheOk = false;
    $storageOk = false;

    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        $dbOk = true;
    } catch (\Throwable $e) {}

    try {
        \Illuminate\Support\Facades\Cache::put('health_check', true, 10);
        $cacheOk = \Illuminate\Support\Facades\Cache::get('health_check') === true;
    } catch (\Throwable $e) {}

    try {
        $testFile = storage_path('app/health.txt');
        \Illuminate\Support\Facades\File::put($testFile, 'ok');
        $storageOk = \Illuminate\Support\Facades\File::get($testFile) === 'ok';
        \Illuminate\Support\Facades\File::delete($testFile);
    } catch (\Throwable $e) {}

    $status = ($dbOk && $cacheOk && $storageOk) ? 'healthy' : 'degraded';
    $code = $status === 'healthy' ? 200 : 503;

    return response()->json([
        'status' => $status,
        'timestamp' => now()->toIso8601String(),
        'services' => [
            'database' => $dbOk ? 'up' : 'down',
            'cache' => $cacheOk ? 'up' : 'down',
            'storage' => $storageOk ? 'writable' : 'readonly',
        ],
        'version' => '1.0.0',
        'php' => PHP_VERSION,
    ], $code);
})->name('health');


@extends('layouts.app')

@section('title', 'Executive Logistics Dashboard')

@section('content')
<!-- Welcome Banner -->
<div class="row g-3 mb-3">
    <div class="col-md-12">
        <div class="card bg-100 shadow-none border">
            <div class="card-body py-3">
                <div class="row flex-between-center">
                    <div class="col-sm-auto mb-2 mb-sm-0">
                        <h4 class="mb-1 text-primary fw-bold">
                            Welcome back, {{ $user->name }}!
                        </h4>
                        <div class="fs--1 text-600">
                            Enterprise Tenant: <strong class="text-dark">{{ $company->name ?? 'Platform Administrator' }}</strong>
                            @if($activeBranch)
                                &bull; Active Operational Hub: <span class="badge bg-soft-info text-info">{{ $activeBranch->name }} ({{ $activeBranch->city }})</span>
                            @else
                                &bull; Scope: <span class="badge bg-soft-primary text-primary">All Accessible Hubs (Consolidated)</span>
                            @endif
                        </div>
                    </div>
                    <div class="col-sm-auto">
                        <a href="{{ route('reports.index') }}" class="btn btn-primary btn-sm">
                            <i class="fas fa-chart-line me-1"></i> Reports & Analytics Center
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if($company && !$company->isOnboarded())
<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="card bg-soft-warning border-warning shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center">
                    <div class="d-flex align-items-center mb-2 mb-md-0">
                        <div class="avatar avatar-l bg-warning rounded-circle me-3">
                            <span class="fas fa-magic text-white"></span>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold text-dark">Initial Setup Incomplete ({{ $company->getOnboardingProgressPercentage() }}% Done)</h6>
                            <div class="fs--1 text-700">Complete guided configuration of company details, branches, financial periods and master data.</div>
                        </div>
                    </div>
                    <div>
                        <a href="{{ route('onboarding') }}" class="btn btn-warning btn-sm text-dark fw-bold shadow-sm">
                            <i class="fas fa-arrow-right me-1"></i> Continue Setup Wizard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

<!-- SECTION 1: EXECUTIVE FINANCIAL SUMMARY WIDGETS -->
<div class="row g-3 mb-3">
    <!-- Freight Revenue -->
    <div class="col-sm-6 col-md-3">
        <div class="card overflow-hidden h-100 border-start border-4 border-primary">
            <div class="card-body py-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-700 mb-1 fs--1 text-uppercase">Monthly Freight Revenue</h6>
                        <h3 class="fw-bolder text-primary mb-0">PKR {{ number_format($kpis['monthly_freight_revenue'] ?? 0, 0) }}</h3>
                    </div>
                    <div class="avatar avatar-l bg-soft-primary rounded-circle">
                        <span class="fas fa-file-invoice-dollar fs-1 text-primary"></span>
                    </div>
                </div>
                <div class="mt-2 fs--2 text-muted">Billed freight voyages (MTD)</div>
            </div>
        </div>
    </div>

    <!-- Trip Expenses -->
    <div class="col-sm-6 col-md-3">
        <div class="card overflow-hidden h-100 border-start border-4 border-warning">
            <div class="card-body py-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-700 mb-1 fs--1 text-uppercase">Trip Expenses & Fuel</h6>
                        <h3 class="fw-bolder text-warning mb-0">PKR {{ number_format($kpis['trip_expenses'] ?? 0, 0) }}</h3>
                    </div>
                    <div class="avatar avatar-l bg-soft-warning rounded-circle">
                        <span class="fas fa-gas-pump fs-1 text-warning"></span>
                    </div>
                </div>
                <div class="mt-2 fs--2 text-muted">Diesel refuels & en-route tolls</div>
            </div>
        </div>
    </div>

    <!-- Outstanding Receivables -->
    <div class="col-sm-6 col-md-3">
        <div class="card overflow-hidden h-100 border-start border-4 border-danger">
            <div class="card-body py-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-700 mb-1 fs--1 text-uppercase">Customer Receivables</h6>
                        <h3 class="fw-bolder text-danger mb-0">PKR {{ number_format($kpis['outstanding_receivables'] ?? 0, 0) }}</h3>
                    </div>
                    <div class="avatar avatar-l bg-soft-danger rounded-circle">
                        <span class="fas fa-hand-holding-usd fs-1 text-danger"></span>
                    </div>
                </div>
                <div class="mt-2 fs--2 text-muted">Unpaid freight invoices</div>
            </div>
        </div>
    </div>

    <!-- Net Profit (Finalized & Estimated) -->
    <div class="col-sm-6 col-md-3">
        <div class="card overflow-hidden h-100 border-start border-4 border-success">
            <div class="card-body py-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-700 mb-1 fs--1 text-uppercase">Finalized Net Profit</h6>
                        <h3 class="fw-bolder text-success mb-0">PKR {{ number_format($kpis['finalized_profit'] ?? 0, 0) }}</h3>
                    </div>
                    <div class="avatar avatar-l bg-soft-success rounded-circle">
                        <span class="fas fa-balance-scale fs-1 text-success"></span>
                    </div>
                </div>
                <div class="mt-2 fs--2 text-muted">Estimated Op: PKR {{ number_format($kpis['estimated_profit'] ?? 0, 0) }}</div>
            </div>
        </div>
    </div>
</div>

<!-- SECTION 2: FLEET STATUS & VOYAGE DISPATCH COUNTERS -->
<div class="row g-3 mb-3">
    <!-- Total Fleet -->
    <div class="col-6 col-md-2">
        <div class="card text-center py-2 h-100">
            <div class="card-body py-2">
                <span class="fas fa-truck text-secondary fs-2 mb-1"></span>
                <h4 class="mb-0 fw-bold">{{ $kpis['total_fleet'] ?? 0 }}</h4>
                <div class="fs--2 text-500 font-sans-serif text-uppercase">Total Fleet</div>
            </div>
        </div>
    </div>

    <!-- Vehicles on Trips -->
    <div class="col-6 col-md-2">
        <div class="card text-center py-2 h-100 border-primary">
            <div class="card-body py-2">
                <span class="fas fa-route text-primary fs-2 mb-1"></span>
                <h4 class="mb-0 fw-bold text-primary">{{ $kpis['vehicles_on_trips'] ?? 0 }}</h4>
                <div class="fs--2 text-500 font-sans-serif text-uppercase">On Voyage / Trip</div>
            </div>
        </div>
    </div>

    <!-- Available Vehicles -->
    <div class="col-6 col-md-2">
        <div class="card text-center py-2 h-100 border-success">
            <div class="card-body py-2">
                <span class="fas fa-check-circle text-success fs-2 mb-1"></span>
                <h4 class="mb-0 fw-bold text-success">{{ $kpis['available_vehicles'] ?? 0 }}</h4>
                <div class="fs--2 text-500 font-sans-serif text-uppercase">Available for Dispatch</div>
            </div>
        </div>
    </div>

    <!-- Vehicles under Maintenance -->
    <div class="col-6 col-md-2">
        <div class="card text-center py-2 h-100 border-warning">
            <div class="card-body py-2">
                <span class="fas fa-tools text-warning fs-2 mb-1"></span>
                <h4 class="mb-0 fw-bold text-warning">{{ $kpis['vehicles_under_maintenance'] ?? 0 }}</h4>
                <div class="fs--2 text-500 font-sans-serif text-uppercase">In Maintenance</div>
            </div>
        </div>
    </div>

    <!-- Trips in Progress -->
    <div class="col-6 col-md-2">
        <div class="card text-center py-2 h-100 border-info">
            <div class="card-body py-2">
                <span class="fas fa-shipping-fast text-info fs-2 mb-1"></span>
                <h4 class="mb-0 fw-bold text-info">{{ $kpis['trips_in_progress'] ?? 0 }}</h4>
                <div class="fs--2 text-500 font-sans-serif text-uppercase">Trips In Transit</div>
            </div>
        </div>
    </div>

    <!-- Pending Deliveries & POD -->
    <div class="col-6 col-md-2">
        <div class="card text-center py-2 h-100 border-danger">
            <div class="card-body py-2">
                <span class="fas fa-clipboard-list text-danger fs-2 mb-1"></span>
                <h4 class="mb-0 fw-bold text-danger">{{ $kpis['pending_deliveries'] ?? 0 }}</h4>
                <div class="fs--2 text-500 font-sans-serif text-uppercase">Pending POD / Deliv</div>
            </div>
        </div>
    </div>
</div>

<!-- SECTION 3: OPERATIONAL MONITORING & SHORTCUTS -->
<div class="row g-3 mb-3">
    <!-- Active Drivers & Owner Payables Overview -->
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="fas fa-tachometer-alt me-1 text-primary"></i> Fleet & Driver Operational Health</h6>
                <span class="badge bg-soft-info text-info fs--2">Live Fleet Sync</span>
            </div>
            <div class="card-body">
                <div class="row g-3 text-center">
                    <div class="col-4 border-end">
                        <div class="fs--1 text-muted text-uppercase">Active Commercial Drivers</div>
                        <div class="fs-2 fw-bold text-dark mt-1">{{ $kpis['active_drivers'] ?? 0 }}</div>
                        <div class="fs--2 text-success mt-1"><i class="fas fa-id-badge me-1"></i> Authorized & Assigned</div>
                    </div>
                    <div class="col-4 border-end">
                        <div class="fs--1 text-muted text-uppercase">Completed Trips (MTD)</div>
                        <div class="fs-2 fw-bold text-primary mt-1">{{ $kpis['completed_trips'] ?? 0 }}</div>
                        <div class="fs--2 text-primary mt-1"><i class="fas fa-flag-checkered me-1"></i> Successfully Closed</div>
                    </div>
                    <div class="col-4">
                        <div class="fs--1 text-muted text-uppercase">Pending Owner Payables</div>
                        <div class="fs-2 fw-bold text-danger mt-1">PKR {{ number_format($kpis['outstanding_owner_payables'] ?? 0, 0) }}</div>
                        <div class="fs--2 text-muted mt-1"><i class="fas fa-user-tie me-1"></i> Attached Truck Settlements</div>
                    </div>
                </div>

                <hr class="my-3">

                <!-- Quick Module Jump Tiles -->
                <div class="row g-2">
                    <div class="col-md-3">
                        <a href="{{ route('operations.trips') }}" class="btn btn-outline-primary btn-sm w-100 py-2">
                            <i class="fas fa-route me-1"></i> Trips & Dispatch
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="{{ route('operations.deliveries') }}" class="btn btn-outline-info btn-sm w-100 py-2">
                            <i class="fas fa-clipboard-check me-1"></i> POD Deliveries
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="{{ route('inventory.stocks') }}" class="btn btn-outline-success btn-sm w-100 py-2">
                            <i class="fas fa-warehouse me-1"></i> Warehouse Stocks
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="{{ route('finance.invoices') }}" class="btn btn-outline-danger btn-sm w-100 py-2">
                            <i class="fas fa-file-invoice-dollar me-1"></i> Billing & Invoices
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Links & System State -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header bg-light">
                <h6 class="mb-0 fw-bold"><i class="fas fa-chart-pie me-1 text-success"></i> BI Reports Quick Access</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="{{ route('reports.index', ['tab' => 'fleet']) }}" class="btn btn-light btn-sm text-start py-2 border">
                        <i class="fas fa-truck-moving me-2 text-primary"></i> Fleet Utilization & Fuel Efficiency
                    </a>
                    <a href="{{ route('reports.index', ['tab' => 'customer']) }}" class="btn btn-light btn-sm text-start py-2 border">
                        <i class="fas fa-users me-2 text-success"></i> Customer Receivables & Aging (0-90+)
                    </a>
                    <a href="{{ route('reports.index', ['tab' => 'trip']) }}" class="btn btn-light btn-sm text-start py-2 border">
                        <i class="fas fa-map-marked-alt me-2 text-info"></i> Route Performance & Profitability
                    </a>
                    <a href="{{ route('reports.index', ['tab' => 'financial']) }}" class="btn btn-light btn-sm text-start py-2 border">
                        <i class="fas fa-coins me-2 text-warning"></i> General Ledger & Cash Flow
                    </a>
                    <a href="{{ route('reports.index', ['tab' => 'inventory']) }}" class="btn btn-light btn-sm text-start py-2 border">
                        <i class="fas fa-boxes me-2 text-secondary"></i> Stock Valuation & Spares
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

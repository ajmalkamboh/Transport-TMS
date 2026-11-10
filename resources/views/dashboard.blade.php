@extends('layouts.app')

@section('title', 'Logistics Dashboard')

@section('content')
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
                            Logged into: <strong class="text-dark">{{ $company->name ?? 'Platform Admin' }}</strong>
                            @if($activeBranch)
                                &bull; Active Operational Hub: <span class="badge bg-soft-info text-info">{{ $activeBranch->name }} ({{ $activeBranch->city }})</span>
                            @endif
                        </div>
                    </div>
                    <div class="col-sm-auto">
                        <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> Multi-Tenancy Active</span>
                        <span class="badge bg-primary ms-1"><i class="fas fa-shield-alt me-1"></i> Isolated Scope</span>
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
                            <div class="fs--1 text-700">Complete the 6-step guided wizard to configure your legal identity, branches, fiscal year, and transport masters.</div>
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

<div class="row g-3 mb-3">
    <!-- Active Branches Card -->
    <div class="col-sm-6 col-md-4">
        <div class="card overflow-hidden h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="avatar avatar-xl bg-soft-primary rounded-circle me-3">
                        <div class="avatar-name text-primary"><span class="fas fa-code-branch fs-1"></span></div>
                    </div>
                    <div>
                        <h6 class="text-700 mb-0">Active Hubs / Branches</h6>
                        <h3 class="fw-bolder text-primary mb-0 mt-1">{{ $branchCount }}</h3>
                    </div>
                </div>
                <div class="mt-3 fs--1 text-500">
                    Scoped strictly to {{ $company->name ?? 'Company' }}
                </div>
            </div>
        </div>
    </div>

    <!-- Company Users Card -->
    <div class="col-sm-6 col-md-4">
        <div class="card overflow-hidden h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="avatar avatar-xl bg-soft-success rounded-circle me-3">
                        <div class="avatar-name text-success"><span class="fas fa-users fs-1"></span></div>
                    </div>
                    <div>
                        <h6 class="text-700 mb-0">Authorized Users</h6>
                        <h3 class="fw-bolder text-success mb-0 mt-1">{{ $userCount }}</h3>
                    </div>
                </div>
                <div class="mt-3 fs--1 text-500">
                    Roles, permissions & branch access synchronized
                </div>
            </div>
        </div>
    </div>

    <!-- Tenant Security Status Card -->
    <div class="col-sm-6 col-md-4">
        <div class="card overflow-hidden h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="avatar avatar-xl bg-soft-warning rounded-circle me-3">
                        <div class="avatar-name text-warning"><span class="fas fa-lock fs-1"></span></div>
                    </div>
                    <div>
                        <h6 class="text-700 mb-0">Tenant Security Mode</h6>
                        <h3 class="fw-bolder text-warning mb-0 mt-1">Enforced</h3>
                    </div>
                </div>
                <div class="mt-3 fs--1 text-500">
                    Fail-closed global scopes + service validation
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header bg-light">
                <h6 class="mb-0 fw-bold"><i class="fas fa-info-circle me-1 text-primary"></i> Multi-Tenant SaaS Architecture Overview</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-striped fs--1 mb-0">
                        <thead>
                            <tr>
                                <th>Component</th>
                                <th>Implementation Pattern</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Tenant Model & Identification</strong></td>
                                <td><code>App\Models\Company</code> via <code>IdentifyTenant</code> Middleware</td>
                                <td><span class="badge bg-success">Active</span></td>
                            </tr>
                            <tr>
                                <td><strong>Global Query Scoping</strong></td>
                                <td><code>BelongsToCompany</code> Trait (Fail-closed)</td>
                                <td><span class="badge bg-success">Active</span></td>
                            </tr>
                            <tr>
                                <td><strong>Branch Isolation</strong></td>
                                <td><code>BelongsToBranch</code> Trait + <code>BranchAccessService</code></td>
                                <td><span class="badge bg-success">Active</span></td>
                            </tr>
                            <tr>
                                <td><strong>Service Layer Validation</strong></td>
                                <td><code>TenantAuthorizationService::validateTenantOwnership()</code></td>
                                <td><span class="badge bg-success">Active</span></td>
                            </tr>
                            <tr>
                                <td><strong>Document Sequences</strong></td>
                                <td><code>DocumentSequenceService</code> with Pessimistic Row Locking</td>
                                <td><span class="badge bg-success">Active</span></td>
                            </tr>
                            <tr>
                                <td><strong>Private File Isolation</strong></td>
                                <td><code>FileStorageService</code> in <code>storage/app/private/companies/{id}/</code></td>
                                <td><span class="badge bg-success">Active</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header bg-light">
                <h6 class="mb-0 fw-bold"><i class="fas fa-bolt me-1 text-warning"></i> Quick Management</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="{{ route('admin.users') }}" class="btn btn-outline-primary btn-sm text-start py-2">
                        <i class="fas fa-user-plus me-2"></i> Manage Users & Permissions
                    </a>
                    @if(auth()->user()->isSuperAdmin())
                    <a href="{{ route('saas.dashboard') }}" class="btn btn-outline-warning btn-sm text-start py-2">
                        <i class="fas fa-building me-2"></i> SaaS Company Directory
                    </a>
                    @endif
                </div>
                <hr>
                <div class="fs--2 text-500">
                    Current Tenant Session: <code>{{ $company->slug ?? 'super_admin' }}</code><br>
                    Current DB Connection: <code>{{ config('database.default') }}</code>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection


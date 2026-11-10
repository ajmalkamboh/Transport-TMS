@extends('layouts.app')

@section('title', 'Platform SaaS Administration')

@section('content')
<div class="row g-3 mb-3">
    <div class="col-md-12">
        <div class="card bg-soft-warning border-warning">
            <div class="card-body py-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h4 class="text-warning fw-bold mb-0"><i class="fas fa-crown me-2"></i> Platform SaaS Governance Console</h4>
                        <div class="fs--1 text-700">Platform-level administration of independent transport companies, subscriptions, and global quotas.</div>
                    </div>
                    <span class="badge bg-warning text-dark px-3 py-2 fs--1">Super Admin Tier</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="text-700">Total Transport Companies</h6>
                <h3 class="fw-bolder text-primary mb-0">{{ $totalCompanies }}</h3>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="text-700">Active Tenants</h6>
                <h3 class="fw-bolder text-success mb-0">{{ $activeCompanies }}</h3>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="text-700">Total Depot Hubs</h6>
                <h3 class="fw-bolder text-info mb-0">{{ $totalBranches }}</h3>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="text-700">Registered Users</h6>
                <h3 class="fw-bolder text-dark mb-0">{{ $totalUsers }}</h3>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold">Registered Transport Companies</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped fs--1 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th>Company Name</th>
                        <th>Code</th>
                        <th>Status</th>
                        <th>Plan</th>
                        <th>Branches</th>
                        <th>Users</th>
                        <th>Subscription Expiry</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($companies as $comp)
                    <tr>
                        <td class="fw-bold text-primary">{{ $comp->name }}</td>
                        <td><span class="badge bg-200 text-dark">{{ $comp->code }}</span></td>
                        <td>
                            @if($comp->status === 'active')
                                <span class="badge bg-soft-success text-success">Active</span>
                            @else
                                <span class="badge bg-soft-danger text-danger">{{ ucfirst($comp->status) }}</span>
                            @endif
                        </td>
                        <td><span class="badge bg-soft-info text-info text-uppercase">{{ $comp->subscription_plan }}</span></td>
                        <td>{{ $comp->branches_count }}</td>
                        <td>{{ $comp->users_count }}</td>
                        <td>{{ $comp->subscription_expires_at ? $comp->subscription_expires_at->format('d M Y') : 'Lifetime' }}</td>
                        <td>{{ $comp->created_at->format('d M Y') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-3 text-500">No transport companies registered yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection


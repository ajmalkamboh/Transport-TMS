@extends('layouts.app')

@section('title', 'Super Admin & SaaS Governance')

@section('content')
<!-- Header Banner -->
<div class="row g-3 mb-3">
    <div class="col-md-12">
        <div class="card bg-soft-warning border-warning shadow-sm">
            <div class="card-body py-3">
                <div class="row flex-between-center">
                    <div class="col-sm-auto mb-2 mb-sm-0">
                        <div class="d-flex align-items-center">
                            <div class="avatar avatar-xl bg-warning rounded-circle me-3">
                                <div class="avatar-name text-white"><span class="fas fa-crown fs-2"></span></div>
                            </div>
                            <div>
                                <h4 class="text-warning fw-bolder mb-0">Platform SaaS Governance Console</h4>
                                <div class="fs--1 text-700">Governance of multi-tenant transport companies, subscription tiers, MRR analytics, and platform billing.</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-auto">
                        <a href="{{ route('saas.tenants') }}" class="btn btn-warning btn-sm shadow-sm me-1 text-dark fw-bold">
                            <i class="fas fa-plus-circle me-1"></i> Provision Tenant
                        </a>
                        <a href="{{ route('saas.plans') }}" class="btn btn-outline-dark btn-sm shadow-sm me-1">
                            <i class="fas fa-layer-group me-1"></i> Subscription Plans
                        </a>
                        <a href="{{ route('saas.billing') }}" class="btn btn-outline-dark btn-sm shadow-sm">
                            <i class="fas fa-file-invoice-dollar me-1"></i> SaaS Billing
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 10 Platform KPI Cards -->
<div class="row g-3 mb-3">
    <!-- Total Transport Companies -->
    <div class="col-sm-6 col-md-4 col-xl-2">
        <div class="card h-100 shadow-sm border">
            <div class="card-body p-3">
                <div class="fs--2 text-600 text-uppercase fw-semi-bold">Total Tenants</div>
                <h3 class="fw-bolder text-primary mb-0 mt-1">{{ $totalCompanies }}</h3>
                <div class="fs--2 text-500 mt-1">Registered Logistics Companies</div>
            </div>
        </div>
    </div>

    <!-- Active Tenants -->
    <div class="col-sm-6 col-md-4 col-xl-2">
        <div class="card h-100 shadow-sm border">
            <div class="card-body p-3">
                <div class="fs--2 text-600 text-uppercase fw-semi-bold text-success">Active Tenants</div>
                <h3 class="fw-bolder text-success mb-0 mt-1">{{ $activeTenants }}</h3>
                <div class="fs--2 text-500 mt-1">Operational & Compliant</div>
            </div>
        </div>
    </div>

    <!-- Suspended Tenants -->
    <div class="col-sm-6 col-md-4 col-xl-2">
        <div class="card h-100 shadow-sm border">
            <div class="card-body p-3">
                <div class="fs--2 text-600 text-uppercase fw-semi-bold text-danger">Suspended</div>
                <h3 class="fw-bolder text-danger mb-0 mt-1">{{ $suspendedTenants }}</h3>
                <div class="fs--2 text-500 mt-1">Locked / Past Due</div>
            </div>
        </div>
    </div>

    <!-- Trial Tenants -->
    <div class="col-sm-6 col-md-4 col-xl-2">
        <div class="card h-100 shadow-sm border">
            <div class="card-body p-3">
                <div class="fs--2 text-600 text-uppercase fw-semi-bold text-info">Free Trials</div>
                <h3 class="fw-bolder text-info mb-0 mt-1">{{ $trialTenants }}</h3>
                <div class="fs--2 text-500 mt-1">Evaluating Platform</div>
            </div>
        </div>
    </div>

    <!-- Active Subscriptions -->
    <div class="col-sm-6 col-md-4 col-xl-2">
        <div class="card h-100 shadow-sm border">
            <div class="card-body p-3">
                <div class="fs--2 text-600 text-uppercase fw-semi-bold text-dark">Active Subs</div>
                <h3 class="fw-bolder text-dark mb-0 mt-1">{{ $activeSubscriptions }}</h3>
                <div class="fs--2 text-500 mt-1">Current Active Plans</div>
            </div>
        </div>
    </div>

    <!-- Expired Subscriptions -->
    <div class="col-sm-6 col-md-4 col-xl-2">
        <div class="card h-100 shadow-sm border">
            <div class="card-body p-3">
                <div class="fs--2 text-600 text-uppercase fw-semi-bold text-warning">Expired Subs</div>
                <h3 class="fw-bolder text-warning mb-0 mt-1">{{ $expiredSubscriptions }}</h3>
                <div class="fs--2 text-500 mt-1">Requires Renewal</div>
            </div>
        </div>
    </div>
</div>

<!-- Revenue & Billing Row -->
<div class="row g-3 mb-3">
    <!-- Monthly Recurring Revenue (MRR) -->
    <div class="col-md-3">
        <div class="card h-100 bg-soft-primary border-primary shadow-sm">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fs--1 text-primary fw-semi-bold text-uppercase">Monthly Recurring Revenue (MRR)</div>
                        <h2 class="fw-bolder text-primary mb-0 mt-1">PKR {{ number_format($mrr, 0) }}</h2>
                        <div class="fs--2 text-700 mt-1">Normalized monthly subscription rate</div>
                    </div>
                    <span class="fas fa-chart-line text-primary fs-3"></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Collected Revenue -->
    <div class="col-md-3">
        <div class="card h-100 bg-soft-success border-success shadow-sm">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fs--1 text-success fw-semi-bold text-uppercase">Total Collected Revenue</div>
                        <h2 class="fw-bolder text-success mb-0 mt-1">PKR {{ number_format($totalRevenue, 0) }}</h2>
                        <div class="fs--2 text-700 mt-1">Verified subscription receipts</div>
                    </div>
                    <span class="fas fa-hand-holding-usd text-success fs-3"></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending / Outstanding Invoices -->
    <div class="col-md-3">
        <div class="card h-100 bg-soft-danger border-danger shadow-sm">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fs--1 text-danger fw-semi-bold text-uppercase">Pending Invoices Amount</div>
                        <h2 class="fw-bolder text-danger mb-0 mt-1">PKR {{ number_format($pendingInvoicesAmount, 0) }}</h2>
                        <div class="fs--2 text-700 mt-1">Unpaid platform billing</div>
                    </div>
                    <span class="fas fa-exclamation-circle text-danger fs-3"></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Payments Awaiting Manual Verification -->
    <div class="col-md-3">
        <div class="card h-100 bg-soft-warning border-warning shadow-sm">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fs--1 text-warning fw-semi-bold text-uppercase">Pending Payment Verifications</div>
                        <h2 class="fw-bolder text-warning mb-0 mt-1">{{ $pendingPayments }}</h2>
                        <div class="fs--2 text-700 mt-1">Bank transfer receipts to review</div>
                    </div>
                    <span class="fas fa-receipt text-warning fs-3"></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Feed: Recent Tenants and Recent Platform Invoices -->
<div class="row g-3">
    <!-- Registered Companies Feed -->
    <div class="col-lg-7">
        <div class="card h-100 shadow-sm">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="fas fa-building me-1 text-primary"></i> Transport Companies Feed</h6>
                <a href="{{ route('saas.tenants') }}" class="fs--1 text-primary">View All Tenants &rarr;</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped fs--1 mb-0">
                        <thead class="bg-200">
                            <tr>
                                <th>Company</th>
                                <th>Plan</th>
                                <th>Owner</th>
                                <th>Status</th>
                                <th>Expiry</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentTenants as $t)
                            <tr>
                                <td>
                                    <div class="fw-bold text-primary">{{ $t->name }}</div>
                                    <div class="fs--2 text-muted">Code: {{ $t->code }} | Slug: {{ $t->slug }}</div>
                                </td>
                                <td>
                                    <span class="badge bg-soft-info text-info">{{ $t->plan?->name ?? 'Custom' }}</span>
                                </td>
                                <td>
                                    <div class="text-dark">{{ $t->owner?->name ?? 'Unassigned' }}</div>
                                    <div class="fs--2 text-500">{{ $t->owner?->email }}</div>
                                </td>
                                <td>
                                    @if($t->status === 'active')
                                        <span class="badge bg-soft-success text-success">Active</span>
                                    @elseif($t->status === 'suspended')
                                        <span class="badge bg-soft-danger text-danger">Suspended</span>
                                    @elseif($t->status === 'trial')
                                        <span class="badge bg-soft-info text-info">Trial</span>
                                    @else
                                        <span class="badge bg-soft-secondary text-secondary">{{ ucfirst($t->status) }}</span>
                                    @endif
                                </td>
                                <td>
                                    {{ $t->subscription_expires_at ? $t->subscription_expires_at->format('d M Y') : 'N/A' }}
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center py-3 text-500">No transport companies provisioned yet.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Subscription Payments Feed -->
    <div class="col-lg-5">
        <div class="card h-100 shadow-sm">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="fas fa-receipt me-1 text-success"></i> Recent SaaS Subscription Payments</h6>
                <a href="{{ route('saas.billing') }}" class="fs--1 text-success">Billing Console &rarr;</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped fs--1 mb-0">
                        <thead class="bg-200">
                            <tr>
                                <th>Payment #</th>
                                <th>Company</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentPayments as $p)
                            <tr>
                                <td>
                                    <div class="fw-bold">{{ $p->payment_number }}</div>
                                    <div class="fs--2 text-500">{{ $p->payment_date->format('d M Y') }} &bull; {{ strtoupper($p->payment_method) }}</div>
                                </td>
                                <td class="text-primary">{{ $p->company?->name }}</td>
                                <td class="fw-bold text-success">PKR {{ number_format($p->amount, 2) }}</td>
                                <td>
                                    @if($p->status === 'verified')
                                        <span class="badge bg-soft-success text-success">Verified</span>
                                    @elseif($p->status === 'pending_verification')
                                        <span class="badge bg-soft-warning text-warning">Pending</span>
                                    @else
                                        <span class="badge bg-soft-danger text-danger">Rejected</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center py-3 text-500">No subscription payments logged yet.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

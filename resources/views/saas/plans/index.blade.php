@extends('layouts.app')

@section('title', 'Subscription Plans | SaaS')

@section('content')
<div class="row g-3 mb-3">
    <div class="col-md-12">
        <div class="card bg-100 shadow-none border">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0 text-primary fw-bold"><i class="fas fa-layer-group me-2"></i> Subscription Plans & Tier Governance</h5>
                        <div class="fs--1 text-600">Define pricing tiers, resource quotas (vehicles, staff, hubs), and bundle feature permissions for transport companies.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<livewire:saas.plan-manager />
@endsection

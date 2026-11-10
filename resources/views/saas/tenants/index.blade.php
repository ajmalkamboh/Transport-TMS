@extends('layouts.app')

@section('title', 'Tenant Management | SaaS')

@section('content')
<div class="row g-3 mb-3">
    <div class="col-md-12">
        <div class="card bg-100 shadow-none border">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0 text-primary fw-bold"><i class="fas fa-building me-2"></i> Transport Company Tenant Administration</h5>
                        <div class="fs--1 text-600">Provision transport companies, assign business owners, control subscription lifecycles, and configure feature overrides.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<livewire:saas.tenant-manager />
@endsection


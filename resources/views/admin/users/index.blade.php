@extends('layouts.app')

@section('title', 'User & Access Architecture')

@section('content')
<div class="row g-3 mb-3">
    <div class="col-md-12">
        <div class="card bg-100 shadow-none border">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0 text-primary fw-bold"><i class="fas fa-users-cog me-2"></i> Company User & Access Governance</h5>
                        <div class="fs--1 text-600">Configure tenant staff, assign roles, define permissions, and grant multi-branch operational access.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<livewire:admin.user-management />
@endsection


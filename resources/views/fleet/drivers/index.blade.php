@extends('layouts.app')

@section('title', 'Commercial Drivers Management')

@section('content')
<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body py-3">
                <div class="d-flex flex-wrap align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-l bg-primary rounded-circle me-3">
                            <div class="avatar-name text-white"><span class="fas fa-id-card"></span></div>
                        </div>
                        <div>
                            <h5 class="fw-bolder mb-0 text-primary">Driver Management & Duty Operations</h5>
                            <div class="fs--1 text-muted">Manage HTV/LTV commercial drivers, licenses, salary & trip allowances, advances disbursement, and vehicle assignments.</div>
                        </div>
                    </div>
                    <div class="mt-2 mt-md-0">
                        <a href="{{ route('fleet.vehicles') }}" class="btn btn-outline-secondary btn-sm me-1">
                            <i class="fas fa-truck me-1"></i> Fleet Vehicles
                        </a>
                        <a href="{{ route('fleet.owners') }}" class="btn btn-outline-secondary btn-sm me-1">
                            <i class="fas fa-user-tie me-1"></i> Vehicle Owners
                        </a>
                        <a href="{{ route('fleet.alerts') }}" class="btn btn-outline-danger btn-sm">
                            <i class="fas fa-bell me-1"></i> Document Expiries
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12">
        @livewire('fleet.driver-manager')
    </div>
</div>
@endsection


@extends('layouts.app')

@section('title', 'Fleet Document Compliance & Expiry Alerts')

@section('content')
<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body py-3">
                <div class="d-flex flex-wrap align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-l bg-danger rounded-circle me-3">
                            <div class="avatar-name text-white"><span class="fas fa-exclamation-triangle"></span></div>
                        </div>
                        <div>
                            <h5 class="fw-bolder mb-0 text-danger">Fleet Compliance & Expiry Alerts</h5>
                            <div class="fs--1 text-muted">Proactive regulatory tracking for vehicle fitness, commercial insurance, route permits, registration, and driver licenses.</div>
                        </div>
                    </div>
                    <div class="mt-2 mt-md-0">
                        <a href="{{ route('fleet.vehicles') }}" class="btn btn-outline-secondary btn-sm me-1">
                            <i class="fas fa-truck me-1"></i> Fleet Vehicles
                        </a>
                        <a href="{{ route('fleet.owners') }}" class="btn btn-outline-secondary btn-sm me-1">
                            <i class="fas fa-user-tie me-1"></i> Vehicle Owners
                        </a>
                        <a href="{{ route('fleet.drivers') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-id-card me-1"></i> Commercial Drivers
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12">
        @livewire('fleet.fleet-alerts-manager')
    </div>
</div>
@endsection

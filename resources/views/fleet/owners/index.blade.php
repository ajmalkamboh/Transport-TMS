@extends('layouts.app')

@section('title', 'Vehicle Owners (Market Trucks)')

@section('content')
<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body py-3">
                <div class="d-flex flex-wrap align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-l bg-primary rounded-circle me-3">
                            <div class="avatar-name text-white"><span class="fas fa-user-tie"></span></div>
                        </div>
                        <div>
                            <h5 class="fw-bolder mb-0 text-primary">Vehicle Owners & Third-Party Fleets</h5>
                            <div class="fs--1 text-muted">Govern market truck owners, hire contracts (trip/%, per-km, rental), banking coordinates, and payment models.</div>
                        </div>
                    </div>
                    <div class="mt-2 mt-md-0">
                        <a href="{{ route('fleet.vehicles') }}" class="btn btn-outline-secondary btn-sm me-1">
                            <i class="fas fa-truck me-1"></i> Fleet Vehicles
                        </a>
                        <a href="{{ route('fleet.drivers') }}" class="btn btn-outline-secondary btn-sm me-1">
                            <i class="fas fa-id-card me-1"></i> Commercial Drivers
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
        @livewire('fleet.vehicle-owner-manager')
    </div>
</div>
@endsection


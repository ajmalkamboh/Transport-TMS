@extends('layouts.app')

@section('title', 'Branch & Depot Network Management')

@section('content')
<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body py-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-l bg-primary rounded-circle me-3">
                            <div class="avatar-name text-white"><span class="fas fa-code-branch"></span></div>
                        </div>
                        <div>
                            <h5 class="fw-bolder mb-0 text-primary">Operational Branch & Depot Management</h5>
                            <div class="fs--1 text-muted">Configure multi-city transport hubs, booking offices, delivery terminals, and loading dock parameters.</div>
                        </div>
                    </div>
                    <div>
                        <a href="{{ route('settings.company') }}" class="btn btn-outline-secondary btn-sm me-1">
                            <i class="fas fa-building me-1"></i> Company Profile
                        </a>
                        <a href="{{ route('settings.financial') }}" class="btn btn-outline-secondary btn-sm me-1">
                            <i class="fas fa-calendar-alt me-1"></i> Fiscal Years
                        </a>
                        <a href="{{ route('settings.master-data') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-database me-1"></i> Master Data
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12">
        @livewire('settings.branch-manager')
    </div>
</div>
@endsection

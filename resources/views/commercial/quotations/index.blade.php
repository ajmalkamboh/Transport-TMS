@extends('layouts.app')

@section('title', 'Freight Quotations')

@section('content')
<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body py-3">
                <div class="d-flex flex-wrap align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-l bg-primary rounded-circle me-3">
                            <div class="avatar-name text-white"><span class="fas fa-file-invoice"></span></div>
                        </div>
                        <div>
                            <h5 class="fw-bolder mb-0 text-primary">Freight Quotations & Cost Estimations</h5>
                            <div class="fs--1 text-muted">Generate professional multi-model freight quotations, manage client approvals, and execute 1-click booking conversions with locked pricing terms.</div>
                        </div>
                    </div>
                    <div class="mt-2 mt-md-0">
                        <a href="{{ route('commercial.customers') }}" class="btn btn-outline-secondary btn-sm me-1">
                            <i class="fas fa-users me-1"></i> Customer Accounts
                        </a>
                        <a href="{{ route('commercial.bookings') }}" class="btn btn-outline-secondary btn-sm me-1">
                            <i class="fas fa-calendar-check me-1"></i> Cargo Bookings
                        </a>
                        <a href="{{ route('commercial.consignments') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-receipt me-1"></i> Consignment Bilties
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12">
        @livewire('commercial.quotation-manager')
    </div>
</div>
@endsection

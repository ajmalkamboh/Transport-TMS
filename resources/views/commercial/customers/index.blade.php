@extends('layouts.app')

@section('title', 'Customer & Client Accounts')

@section('content')
<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body py-3">
                <div class="d-flex flex-wrap align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-l bg-primary rounded-circle me-3">
                            <div class="avatar-name text-white"><span class="fas fa-users"></span></div>
                        </div>
                        <div>
                            <h5 class="fw-bolder mb-0 text-primary">Commercial Customers & Client Accounts</h5>
                            <div class="fs--1 text-muted">Govern corporate and individual transport clients, billing addresses, tax credentials, credit limits, and freight contracts.</div>
                        </div>
                    </div>
                    <div class="mt-2 mt-md-0">
                        <a href="{{ route('commercial.quotations') }}" class="btn btn-outline-secondary btn-sm me-1">
                            <i class="fas fa-file-invoice me-1"></i> Freight Quotations
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
        @livewire('commercial.customer-manager')
    </div>
</div>
@endsection


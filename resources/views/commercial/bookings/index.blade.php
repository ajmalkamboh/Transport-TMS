@extends('layouts.app')

@section('title', 'Cargo Booking Orders')

@section('content')
<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body py-3">
                <div class="d-flex flex-wrap align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-l bg-primary rounded-circle me-3">
                            <div class="avatar-name text-white"><span class="fas fa-calendar-check"></span></div>
                        </div>
                        <div>
                            <h5 class="fw-bolder mb-0 text-primary">Cargo Booking Orders & Dispatch Schedules</h5>
                            <div class="fs--1 text-muted">Manage confirmed bookings, multi-consignment groups, customer advance collection, payment terms, and dispatch execution.</div>
                        </div>
                    </div>
                    <div class="mt-2 mt-md-0">
                        <a href="{{ route('commercial.customers') }}" class="btn btn-outline-secondary btn-sm me-1">
                            <i class="fas fa-users me-1"></i> Customer Accounts
                        </a>
                        <a href="{{ route('commercial.quotations') }}" class="btn btn-outline-secondary btn-sm me-1">
                            <i class="fas fa-file-invoice me-1"></i> Freight Quotations
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
        @livewire('commercial.booking-manager')
    </div>
</div>
@endsection


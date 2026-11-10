@extends('layouts.print')

@section('title', 'Trip Sheet / Driver Manifest - ' . $trip->trip_number)

@section('content')
<div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
    <div>
        <h4 class="fw-bold text-primary mb-0">{{ $trip->company->name }}</h4>
        <div class="text-muted fs--1">DISPATCH OPERATIONS - DRIVER TRIP SHEET & ROUTE MANIFEST</div>
        <div class="text-muted fs--2">{{ $trip->branch?->name ?: 'Central Terminal' }} | Generated: {{ now()->format('d M Y H:i') }}</div>
    </div>
    <div class="text-end">
        <div class="badge bg-success fs--2 mb-1">OFFICIAL DISPATCH MANIFEST</div>
        <div class="fs-1 fw-bolder text-primary">{{ $trip->trip_number }}</div>
        <div class="fs--2 text-muted">Trip Date: {{ $trip->trip_date->format('d M Y') }}</div>
        <div class="fs--2 text-muted">Status: {{ strtoupper($trip->trip_status) }}</div>
    </div>
</div>

<div class="row g-2 mb-3">
    <div class="col-4">
        <div class="p-2 border rounded bg-light h-100">
            <h6 class="fw-bold fs--2 text-uppercase text-muted mb-1">ROUTING & TIMINGS:</h6>
            <div class="fs--1"><strong>Origin:</strong> {{ $trip->origin_city }}</div>
            <div class="fs--1"><strong>Destination:</strong> {{ $trip->destination_city }}</div>
            <div class="fs--2 text-muted">Route: {{ $trip->planned_route ?: 'Direct Highway Route' }}</div>
            <div class="fs--2 text-muted">Actual Departure: {{ $trip->actual_departure?->format('d M Y H:i') ?: 'Scheduled' }}</div>
            <div class="fs--2 text-muted">Departure Odometer: <strong>{{ $trip->departure_odometer_km ? number_format($trip->departure_odometer_km) . ' KM' : 'N/A' }}</strong></div>
        </div>
    </div>
    <div class="col-4">
        <div class="p-2 border rounded bg-light h-100">
            <h6 class="fw-bold fs--2 text-uppercase text-muted mb-1">DEPLOYED VEHICLES:</h6>
            @forelse($trip->resources->where('resource_type', 'vehicle')->where('is_active', true) as $res)
                <div class="fs--1">
                    <span class="badge bg-secondary fs--2">{{ strtoupper(str_replace('_', ' ', $res->role)) }}</span>
                    <strong>{{ $res->vehicle?->registration_number }}</strong>
                </div>
                <div class="fs--2 text-muted">{{ $res->vehicle?->make_model }} (Cap: {{ $res->vehicle?->capacity_tons }}T)</div>
            @empty
                <div class="fs--2 text-muted">No vehicle assigned</div>
            @endforelse
        </div>
    </div>
    <div class="col-4">
        <div class="p-2 border rounded bg-light h-100">
            <h6 class="fw-bold fs--2 text-uppercase text-muted mb-1">DRIVER CREW & DISPATCH:</h6>
            @forelse($trip->resources->where('resource_type', 'driver')->where('is_active', true) as $res)
                <div class="fs--1">
                    <span class="badge bg-info text-dark fs--2">{{ strtoupper(str_replace('_', ' ', $res->role)) }}</span>
                    <strong>{{ $res->driver?->name }}</strong>
                </div>
                <div class="fs--2">Phone: {{ $res->driver?->phone }} | CNIC: {{ $res->driver?->cnic }}</div>
            @empty
                <div class="fs--2 text-muted">No driver assigned</div>
            @endforelse
            <div class="fs--2 text-muted mt-1">Dispatcher: <strong>{{ $trip->dispatcher?->name ?: 'Duty Officer' }}</strong></div>
        </div>
    </div>
</div>

<h6 class="fw-bold text-uppercase fs--2 mb-2">DELIVERY DROP-OFF POINTS & CONSIGNMENTS</h6>
<table class="table table-print mb-3 w-100">
    <thead>
        <tr>
            <th style="width: 5%;">#</th>
            <th style="width: 15%;">Bilty / Consignment</th>
            <th style="width: 25%;">Destination & Consignee</th>
            <th style="width: 25%;">Delivery Contact & Address</th>
            <th style="width: 15%;" class="text-center">Packages</th>
            <th style="width: 15%;" class="text-end">Weight (KG)</th>
        </tr>
    </thead>
    <tbody>
        @forelse($trip->tripConsignments as $index => $alloc)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>
                    <div class="fw-bold">{{ $alloc->consignment->consignment_number }}</div>
                    <div class="fs--2 text-muted">Sender: {{ $alloc->consignment->customer?->name }}</div>
                </td>
                <td>
                    <div class="fw-semi-bold">{{ $alloc->consignment->consignee_name }}</div>
                    <div class="fs--2 text-muted">City: <strong>{{ $alloc->consignment->consignee_city }}</strong></div>
                </td>
                <td>
                    <div class="fs--1">{{ $alloc->consignment->consignee_phone }}</div>
                    <div class="fs--2 text-muted">{{ $alloc->consignment->consignee_address ?: 'Contact on arrival' }}</div>
                </td>
                <td class="text-center fw-bold fs-0">
                    {{ $alloc->assigned_quantity }}
                </td>
                <td class="text-end">
                    {{ number_format($alloc->weight_kg, 1) }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="text-center text-muted py-3">No consignments allocated.</td>
            </tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr class="fw-bold bg-light">
            <td colspan="4" class="text-end">TOTAL CONSIGNMENTS ON MANIFEST:</td>
            <td class="text-center fs-0 text-primary">{{ $trip->total_packages_count }} Pkgs</td>
            <td class="text-end text-primary">{{ number_format($trip->total_weight_tons * 1000, 1) }} KG</td>
        </tr>
    </tfoot>
</table>

@if($trip->checkpoints->isNotEmpty())
    <h6 class="fw-bold text-uppercase fs--2 mb-1">TRANSIT CHECKPOINTS & EN-ROUTE LOG:</h6>
    <table class="table table-print mb-3 w-100">
        <thead>
            <tr>
                <th style="width: 10%;">Stop #</th>
                <th style="width: 30%;">Milestone / Checkpoint</th>
                <th style="width: 20%;">Reached Time</th>
                <th style="width: 15%;">Odometer</th>
                <th style="width: 25%;">Delay / Route Notes</th>
            </tr>
        </thead>
        <tbody>
            @foreach($trip->checkpoints as $cp)
                <tr>
                    <td class="text-center">{{ $cp->stop_order }}</td>
                    <td class="fw-semi-bold">{{ $cp->checkpoint_name }}</td>
                    <td>{{ $cp->actual_arrival?->format('d M H:i') ?: '-' }}</td>
                    <td>{{ $cp->odometer_km ? number_format($cp->odometer_km) . ' KM' : '-' }}</td>
                    <td>
                        @if($cp->delay_minutes > 0)
                            <span class="badge bg-warning text-dark">{{ $cp->delay_minutes }}m delay</span>
                            <span class="fs--2">{{ $cp->delay_reason }}</span>
                        @else
                            <span class="text-success fs--2">On Schedule</span>
                        @endif
                        @if($cp->notes)<div class="fs--2 text-muted">{{ $cp->notes }}</div>@endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<div class="row g-2 mt-4 pt-3 border-top">
    <div class="col-6">
        <div class="text-center">
            <div style="height: 45px;"></div>
            <div class="border-top pt-1 fw-bold fs--1">Dispatcher / Terminal Authority</div>
            <div class="fs--2 text-muted">Authorized Vehicle Departure & Safe Route Plan</div>
        </div>
    </div>
    <div class="col-6">
        <div class="text-center">
            <div style="height: 45px;"></div>
            <div class="border-top pt-1 fw-bold fs--1">Driver Sign-off & Acceptance</div>
            <div class="fs--2 text-muted">I acknowledge receipt of trip documents and manifest</div>
        </div>
    </div>
</div>
@endsection


@extends('layouts.print')

@section('title', 'Fuel Slip - ' . $fuelEntry->entry_number)

@section('content')
<div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
    <div>
        <h4 class="fw-bold text-primary mb-0">{{ $fuelEntry->company->name }}</h4>
        <div class="text-muted fs--1">FLEET OPERATIONS & FUEL DISPENSING CONTROL SLIP</div>
        <div class="text-muted fs--2">{{ $fuelEntry->branch?->name ?: 'Central Fuel Depot' }} | Date: {{ $fuelEntry->entry_date->format('d M Y') }}</div>
    </div>
    <div class="text-end">
        <div class="badge bg-primary fs--2 mb-1">REFUEL SLIP</div>
        <div class="fs-1 fw-bolder text-primary">{{ $fuelEntry->entry_number }}</div>
        <div class="fs--2 text-muted">Status: {{ strtoupper($fuelEntry->status) }}</div>
        <div class="fs--2 text-muted">Trip Ref: {{ $fuelEntry->trip?->trip_number ?: 'Local Fleet Service' }}</div>
    </div>
</div>

<div class="row g-2 mb-3">
    <div class="col-6">
        <div class="p-2 border rounded bg-light h-100">
            <h6 class="fw-bold fs--2 text-uppercase text-muted mb-1">VEHICLE & CREW ASSIGNMENT:</h6>
            <div class="fs--1"><strong>Registration #:</strong> {{ $fuelEntry->vehicle?->registration_number }}</div>
            <div class="fs--2 text-muted">Vehicle: {{ $fuelEntry->vehicle?->make_model }} ({{ $fuelEntry->vehicle?->vehicle_type }})</div>
            <div class="fs--1 mt-1"><strong>Commercial Driver:</strong> {{ $fuelEntry->driver?->name ?: 'Duty Driver' }}</div>
            <div class="fs--2 text-muted">Driver Cell: {{ $fuelEntry->driver?->phone ?: 'N/A' }}</div>
        </div>
    </div>
    <div class="col-6">
        <div class="p-2 border rounded bg-light h-100">
            <h6 class="fw-bold fs--2 text-uppercase text-muted mb-1">FUEL OUTLET & PAYMENT METHOD:</h6>
            <div class="fs--1"><strong>Fuel Supplier:</strong> {{ $fuelEntry->supplier?->name ?: 'Company Bunker / Direct' }}</div>
            <div class="fs--2 text-muted">Pump / Station: {{ $fuelEntry->station?->name ?: 'Designated Fuel Station' }}</div>
            <div class="fs--1 mt-1"><strong>Payment:</strong> {{ strtoupper(str_replace('_', ' ', $fuelEntry->payment_method)) }}</div>
            @if($fuelEntry->card)
                <div class="fs--2 text-muted">Fleet Card #: <strong>{{ $fuelEntry->card->card_number }}</strong></div>
            @endif
        </div>
    </div>
</div>

<h6 class="fw-bold text-uppercase fs--2 mb-2">FUEL DISPENSING PARTICULARS</h6>
<table class="table table-print mb-3 w-100">
    <thead>
        <tr>
            <th style="width: 25%;">Fuel Type</th>
            <th style="width: 25%;" class="text-center">Quantity (Liters)</th>
            <th style="width: 25%;" class="text-end">Rate / Liter</th>
            <th style="width: 25%;" class="text-end">Total Amount</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="fw-bold fs-0 text-uppercase">{{ $fuelEntry->fuel_type }}</td>
            <td class="text-center fw-bold fs-1 text-primary">{{ number_format($fuelEntry->quantity_liters, 2) }} L</td>
            <td class="text-end fs-0">PKR {{ number_format($fuelEntry->rate_per_liter, 2) }}</td>
            <td class="text-end fw-bold fs-1 text-success">PKR {{ number_format($fuelEntry->total_amount, 2) }}</td>
        </tr>
    </tbody>
</table>

<h6 class="fw-bold text-uppercase fs--2 mb-2">ODOMETER & FLEET FUEL ECONOMY PERFORMANCE</h6>
<div class="row g-2 mb-3">
    <div class="col-3">
        <div class="p-2 border rounded text-center">
            <div class="fs--2 text-muted">PREVIOUS ODOMETER</div>
            <div class="fw-bold fs-0">{{ number_format($fuelEntry->previous_odometer_km) }} KM</div>
        </div>
    </div>
    <div class="col-3">
        <div class="p-2 border rounded text-center">
            <div class="fs--2 text-muted">CURRENT ODOMETER</div>
            <div class="fw-bold fs-0 text-dark">{{ number_format($fuelEntry->odometer_km) }} KM</div>
        </div>
    </div>
    <div class="col-3">
        <div class="p-2 border rounded text-center">
            <div class="fs--2 text-muted">DISTANCE RUN</div>
            <div class="fw-bold fs-0 text-primary">{{ number_format($fuelEntry->distance_km) }} KM</div>
        </div>
    </div>
    <div class="col-3">
        <div class="p-2 border rounded text-center">
            <div class="fs--2 text-muted">FUEL ECONOMY</div>
            <div class="fw-bold fs-0 text-success">{{ $fuelEntry->fuel_economy_km_per_liter ? $fuelEntry->fuel_economy_km_per_liter . ' KM/L' : 'N/A' }}</div>
            <div class="fs--2 text-muted">{{ $fuelEntry->cost_per_km ? 'PKR ' . $fuelEntry->cost_per_km . '/KM' : '' }}</div>
        </div>
    </div>
</div>

@if($fuelEntry->is_suspicious)
    <div class="alert alert-warning border mb-3 p-2 fs--1">
        <strong>Fuel & Mileage Audit Flag:</strong> {{ $fuelEntry->anomaly_reason }}
    </div>
@endif

<div class="row g-2 mt-4 pt-3 border-top">
    <div class="col-6">
        <div class="text-center">
            <div style="height: 45px;"></div>
            <div class="border-top pt-1 fw-bold fs--1">Commercial Driver Acceptance</div>
            <div class="fs--2 text-muted">Confirmed fuel received directly into vehicle tank</div>
        </div>
    </div>
    <div class="col-6">
        <div class="text-center">
            <div style="height: 45px;"></div>
            <div class="border-top pt-1 fw-bold fs--1">Fleet / Fuel Control Supervisor</div>
            <div class="fs--2 text-muted">Odometer & consumption verified in fleet system</div>
        </div>
    </div>
</div>
@endsection


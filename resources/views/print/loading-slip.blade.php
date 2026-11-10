@extends('layouts.print')

@section('title', 'Loading Slip - ' . $trip->trip_number)

@section('content')
<div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
    <div>
        <h4 class="fw-bold text-primary mb-0">{{ $trip->company->name }}</h4>
        <div class="text-muted fs--1">WAREHOUSE & TERMINAL OPERATIONS - LOADING TALLY SLIP</div>
        <div class="text-muted fs--2">{{ $trip->branch?->name ?: 'Central Terminal' }} | Printed: {{ now()->format('d M Y H:i') }}</div>
    </div>
    <div class="text-end">
        <div class="badge bg-primary fs--2 mb-1">LOADING MANIFEST & SLIP</div>
        <div class="fs-1 fw-bolder text-primary">{{ $trip->trip_number }}</div>
        <div class="fs--2 text-muted">Trip Date: {{ $trip->trip_date->format('d M Y') }}</div>
        <div class="fs--2 text-muted">Status: {{ strtoupper($trip->trip_status) }}</div>
    </div>
</div>

<div class="row g-2 mb-3">
    <div class="col-6">
        <div class="p-2 border rounded bg-light h-100">
            <h6 class="fw-bold fs--2 text-uppercase text-muted mb-1">ROUTE & STATION DETAILS:</h6>
            <div class="fs--1">Origin Terminal: <strong>{{ $trip->origin_city }}</strong></div>
            <div class="fs--1">Destination Terminal: <strong>{{ $trip->destination_city }}</strong></div>
            <div class="fs--2 text-muted">Planned Route: {{ $trip->planned_route ?: 'Direct Highway' }}</div>
            <div class="fs--2 text-muted">Est. Departure: {{ $trip->estimated_departure?->format('d M Y H:i') ?: 'TBD' }}</div>
        </div>
    </div>
    <div class="col-6">
        <div class="p-2 border rounded bg-light h-100">
            <h6 class="fw-bold fs--2 text-uppercase text-muted mb-1">VEHICLE & CREW ASSIGNMENTS:</h6>
            @foreach($trip->resources->where('is_active', true) as $res)
                @if($res->resource_type === 'vehicle')
                    <div class="fs--1">
                        <strong>{{ strtoupper(str_replace('_', ' ', $res->role)) }}:</strong>
                        {{ $res->vehicle?->registration_number }} ({{ $res->vehicle?->make_model }})
                    </div>
                @else
                    <div class="fs--1">
                        <strong>{{ strtoupper(str_replace('_', ' ', $res->role)) }}:</strong>
                        {{ $res->driver?->name }} (Cell: {{ $res->driver?->phone }})
                    </div>
                @endif
            @endforeach
            <div class="fs--2 text-muted mt-1">Supervisor: <strong>{{ $trip->loading_supervisor ?: 'Terminal Incharge' }}</strong></div>
        </div>
    </div>
</div>

<h6 class="fw-bold text-uppercase fs--2 mb-2">ALLOCATED CARGO CONSIGNMENTS (LOADED ITEMS)</h6>
<table class="table table-print mb-3 w-100">
    <thead>
        <tr>
            <th style="width: 5%;">#</th>
            <th style="width: 18%;">Consignment (Bilty)</th>
            <th style="width: 25%;">Consignor & Consignee</th>
            <th style="width: 25%;">Cargo Description</th>
            <th style="width: 12%;" class="text-center">Loaded Pkgs</th>
            <th style="width: 15%;" class="text-end">Weight (KG)</th>
        </tr>
    </thead>
    <tbody>
        @forelse($trip->tripConsignments as $index => $alloc)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>
                    <div class="fw-bold">{{ $alloc->consignment->consignment_number }}</div>
                    <div class="fs--2 text-muted">Ref: {{ $alloc->consignment->booking?->booking_number }}</div>
                </td>
                <td>
                    <div class="fw-semi-bold">{{ $alloc->consignment->customer?->name }}</div>
                    <div class="fs--2 text-muted">&rarr; {{ $alloc->consignment->consignee_name }} ({{ $alloc->consignment->consignee_city }})</div>
                </td>
                <td>
                    <div>{{ $alloc->consignment->cargo_description }}</div>
                    <div class="fs--2 text-muted">Packaging: {{ ucfirst($alloc->consignment->packaging_type) }}</div>
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
                <td colspan="6" class="text-center text-muted py-3">No consignments allocated to this trip yet.</td>
            </tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr class="fw-bold bg-light">
            <td colspan="4" class="text-end">TOTAL CARGO LOADED:</td>
            <td class="text-center fs-0 text-primary">{{ $trip->total_packages_count }} Pkgs</td>
            <td class="text-end text-primary">{{ number_format($trip->total_weight_tons * 1000, 1) }} KG ({{ $trip->total_weight_tons }} Tons)</td>
        </tr>
    </tfoot>
</table>

<div class="row g-2 mt-4 pt-3 border-top">
    <div class="col-6">
        <div class="text-center">
            <div style="height: 50px;"></div>
            <div class="border-top pt-1 fw-bold fs--1">Warehouse Loading Supervisor</div>
            <div class="fs--2 text-muted">Verified goods loaded in safe and sound condition</div>
        </div>
    </div>
    <div class="col-6">
        <div class="text-center">
            <div style="height: 50px;"></div>
            <div class="border-top pt-1 fw-bold fs--1">Commercial Driver Acknowledgment</div>
            <div class="fs--2 text-muted">Confirmed physical count & sealed vehicle handover</div>
        </div>
    </div>
</div>
@endsection


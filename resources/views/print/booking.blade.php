@extends('layouts.print')

@section('title', 'Booking Order - ' . $booking->booking_number)

@section('content')
<div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
    <div>
        <h4 class="fw-bold text-primary mb-1">{{ $booking->company->name }}</h4>
        <div class="text-muted fs--1">{{ $booking->company->address ?: 'Head Office Logistics Complex' }}</div>
        <div class="text-muted fs--1">Phone: {{ $booking->company->phone }} | Email: {{ $booking->company->email }}</div>
    </div>
    <div class="text-end">
        <h5 class="fw-bold text-uppercase text-secondary mb-1">BOOKING CONFIRMATION</h5>
        <div class="fs-0 fw-bold text-primary">{{ $booking->booking_number }}</div>
        <div class="text-muted fs--1">Booking Date: {{ $booking->booking_date->format('d M Y') }}</div>
        <span class="badge bg-success fs--2">{{ strtoupper($booking->status) }}</span>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6">
        <div class="p-3 bg-light rounded border">
            <h6 class="fw-bold fs--1 text-uppercase text-muted mb-2">CONSIGNOR / SHIPPER:</h6>
            <div class="fw-bold fs-0 text-dark">{{ $booking->customer->name }}</div>
            <div class="fs--1 text-muted">{{ $booking->customer->company_name }}</div>
            <div class="fs--1">Contact: {{ $booking->customer->contact_person }} ({{ $booking->customer->phone }})</div>
            <div class="fs--1">Origin: {{ $booking->origin_city }} ({{ $booking->origin_address ?: 'Origin Depot' }})</div>
        </div>
    </div>
    <div class="col-6">
        <div class="p-3 bg-light rounded border">
            <h6 class="fw-bold fs--1 text-uppercase text-muted mb-2">DESTINATION & SCHEDULE:</h6>
            <div class="fs--1 mb-1"><strong>Destination City:</strong> {{ $booking->destination_city }}</div>
            <div class="fs--1 mb-1"><strong>Delivery Address:</strong> {{ $booking->destination_address ?: 'Consignee Premises' }}</div>
            <div class="fs--1 mb-1"><strong>Expected Dispatch:</strong> {{ $booking->expected_dispatch_date?->format('d M Y') ?: 'Immediate' }}</div>
            <div class="fs--1 mb-1"><strong>Expected Delivery:</strong> {{ $booking->expected_delivery_date?->format('d M Y') ?: 'Per Corridor ETA' }}</div>
            <div class="fs--1"><strong>Payment Terms:</strong> <span class="badge bg-secondary">{{ strtoupper(str_replace('_', ' ', $booking->payment_terms)) }}</span></div>
        </div>
    </div>
</div>

<h6 class="fw-bold fs--1 text-uppercase mb-2">Booked Consignments Breakdown</h6>
<table class="table table-print mb-4 w-100">
    <thead>
        <tr>
            <th>Bilty (CN) #</th>
            <th>Consignee (Receiver)</th>
            <th>Cargo Description</th>
            <th class="text-center">Packages</th>
            <th class="text-center">Weight (KG)</th>
            <th class="text-end">Charges (PKR)</th>
        </tr>
    </thead>
    <tbody>
        @foreach($booking->consignments as $cn)
            <tr>
                <td class="fw-bold text-primary">{{ $cn->consignment_number }}</td>
                <td>
                    <div class="fw-semi-bold">{{ $cn->consignee_name }}</div>
                    <div class="fs--2 text-muted">{{ $cn->consignee_city }} | {{ $cn->consignee_phone }}</div>
                </td>
                <td>{{ $cn->cargo_description }} ({{ $cn->packaging_type }})</td>
                <td class="text-center">{{ $cn->total_quantity }}</td>
                <td class="text-center">{{ number_format($cn->chargeable_weight_kg, 2) }}</td>
                <td class="text-end fw-semi-bold">{{ number_format($cn->total_charges, 2) }}</td>
            </tr>
        @endforeach
        <tr class="bg-light fw-bold">
            <td colspan="3" class="text-end">Total Freight & Surcharges:</td>
            <td class="text-center">{{ $booking->total_packages_count }}</td>
            <td class="text-center">{{ number_format($booking->total_weight_tons * 1000, 2) }}</td>
            <td class="text-end">{{ number_format($booking->net_amount, 2) }}</td>
        </tr>
        @if($booking->advance_received > 0)
        <tr class="text-success fw-semi-bold">
            <td colspan="5" class="text-end">Customer Advance Collected ({{ $booking->advance_payment_method }} - {{ $booking->advance_receipt_reference }}):</td>
            <td class="text-end">- {{ number_format($booking->advance_received, 2) }}</td>
        </tr>
        @endif
        <tr class="fs-0 fw-bold">
            <td colspan="5" class="text-end text-danger">OUTSTANDING BALANCE DUE (PKR):</td>
            <td class="text-end text-danger">{{ number_format($booking->balance_amount, 2) }}</td>
        </tr>
    </tbody>
</table>

<div class="row g-3 mb-4">
    <div class="col-8">
        <h6 class="fw-bold fs--1 text-uppercase text-muted mb-1">Special Handling Instructions:</h6>
        <div class="fs--2 text-muted">
            {{ $booking->special_instructions ?: 'Ensure cargo is securely lashed and weatherproof tarpaulin is fastened during transit. Report any seal tampering immediately.' }}
        </div>
    </div>
    <div class="col-4 text-center">
        <div class="stamp-box mb-1">
            Dispatch Supervisor / Stamp
        </div>
        <div class="fs--2 text-muted">{{ $booking->branch?->name ?: 'Origin Terminal Hub' }}</div>
    </div>
</div>
@endsection


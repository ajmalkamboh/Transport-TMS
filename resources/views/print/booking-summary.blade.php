@extends('layouts.print')

@section('title', 'Booking Summary - ' . $booking->booking_number)

@section('content')
<div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
    <div>
        <h4 class="fw-bold text-primary mb-0">{{ $booking->company->name }}</h4>
        <div class="text-muted fs--1">BOOKING DISPATCH SUMMARY & MANIFEST</div>
    </div>
    <div class="text-end">
        <div class="fs-1 fw-bold text-primary">{{ $booking->booking_number }}</div>
        <div class="fs--2 text-muted">Booking Date: {{ $booking->booking_date->format('d M Y') }}</div>
    </div>
</div>

<div class="row g-2 mb-3">
    <div class="col-4">
        <div class="border p-2 rounded bg-light">
            <div class="fs--2 text-muted">CUSTOMER / SHIPPER</div>
            <div class="fw-bold fs--1">{{ $booking->customer->name }}</div>
            <div class="fs--2">{{ $booking->customer->phone }}</div>
        </div>
    </div>
    <div class="col-4">
        <div class="border p-2 rounded bg-light">
            <div class="fs--2 text-muted">TRANSIT CORRIDOR</div>
            <div class="fw-bold fs--1">{{ $booking->origin_city }} &rarr; {{ $booking->destination_city }}</div>
            <div class="fs--2">{{ $booking->total_weight_tons }} Tons Total Weight</div>
        </div>
    </div>
    <div class="col-4">
        <div class="border p-2 rounded bg-light">
            <div class="fs--2 text-muted">FINANCIAL SUMMARY</div>
            <div class="fw-bold fs--1">Net: PKR {{ number_format($booking->net_amount, 2) }}</div>
            <div class="fs--2 text-success">Advance: PKR {{ number_format($booking->advance_received, 2) }}</div>
        </div>
    </div>
</div>

<h6 class="fw-bold fs--1 text-uppercase mb-2">Consignment Notes & Quantities Roster</h6>
<table class="table table-print mb-4 w-100">
    <thead>
        <tr>
            <th>Bilty (CN) #</th>
            <th>Consignee</th>
            <th>Cargo</th>
            <th class="text-center">Total Pkgs</th>
            <th class="text-center">Dispatched</th>
            <th class="text-center">Remaining</th>
            <th class="text-center">Status</th>
            <th class="text-end">Charges (PKR)</th>
        </tr>
    </thead>
    <tbody>
        @foreach($booking->consignments as $cn)
            <tr>
                <td class="fw-bold text-primary">{{ $cn->consignment_number }}</td>
                <td>{{ $cn->consignee_name }} ({{ $cn->consignee_city }})</td>
                <td>{{ $cn->cargo_description }} ({{ $cn->packaging_type }})</td>
                <td class="text-center">{{ $cn->total_quantity }}</td>
                <td class="text-center text-success fw-bold">{{ $cn->dispatched_quantity }}</td>
                <td class="text-center text-warning fw-bold">{{ $cn->remaining_quantity }}</td>
                <td class="text-center">{{ ucfirst(str_replace('_', ' ', $cn->status)) }}</td>
                <td class="text-end fw-semi-bold">{{ number_format($cn->total_charges, 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="row g-2">
    <div class="col-6">
        <div class="border p-2 rounded" style="min-height: 80px;">
            <div class="fs--2 text-muted mb-4">Operations Dispatch Incharge</div>
            <div class="border-top pt-1 fs--2 fw-semi-bold">Manifest Certified</div>
        </div>
    </div>
    <div class="col-6">
        <div class="border p-2 rounded" style="min-height: 80px;">
            <div class="fs--2 text-muted mb-4">Commercial Accounts Verifier</div>
            <div class="border-top pt-1 fs--2 fw-semi-bold">Billing & Credit Verified</div>
        </div>
    </div>
</div>
@endsection

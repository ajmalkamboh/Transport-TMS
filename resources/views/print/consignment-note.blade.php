@extends('layouts.print')

@section('title', 'Bilty (Consignment Note) - ' . $consignment->consignment_number)

@section('content')
<div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
    <div>
        <h4 class="fw-bold text-primary mb-0">{{ $consignment->company->name }}</h4>
        <div class="text-muted fs--1">GOODS FORWARDING & TRANSPORT CONTRACTORS</div>
        <div class="text-muted fs--2">{{ $consignment->company->address ?: 'Nationwide Logistics Network' }} | Tel: {{ $consignment->company->phone }}</div>
    </div>
    <div class="text-end">
        <div class="badge bg-danger fs--2 mb-1">LORRY RECEIPT / BILTY</div>
        <div class="fs-1 fw-bolder text-primary">{{ $consignment->consignment_number }}</div>
        <div class="fs--2 text-muted">Booking Ref: {{ $consignment->booking->booking_number }}</div>
        <div class="fs--2 text-muted">Date: {{ $consignment->consignment_date->format('d M Y') }}</div>
    </div>
</div>

<div class="row g-2 mb-3">
    <div class="col-6">
        <div class="p-2 border rounded bg-light h-100">
            <h6 class="fw-bold fs--2 text-uppercase text-muted mb-1">CONSIGNOR (SENDER / SHIPPED BY):</h6>
            <div class="fw-bold fs--1 text-dark">{{ $consignment->customer->name }}</div>
            <div class="fs--2 text-muted">{{ $consignment->customer->company_name }}</div>
            <div class="fs--2">Phone: {{ $consignment->customer->phone }}</div>
            <div class="fs--2">Dispatch Station: <strong>{{ $consignment->pickup_point }}</strong></div>
        </div>
    </div>
    <div class="col-6">
        <div class="p-2 border rounded bg-light h-100">
            <h6 class="fw-bold fs--2 text-uppercase text-muted mb-1">CONSIGNEE (TO BE DELIVERED TO):</h6>
            <div class="fw-bold fs--1 text-dark">{{ $consignment->consignee_name }}</div>
            <div class="fs--2 text-muted">{{ $consignment->consignee_address ?: 'Destination Station' }}</div>
            <div class="fs--2">Destination City: <strong>{{ $consignment->consignee_city }}</strong></div>
            <div class="fs--2">Receiver Phone: {{ $consignment->consignee_phone }}</div>
        </div>
    </div>
</div>

<table class="table table-print mb-3 w-100">
    <thead>
        <tr>
            <th style="width: 15%;">Packages</th>
            <th style="width: 15%;">Packaging</th>
            <th style="width: 35%;">Cargo Description</th>
            <th style="width: 15%;" class="text-center">Actual Weight</th>
            <th style="width: 20%;" class="text-end">Freight & Charges</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="fw-bold text-center fs-0">{{ $consignment->total_quantity }}</td>
            <td>{{ ucfirst($consignment->packaging_type) }}</td>
            <td>
                <div class="fw-semi-bold">{{ $consignment->cargo_description }}</div>
                <div class="fs--2 text-muted">Category: {{ str_replace('_', ' ', $consignment->cargo_category) }}</div>
                @if($consignment->declared_value)
                    <div class="fs--2 text-muted">Declared Value: PKR {{ number_format($consignment->declared_value, 2) }}</div>
                @endif
            </td>
            <td class="text-center">
                <div>{{ number_format($consignment->gross_weight_kg, 1) }} KG</div>
                <div class="fs--2 text-muted">Chg: {{ number_format($consignment->chargeable_weight_kg, 1) }} KG</div>
            </td>
            <td class="text-end">
                <div class="fw-bold fs-0">PKR {{ number_format($consignment->total_charges, 2) }}</div>
                <span class="badge bg-{{ $consignment->payment_mode === 'paid' ? 'success' : 'warning text-dark' }}">
                    {{ strtoupper($consignment->payment_mode) }}
                </span>
            </td>
        </tr>
    </tbody>
</table>

<div class="row g-2 mb-3">
    <div class="col-8">
        <div class="border p-2 rounded fs--2 text-muted" style="min-height: 80px;">
            <strong>Terms & Carriage Conditions:</strong> Goods carried at owner's risk unless explicitly insured. Demurrage charged if cargo is not cleared within 48 hours of arrival at destination terminal. Carrier is not liable for leakage, natural loss or force majeure incidents during transit.
        </div>
    </div>
    <div class="col-4">
        <div class="border p-2 rounded text-center" style="min-height: 80px;">
            <div class="fs--2 text-muted mb-4">Shipper / Agent Signature</div>
            <div class="border-top pt-1 fs--2 fw-semi-bold">Authorized Booking Clerk</div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between fs--2 text-muted border-top pt-2">
    <div>Bilty Generated: {{ now()->format('d M Y H:i') }} | Origin Branch: {{ $consignment->branch?->name ?: 'Main Hub' }}</div>
    <div>Page 1 of 1 (Consignment Bilty Note)</div>
</div>
@endsection

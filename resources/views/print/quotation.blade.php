@extends('layouts.print')

@section('title', 'Quotation - ' . $quotation->quotation_number)

@section('content')
<div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
    <div>
        <h4 class="fw-bold text-primary mb-1">{{ $quotation->company->name }}</h4>
        <div class="text-muted fs--1">{{ $quotation->company->address ?: 'Head Office Transport Complex' }}</div>
        <div class="text-muted fs--1">Phone: {{ $quotation->company->phone }} | Email: {{ $quotation->company->email }}</div>
        @if($quotation->company->tax_number)
            <div class="text-muted fs--1">NTN / STRN: {{ $quotation->company->tax_number }}</div>
        @endif
    </div>
    <div class="text-end">
        <h5 class="fw-bold text-uppercase text-secondary mb-1">FREIGHT QUOTATION</h5>
        <div class="fs-0 fw-bold text-primary">{{ $quotation->quotation_number }}</div>
        <div class="text-muted fs--1">Date: {{ $quotation->quotation_date->format('d M Y') }}</div>
        <div class="text-danger fs--1 fw-semi-bold">Valid Until: {{ $quotation->valid_until->format('d M Y') }}</div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6">
        <div class="p-3 bg-light rounded border">
            <h6 class="fw-bold fs--1 text-uppercase text-muted mb-2">QUOTATION ISSUED TO:</h6>
            <div class="fw-bold fs-0 text-dark">{{ $quotation->customer->name }}</div>
            @if($quotation->customer->company_name)
                <div class="fs--1 text-muted">{{ $quotation->customer->company_name }}</div>
            @endif
            <div class="fs--1">Attn: {{ $quotation->customer->contact_person ?: $quotation->customer->name }} ({{ $quotation->customer->designation ?: 'Manager' }})</div>
            <div class="fs--1">Phone: {{ $quotation->customer->phone }}</div>
            <div class="fs--1">{{ $quotation->customer->billing_address }}, {{ $quotation->customer->billing_city }}</div>
            @if($quotation->customer->tax_ntn)
                <div class="fs--1">NTN: {{ $quotation->customer->tax_ntn }}</div>
            @endif
        </div>
    </div>
    <div class="col-6">
        <div class="p-3 bg-light rounded border">
            <h6 class="fw-bold fs--1 text-uppercase text-muted mb-2">ROUTE & TRANSIT SPECIFICATIONS:</h6>
            <div class="fs--1 mb-1"><strong>Origin Hub:</strong> {{ $quotation->origin_city }} ({{ $quotation->origin_address ?: 'Hub Depot' }})</div>
            <div class="fs--1 mb-1"><strong>Destination:</strong> {{ $quotation->destination_city }} ({{ $quotation->destination_address ?: 'Consignee Delivery' }})</div>
            <div class="fs--1 mb-1"><strong>Estimated Transit Distance:</strong> {{ $quotation->distance_km ? number_format($quotation->distance_km) . ' KM' : 'Direct Corridor' }}</div>
            <div class="fs--1 mb-1"><strong>Vehicle Type Required:</strong> {{ $quotation->required_vehicle_type }} ({{ $quotation->required_vehicle_category }})</div>
            <div class="fs--1"><strong>Status:</strong> <span class="badge bg-secondary">{{ strtoupper($quotation->status) }}</span></div>
        </div>
    </div>
</div>

<h6 class="fw-bold fs--1 text-uppercase mb-2">Cargo & Freight Tariff Breakdown</h6>
<table class="table table-print mb-4 w-100">
    <thead>
        <tr>
            <th>Description & Cargo Specs</th>
            <th class="text-center">Rate Model</th>
            <th class="text-center">Units / Weight</th>
            <th class="text-end">Base Rate</th>
            <th class="text-end">Amount (PKR)</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>
                <div class="fw-bold">{{ $quotation->cargo_description }}</div>
                <div class="fs--2 text-muted">Category: {{ str_replace('_', ' ', $quotation->cargo_category) }}</div>
            </td>
            <td class="text-center">{{ ucfirst(str_replace('_', ' ', $quotation->rate_type)) }}</td>
            <td class="text-center">{{ $quotation->weight_tons }} Tons / {{ $quotation->package_count }} pkgs</td>
            <td class="text-end">{{ number_format($quotation->base_rate, 2) }}</td>
            <td class="text-end fw-semi-bold">{{ number_format($quotation->freight_amount, 2) }}</td>
        </tr>
        @if($quotation->loading_charges > 0)
        <tr>
            <td colspan="4" class="text-end">Loading & Terminal Handling Charges:</td>
            <td class="text-end">{{ number_format($quotation->loading_charges, 2) }}</td>
        </tr>
        @endif
        @if($quotation->unloading_charges > 0)
        <tr>
            <td colspan="4" class="text-end">Unloading & Destination Handling Charges:</td>
            <td class="text-end">{{ number_format($quotation->unloading_charges, 2) }}</td>
        </tr>
        @endif
        @if($quotation->toll_charges > 0)
        <tr>
            <td colspan="4" class="text-end">Motorway & Highway Toll Taxes:</td>
            <td class="text-end">{{ number_format($quotation->toll_charges, 2) }}</td>
        </tr>
        @endif
        @if($quotation->detention_charges > 0)
        <tr>
            <td colspan="4" class="text-end">Detention Surcharge:</td>
            <td class="text-end">{{ number_format($quotation->detention_charges, 2) }}</td>
        </tr>
        @endif
        @if($quotation->discount_amount > 0)
        <tr class="text-danger">
            <td colspan="4" class="text-end">Negotiated Discount:</td>
            <td class="text-end">- {{ number_format($quotation->discount_amount, 2) }}</td>
        </tr>
        @endif
        @if($quotation->tax_amount > 0)
        <tr class="text-muted">
            <td colspan="4" class="text-end">Provincial Sales Tax ({{ $quotation->tax_percentage }}%):</td>
            <td class="text-end">+ {{ number_format($quotation->tax_amount, 2) }}</td>
        </tr>
        @endif
        <tr class="bg-light fs-0 fw-bold">
            <td colspan="4" class="text-end text-primary">TOTAL ESTIMATED FREIGHT (PKR):</td>
            <td class="text-end text-primary">{{ number_format($quotation->net_total_amount, 2) }}</td>
        </tr>
    </tbody>
</table>

<div class="row g-3 mb-4">
    <div class="col-8">
        <h6 class="fw-bold fs--1 text-uppercase text-muted mb-1">Standard Terms & Transit Conditions:</h6>
        <div class="fs--2 text-muted">
            {{ $quotation->terms_and_conditions ?: '1. Quoted rates remain valid until the expiration date indicated above. 2. Detention charges apply after 24 hours of vehicle reporting at destination. 3. Rates are subject to standard carrier liability exclusions.' }}
        </div>
    </div>
    <div class="col-4 text-center">
        <div class="stamp-box mb-1">
            Authorised Signatory / Stamp
        </div>
        <div class="fs--2 text-muted">{{ $quotation->company->name }} Commercial Division</div>
    </div>
</div>
@endsection

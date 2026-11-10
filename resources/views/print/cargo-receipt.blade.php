@extends('layouts.print')

@section('title', 'Cargo Receipt - ' . $consignment->consignment_number)

@section('content')
<div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
    <div>
        <h4 class="fw-bold text-primary mb-0">{{ $consignment->company->name }}</h4>
        <div class="text-muted fs--1">TERMINAL WAREHOUSE & CARGO INWARD RECEIPT</div>
    </div>
    <div class="text-end">
        <div class="badge bg-info fs--2 mb-1">GOODS INWARD RECEIPT</div>
        <div class="fs-1 fw-bolder text-primary">RCP-{{ $consignment->consignment_number }}</div>
        <div class="fs--2 text-muted">Receipt Date: {{ now()->format('d M Y') }}</div>
    </div>
</div>

<div class="alert alert-soft-info py-2 fs--1 mb-3">
    This document acknowledges receipt of commercial cargo into warehouse custody pending scheduled dispatch.
</div>

<div class="row g-2 mb-3">
    <div class="col-6">
        <div class="border p-2 rounded bg-light">
            <h6 class="fw-bold fs--2 text-muted mb-1">DEPOSITED BY (CUSTOMER):</h6>
            <div class="fw-bold">{{ $consignment->customer->name }}</div>
            <div class="fs--2 text-muted">{{ $consignment->customer->phone }}</div>
            <div class="fs--2">Pickup Terminal: {{ $consignment->pickup_point }}</div>
        </div>
    </div>
    <div class="col-6">
        <div class="border p-2 rounded bg-light">
            <h6 class="fw-bold fs--2 text-muted mb-1">TARGET CONVEYANCE & DESTINATION:</h6>
            <div class="fw-bold">{{ $consignment->consignee_name }}</div>
            <div class="fs--2 text-muted">{{ $consignment->delivery_point }} ({{ $consignment->consignee_city }})</div>
            <div class="fs--2">Receiver Phone: {{ $consignment->consignee_phone }}</div>
        </div>
    </div>
</div>

<table class="table table-print mb-3 w-100">
    <thead>
        <tr>
            <th>Cargo Description</th>
            <th class="text-center">Packaging</th>
            <th class="text-center">Deposited Quantity</th>
            <th class="text-center">Chargeable Weight</th>
            <th class="text-end">Payment Mode</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="fw-bold">{{ $consignment->cargo_description }}</td>
            <td class="text-center">{{ ucfirst($consignment->packaging_type) }}</td>
            <td class="text-center fs-0 fw-bold text-primary">{{ $consignment->total_quantity }} Units</td>
            <td class="text-center">{{ number_format($consignment->chargeable_weight_kg, 1) }} KG</td>
            <td class="text-end fw-semi-bold">{{ strtoupper($consignment->payment_mode) }}</td>
        </tr>
    </tbody>
</table>

<div class="row g-2 mb-3">
    <div class="col-6">
        <div class="border p-2 rounded text-center" style="min-height: 80px;">
            <div class="fs--2 text-muted mb-4">Depositor / Driver Handover Signature</div>
            <div class="border-top pt-1 fs--2 fw-semi-bold">Cargo Deposited In Good Condition</div>
        </div>
    </div>
    <div class="col-6">
        <div class="border p-2 rounded text-center" style="min-height: 80px;">
            <div class="fs--2 text-muted mb-4">Terminal Warehouse Officer</div>
            <div class="border-top pt-1 fs--2 fw-semi-bold">Custody Acknowledged & Received</div>
        </div>
    </div>
</div>
@endsection


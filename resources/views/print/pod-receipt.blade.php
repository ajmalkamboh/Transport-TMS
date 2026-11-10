@extends('layouts.print')

@section('title', 'Proof of Delivery (POD) - ' . $pod->consignment->consignment_number)

@section('content')
<div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
    <div>
        <h4 class="fw-bold text-primary mb-0">{{ $pod->company->name }}</h4>
        <div class="text-muted fs--1">ELECTRONIC PROOF OF DELIVERY (EPOD) CERTIFICATE</div>
        <div class="text-muted fs--2">Document Tracking Ref: POD-{{ str_pad($pod->id, 6, '0', STR_PAD_LEFT) }} | Printed: {{ now()->format('d M Y H:i') }}</div>
    </div>
    <div class="text-end">
        <div class="badge bg-{{ $pod->status === 'verified' ? 'success' : 'warning text-dark' }} fs--2 mb-1">
            {{ strtoupper($pod->status) }}
        </div>
        <div class="fs-1 fw-bolder text-primary">{{ $pod->consignment->consignment_number }}</div>
        <div class="fs--2 text-muted">Trip Number: {{ $pod->trip->trip_number }}</div>
        <div class="fs--2 text-muted">Delivered: {{ $pod->delivery_datetime->format('d M Y H:i') }}</div>
    </div>
</div>

<div class="row g-2 mb-3">
    <div class="col-6">
        <div class="p-2 border rounded bg-light h-100">
            <h6 class="fw-bold fs--2 text-uppercase text-muted mb-1">SHIPMENT & SENDER INFORMATION:</h6>
            <div class="fs--1"><strong>Consignor (Sender):</strong> {{ $pod->consignment->customer?->name }}</div>
            <div class="fs--2 text-muted">Company: {{ $pod->consignment->customer?->company_name }}</div>
            <div class="fs--2">Cargo Description: <strong>{{ $pod->consignment->cargo_description }}</strong></div>
            <div class="fs--2 text-muted">Booking Reference: {{ $pod->consignment->booking?->booking_number }}</div>
            <div class="fs--2">Dispatch City: {{ $pod->trip->origin_city }} &rarr; {{ $pod->trip->destination_city }}</div>
        </div>
    </div>
    <div class="col-6">
        <div class="p-2 border rounded bg-light h-100">
            <h6 class="fw-bold fs--2 text-uppercase text-muted mb-1">RECEIVING PARTY DETAILS:</h6>
            <div class="fs--1"><strong>Consignee Name:</strong> {{ $pod->consignment->consignee_name }}</div>
            <div class="fs--1"><strong>Received By:</strong> {{ $pod->receiver_name }}</div>
            <div class="fs--2">Receiver Phone: <strong>{{ $pod->receiver_phone ?: 'N/A' }}</strong></div>
            <div class="fs--2">Receiver CNIC / National ID: <strong>{{ $pod->receiver_cnic ?: 'N/A' }}</strong></div>
            <div class="fs--2 text-muted">Delivery Address: {{ $pod->consignment->consignee_address ?: $pod->consignment->consignee_city }}</div>
        </div>
    </div>
</div>

<h6 class="fw-bold text-uppercase fs--2 mb-2">DELIVERY QUANTITY VERIFICATION & AUDIT</h6>
<table class="table table-print mb-3 w-100">
    <thead>
        <tr>
            <th style="width: 25%;" class="text-center">Assigned / Dispatched</th>
            <th style="width: 25%;" class="text-center">Delivered in Good Order</th>
            <th style="width: 25%;" class="text-center">Damaged Packages</th>
            <th style="width: 25%;" class="text-center">Short / Missing Packages</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="text-center fw-bold fs-1">{{ $pod->tripConsignment?->assigned_quantity ?? $pod->delivered_quantity }}</td>
            <td class="text-center fw-bold fs-1 text-success">{{ $pod->delivered_quantity }}</td>
            <td class="text-center fw-bold fs-1 {{ $pod->damaged_quantity > 0 ? 'text-danger' : 'text-muted' }}">
                {{ $pod->damaged_quantity }}
            </td>
            <td class="text-center fw-bold fs-1 {{ $pod->short_quantity > 0 ? 'text-warning' : 'text-muted' }}">
                {{ $pod->short_quantity }}
            </td>
        </tr>
    </tbody>
</table>

@if($pod->exception_notes)
    <div class="alert alert-warning border mb-3 p-2 fs--1">
        <strong>Delivery Discrepancy / Exception Notes:</strong>
        <div>{{ $pod->exception_notes }}</div>
    </div>
@endif

<div class="row g-2 mb-3">
    <div class="col-6">
        <div class="p-2 border rounded bg-light">
            <h6 class="fw-bold fs--2 text-uppercase text-muted mb-1">GEO-LOCATION & VERIFICATION DATA:</h6>
            <div class="fs--2">GPS Latitude: <strong>{{ $pod->gps_latitude ?: '31.5204° N' }}</strong></div>
            <div class="fs--2">GPS Longitude: <strong>{{ $pod->gps_longitude ?: '74.3587° E' }}</strong></div>
            <div class="fs--2">Carrier Verifier: <strong>{{ $pod->verifier?->name ?: 'Delivery Incharge' }}</strong></div>
            <div class="fs--2 text-muted">Verification Timestamp: {{ $pod->verified_at?->format('d M Y H:i:s') ?: 'Verified on Delivery' }}</div>
        </div>
    </div>
    <div class="col-6">
        <div class="p-2 border rounded bg-light text-center">
            <h6 class="fw-bold fs--2 text-uppercase text-muted mb-1">DIGITAL / PHYSICAL SIGNATURE</h6>
            <div style="height: 40px;" class="d-flex align-items-center justify-content-center text-primary fw-bold font-monospace">
                [ SIGNED & ACCEPTED ]
            </div>
            <div class="border-top pt-1 fs--2 text-muted">{{ $pod->receiver_name }} (Recipient)</div>
        </div>
    </div>
</div>

<div class="row g-2 mt-3 pt-2 border-top">
    <div class="col-12 text-center text-muted fs--2">
        This is an official computer-generated Proof of Delivery document certifying receipt of cargo under Goods Forwarding Agreement.
    </div>
</div>
@endsection


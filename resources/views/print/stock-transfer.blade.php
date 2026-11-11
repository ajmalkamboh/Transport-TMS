@extends('layouts.print')

@section('title', 'Stock Transfer - ' . $transfer->transfer_number)

@section('content')
<div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
    <div>
        <h4 class="fw-bold text-primary mb-0">{{ $transfer->company->name }}</h4>
        <div class="text-muted fs--1">INTER-WAREHOUSE STOCK TRANSFER SLIP</div>
        <div class="text-muted fs--2">Date: {{ $transfer->transfer_date->format('d M Y') }}</div>
    </div>
    <div class="text-end">
        <div class="badge bg-primary fs--2 mb-1">
            {{ strtoupper($transfer->status) }}
        </div>
        <div class="fs-1 fw-bolder text-primary">{{ $transfer->transfer_number }}</div>
    </div>
</div>

<div class="row g-3 mb-3 fs--1">
    <div class="col-6">
        <div class="p-2 border rounded bg-light">
            <strong class="d-block text-uppercase text-muted fs--2">Origin / Dispatching Warehouse:</strong>
            <div class="fw-bold text-dark">{{ $transfer->fromWarehouse?->name }} ({{ $transfer->fromWarehouse?->code }})</div>
            <div>Branch: {{ $transfer->fromWarehouse?->branch?->name ?? 'Head Office' }}</div>
            <div>Dispatched By: {{ $transfer->dispatcher?->name ?? 'Store Keeper' }}</div>
        </div>
    </div>
    <div class="col-6">
        <div class="p-2 border rounded bg-light">
            <strong class="d-block text-uppercase text-muted fs--2">Destination / Receiving Warehouse:</strong>
            <div class="fw-bold text-dark">{{ $transfer->toWarehouse?->name }} ({{ $transfer->toWarehouse?->code }})</div>
            <div>Branch: {{ $transfer->toWarehouse?->branch?->name ?? 'Head Office' }}</div>
            <div>Received By: {{ $transfer->receiver?->name ?? 'Store Keeper' }}</div>
        </div>
    </div>
</div>

<table class="table table-print mb-3 w-100">
    <thead>
        <tr>
            <th style="width: 5%;">#</th>
            <th style="width: 15%;">Item Code</th>
            <th style="width: 45%;">Item Name</th>
            <th style="width: 15%;" class="text-center">UOM</th>
            <th style="width: 20%;" class="text-end">Quantity Transferred</th>
        </tr>
    </thead>
    <tbody>
        @foreach($transfer->items as $idx => $item)
            <tr>
                <td class="text-center">{{ $idx + 1 }}</td>
                <td class="font-monospace fw-bold">{{ $item->item?->item_code }}</td>
                <td>{{ $item->item?->name }}</td>
                <td class="text-center">{{ $item->item?->primaryUom?->code }}</td>
                <td class="text-end font-monospace fw-bold">{{ number_format($item->quantity_transferred, 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

@if($transfer->notes)
    <div class="p-2 border rounded bg-light mb-4 fs--2 text-muted">
        <strong>Notes:</strong> {{ $transfer->notes }}
    </div>
@endif

<div class="row text-center mt-5 pt-4">
    <div class="col-4">
        <div class="border-top pt-2 fs--1 text-muted">Dispatched By</div>
    </div>
    <div class="col-4">
        <div class="border-top pt-2 fs--1 text-muted">Transit Carrier</div>
    </div>
    <div class="col-4">
        <div class="border-top pt-2 fs--1 text-muted">Received By</div>
    </div>
</div>
@endsection


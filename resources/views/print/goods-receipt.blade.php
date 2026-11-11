@extends('layouts.print')

@section('title', 'Goods Receipt Note - ' . $grn->grn_number)

@section('content')
<div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
    <div>
        <h4 class="fw-bold text-primary mb-0">{{ $grn->company->name }}</h4>
        <div class="text-muted fs--1">GOODS RECEIPT NOTE (GRN)</div>
        <div class="text-muted fs--2">{{ $grn->branch?->name ?: 'Central Operations' }} | Received Date: {{ $grn->received_date->format('d M Y') }}</div>
    </div>
    <div class="text-end">
        <div class="badge bg-success fs--2 mb-1">
            {{ strtoupper($grn->status) }}
        </div>
        <div class="fs-1 fw-bolder text-primary">{{ $grn->grn_number }}</div>
        @if($grn->purchaseOrder)
            <div class="fs--2 text-muted">Against PO: {{ $grn->purchaseOrder->po_number }}</div>
        @endif
    </div>
</div>

<div class="row g-3 mb-3 fs--1">
    <div class="col-6">
        <div class="p-2 border rounded bg-light">
            <strong class="d-block text-uppercase text-muted fs--2">Supplier:</strong>
            <div class="fw-bold text-dark">{{ $grn->supplier?->name }}</div>
            <div>Delivery Challan #: {{ $grn->supplier_challan_number ?? 'N/A' }}</div>
            <div>Contact: {{ $grn->supplier?->phone }}</div>
        </div>
    </div>
    <div class="col-6">
        <div class="p-2 border rounded bg-light">
            <strong class="d-block text-uppercase text-muted fs--2">Receiving Warehouse:</strong>
            <div class="fw-bold text-dark">{{ $grn->warehouse?->name }} ({{ $grn->warehouse?->code }})</div>
            <div>Branch: {{ $grn->warehouse?->branch?->name ?? 'Head Office' }}</div>
            <div>Received By: {{ $grn->receiver?->name ?? 'Store In-Charge' }}</div>
        </div>
    </div>
</div>

<table class="table table-print mb-3 w-100">
    <thead>
        <tr>
            <th style="width: 5%;">#</th>
            <th style="width: 15%;">Item Code</th>
            <th style="width: 35%;">Item Name</th>
            <th style="width: 10%;" class="text-center">UOM</th>
            <th style="width: 15%;" class="text-end">Qty Received</th>
            <th style="width: 10%;" class="text-center">Condition</th>
            <th style="width: 10%;" class="text-end">Unit Rate (PKR)</th>
        </tr>
    </thead>
    <tbody>
        @foreach($grn->items as $idx => $item)
            <tr>
                <td class="text-center">{{ $idx + 1 }}</td>
                <td class="font-monospace fw-bold">{{ $item->item?->item_code }}</td>
                <td>{{ $item->item?->name }}</td>
                <td class="text-center">{{ $item->item?->primaryUom?->code }}</td>
                <td class="text-end font-monospace fw-bold">{{ number_format($item->quantity_received, 2) }}</td>
                <td class="text-center"><span class="badge bg-success">{{ strtoupper($item->condition_status) }}</span></td>
                <td class="text-end font-monospace">{{ number_format($item->unit_rate, 2) }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr class="fw-bold fs-6 border-top border-2">
            <td colspan="6" class="text-end text-uppercase">Total Received Value:</td>
            <td class="text-end font-monospace text-primary">PKR {{ number_format($grn->total_received_amount, 2) }}</td>
        </tr>
    </tfoot>
</table>

<div class="row text-center mt-5 pt-4">
    <div class="col-4">
        <div class="border-top pt-2 fs--1 text-muted">Delivery Driver (Supplier)</div>
    </div>
    <div class="col-4">
        <div class="border-top pt-2 fs--1 text-muted">Inspection Technician</div>
    </div>
    <div class="col-4">
        <div class="border-top pt-2 fs--1 text-muted">Store In-Charge (Warehouse)</div>
    </div>
</div>
@endsection


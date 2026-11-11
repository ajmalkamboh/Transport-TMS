@extends('layouts.print')

@section('title', 'Purchase Order - ' . $order->po_number)

@section('content')
<div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
    <div>
        <h4 class="fw-bold text-primary mb-0">{{ $order->company->name }}</h4>
        <div class="text-muted fs--1">PURCHASE ORDER</div>
        <div class="text-muted fs--2">{{ $order->branch?->name ?: 'Central Operations' }} | Order Date: {{ $order->order_date->format('d M Y') }}</div>
    </div>
    <div class="text-end">
        <div class="badge bg-primary fs--2 mb-1">
            {{ strtoupper($order->status) }}
        </div>
        <div class="fs-1 fw-bolder text-primary">{{ $order->po_number }}</div>
        @if($order->expected_delivery_date)
            <div class="fs--2 text-muted">Delivery By: {{ $order->expected_delivery_date->format('d M Y') }}</div>
        @endif
    </div>
</div>

<div class="row g-3 mb-3 fs--1">
    <div class="col-6">
        <div class="p-2 border rounded bg-light">
            <strong class="d-block text-uppercase text-muted fs--2">Vendor / Supplier:</strong>
            <div class="fw-bold text-dark">{{ $order->supplier?->name }}</div>
            <div>Contact: {{ $order->supplier?->contact_name ?? 'Vendor Rep' }} ({{ $order->supplier?->phone }})</div>
            <div>NTN/Tax #: {{ $order->supplier?->tax_number ?? 'N/A' }}</div>
            <div>{{ $order->supplier?->address }}</div>
        </div>
    </div>
    <div class="col-6">
        <div class="p-2 border rounded bg-light">
            <strong class="d-block text-uppercase text-muted fs--2">Ship To / Destination Warehouse:</strong>
            <div class="fw-bold text-dark">{{ $order->warehouse?->name }} ({{ $order->warehouse?->code }})</div>
            <div>Branch: {{ $order->warehouse?->branch?->name ?? 'Head Office' }}</div>
            <div>Store In-Charge: {{ $order->warehouse?->contact_person }} ({{ $order->warehouse?->phone }})</div>
            <div>{{ $order->warehouse?->address }}</div>
        </div>
    </div>
</div>

<table class="table table-print mb-3 w-100">
    <thead>
        <tr>
            <th style="width: 5%;">#</th>
            <th style="width: 15%;">Item Code</th>
            <th style="width: 40%;">Description</th>
            <th style="width: 10%;" class="text-center">UOM</th>
            <th style="width: 10%;" class="text-end">Qty Ordered</th>
            <th style="width: 10%;" class="text-end">Unit Rate (PKR)</th>
            <th style="width: 10%;" class="text-end">Total Amount (PKR)</th>
        </tr>
    </thead>
    <tbody>
        @foreach($order->items as $idx => $item)
            <tr>
                <td class="text-center">{{ $idx + 1 }}</td>
                <td class="font-monospace fw-bold">{{ $item->item?->item_code }}</td>
                <td>{{ $item->item?->name }}</td>
                <td class="text-center">{{ $item->item?->primaryUom?->code }}</td>
                <td class="text-end font-monospace">{{ number_format($item->quantity_ordered, 2) }}</td>
                <td class="text-end font-monospace">{{ number_format($item->unit_rate, 2) }}</td>
                <td class="text-end font-monospace fw-bold">{{ number_format($item->total_amount, 2) }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr class="fw-bold fs-6 border-top border-2">
            <td colspan="6" class="text-end text-uppercase">Grand Total:</td>
            <td class="text-end font-monospace text-primary">PKR {{ number_format($order->total_amount, 2) }}</td>
        </tr>
    </tfoot>
</table>

@if($order->notes)
    <div class="p-2 border rounded bg-light mb-4 fs--2 text-muted">
        <strong>Terms & Instructions:</strong> {{ $order->notes }}
    </div>
@endif

<div class="row text-center mt-5 pt-4">
    <div class="col-4">
        <div class="border-top pt-2 fs--1 text-muted">Prepared By</div>
    </div>
    <div class="col-4">
        <div class="border-top pt-2 fs--1 text-muted">Store Manager</div>
    </div>
    <div class="col-4">
        <div class="border-top pt-2 fs--1 text-muted">Authorized Signatory</div>
    </div>
</div>
@endsection

@extends('layouts.print')

@section('title', 'Freight Invoice - ' . $invoice->invoice_number)

@section('content')
<div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
    <div>
        <h4 class="fw-bold text-primary mb-0">{{ $invoice->company->name }}</h4>
        <div class="text-muted fs--1">FREIGHT TRANSPORTATION TAX INVOICE</div>
        <div class="text-muted fs--2">{{ $invoice->branch?->name ?: 'Central Operations' }} | Date: {{ $invoice->invoice_date->format('d M Y') }}</div>
    </div>
    <div class="text-end">
        <div class="badge bg-{{ $invoice->isPosted() ? 'success' : 'secondary' }} fs--2 mb-1">
            {{ strtoupper($invoice->payment_status) }}
        </div>
        <div class="fs-1 fw-bolder text-primary">{{ $invoice->invoice_number }}</div>
        <div class="fs--2 text-muted">Due Date: {{ $invoice->due_date->format('d M Y') }}</div>
    </div>
</div>

<div class="row g-2 mb-3">
    <div class="col-6">
        <div class="p-2 border rounded bg-light h-100">
            <h6 class="fw-bold fs--2 text-uppercase text-muted mb-1">BILLED TO (CUSTOMER):</h6>
            <div class="fs--1"><strong>{{ $invoice->customer?->name }}</strong></div>
            <div class="fs--2 text-muted">{{ $invoice->customer?->billing_address ?: 'Commercial Client' }}</div>
            <div class="fs--2 text-muted">NTN / Tax ID: {{ $invoice->customer?->tax_number ?: 'N/A' }}</div>
            <div class="fs--2 text-muted">Phone: {{ $invoice->customer?->phone }} | Email: {{ $invoice->customer?->email }}</div>
        </div>
    </div>
    <div class="col-6">
        <div class="p-2 border rounded bg-light h-100">
            <h6 class="fw-bold fs--2 text-uppercase text-muted mb-1">INVOICE & TRIP REFERENCE:</h6>
            <div class="fs--1"><strong>Invoice Number:</strong> {{ $invoice->invoice_number }}</div>
            @if ($invoice->trip)
                <div class="fs--2 text-muted">Trip #: {{ $invoice->trip->trip_number }} (Vehicle: {{ $invoice->trip->vehicle?->registration_number }})</div>
            @endif
            <div class="fs--2 text-muted">Status: {{ strtoupper($invoice->status) }}</div>
            @if($invoice->voucher)
                <div class="fs--2 text-muted">General Ledger Ref: {{ $invoice->voucher->voucher_number }}</div>
            @endif
        </div>
    </div>
</div>

<table class="table table-print mb-3 w-100">
    <thead>
        <tr>
            <th style="width: 5%;">#</th>
            <th style="width: 55%;">Description</th>
            <th style="width: 12%;" class="text-end">Quantity</th>
            <th style="width: 13%;" class="text-end">Unit Rate</th>
            <th style="width: 15%;" class="text-end">Amount (PKR)</th>
        </tr>
    </thead>
    <tbody>
        @foreach($invoice->items as $idx => $item)
            <tr>
                <td class="text-center">{{ $idx + 1 }}</td>
                <td>{{ $item->description }}</td>
                <td class="text-end font-monospace">{{ number_format($item->quantity, 2) }}</td>
                <td class="text-end font-monospace">{{ number_format($item->unit_rate, 2) }}</td>
                <td class="text-end font-monospace fw-semibold">{{ number_format($item->amount, 2) }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="4" class="text-end text-uppercase fw-bold">Subtotal:</td>
            <td class="text-end font-monospace fw-bold">{{ number_format($invoice->subtotal, 2) }}</td>
        </tr>
        @if((float)$invoice->tax_amount > 0)
            <tr>
                <td colspan="4" class="text-end text-uppercase">Sales Tax / VAT ({{ $invoice->tax_rate_percent }}%):</td>
                <td class="text-end font-monospace">{{ number_format($invoice->tax_amount, 2) }}</td>
            </tr>
        @endif
        @if((float)$invoice->discount_amount > 0)
            <tr>
                <td colspan="4" class="text-end text-uppercase">Discount:</td>
                <td class="text-end font-monospace">-{{ number_format($invoice->discount_amount, 2) }}</td>
            </tr>
        @endif
        <tr class="fw-bold fs-6 border-top border-2">
            <td colspan="4" class="text-end text-uppercase text-primary">Total Amount Due:</td>
            <td class="text-end font-monospace text-primary">PKR {{ number_format($invoice->total_amount, 2) }}</td>
        </tr>
        <tr>
            <td colspan="4" class="text-end text-uppercase text-success">Paid Amount:</td>
            <td class="text-end font-monospace text-success">{{ number_format($invoice->paid_amount, 2) }}</td>
        </tr>
        <tr class="fw-bold">
            <td colspan="4" class="text-end text-uppercase text-danger">Balance Due:</td>
            <td class="text-end font-monospace text-danger">PKR {{ number_format($invoice->balance_due, 2) }}</td>
        </tr>
    </tfoot>
</table>

@if($invoice->notes)
    <div class="p-2 border rounded bg-light mb-3 fs--2">
        <strong>Notes / Terms:</strong> {{ $invoice->notes }}
    </div>
@endif

<div class="row text-center mt-5">
    <div class="col-4">
        <div class="border-top pt-1 fs--2">Prepared By</div>
    </div>
    <div class="col-4">
        <div class="border-top pt-1 fs--2">Accounts Manager</div>
    </div>
    <div class="col-4">
        <div class="border-top pt-1 fs--2">Customer Acknowledgment</div>
    </div>
</div>
@endsection


@extends('layouts.print')

@section('title', 'Payment Receipt - ' . $receipt->receipt_number)

@section('content')
<div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
    <div>
        <h4 class="fw-bold text-primary mb-0">{{ $receipt->company->name }}</h4>
        <div class="text-muted fs--1">MONEY COLLECTION & PAYMENT RECEIPT</div>
        <div class="text-muted fs--2">{{ $receipt->branch?->name ?: 'Central Operations' }} | Date: {{ $receipt->receipt_date->format('d M Y') }}</div>
    </div>
    <div class="text-end">
        <div class="badge bg-success fs--2 mb-1">
            {{ strtoupper($receipt->status) }}
        </div>
        <div class="fs-1 fw-bolder text-primary">{{ $receipt->receipt_number }}</div>
        <div class="fs--2 text-muted">Type: {{ strtoupper(str_replace('_', ' ', $receipt->payment_type)) }}</div>
    </div>
</div>

<div class="row g-2 mb-3">
    <div class="col-6">
        <div class="p-2 border rounded bg-light h-100">
            <h6 class="fw-bold fs--2 text-uppercase text-muted mb-1">RECEIVED FROM:</h6>
            <div class="fs--1"><strong>{{ $receipt->customer?->name }}</strong></div>
            <div class="fs--2 text-muted">{{ $receipt->customer?->billing_address ?: 'Commercial Client' }}</div>
            <div class="fs--2 text-muted">Phone: {{ $receipt->customer?->phone }}</div>
        </div>
    </div>
    <div class="col-6">
        <div class="p-2 border rounded bg-light h-100">
            <h6 class="fw-bold fs--2 text-uppercase text-muted mb-1">PAYMENT DETAILS:</h6>
            <div class="fs--1"><strong>Payment Method:</strong> {{ strtoupper(str_replace('_', ' ', $receipt->payment_method)) }}</div>
            @if($receipt->reference_number)
                <div class="fs--2 text-muted">Transaction Ref: {{ $receipt->reference_number }}</div>
            @endif
            @if($receipt->cheque_number)
                <div class="fs--2 text-muted">Cheque #: {{ $receipt->cheque_number }} ({{ $receipt->bank_name }})</div>
            @endif
            @if($receipt->voucher)
                <div class="fs--2 text-muted">GL Voucher: {{ $receipt->voucher->voucher_number }}</div>
            @endif
        </div>
    </div>
</div>

<div class="card border border-2 border-success bg-success-subtle p-3 text-center my-4">
    <span class="fs--1 text-uppercase fw-semibold text-muted">Amount Received</span>
    <h2 class="fw-bold text-success font-monospace mb-0">PKR {{ number_format($receipt->amount, 2) }}</h2>
</div>

@if($receipt->invoice)
    <div class="p-2 border rounded bg-light mb-3 fs--1">
        <strong>Applied Against:</strong> Invoice # {{ $receipt->invoice->invoice_number }} (Dated: {{ $receipt->invoice->invoice_date->format('d M Y') }})
    </div>
@elseif($receipt->isAdvance())
    <div class="p-2 border rounded bg-warning-subtle mb-3 fs--1 text-warning-emphasis">
        <strong>Customer Advance:</strong> Held as liability account [2200-CUSTOMER-ADVANCES]. Unapplied balance available: PKR {{ number_format($receipt->unapplied_amount, 2) }}.
    </div>
@endif

<div class="row text-center mt-5">
    <div class="col-6">
        <div class="border-top pt-1 fs--2">Cashier / Receiver Signature</div>
    </div>
    <div class="col-6">
        <div class="border-top pt-1 fs--2">Customer / Payer Signature</div>
    </div>
</div>
@endsection

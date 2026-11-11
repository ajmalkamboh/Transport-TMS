@extends('layouts.print')

@section('title', 'Payment Receipt - ' . $payment->payment_number)

@section('content')
<div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
    <div>
        <h4 class="fw-bold text-success mb-0">{{ $payment->company->name }}</h4>
        <div class="text-muted fs--1">VEHICLE OWNER PAYMENT RECEIPT / VOUCHER</div>
        <div class="text-muted fs--2">{{ $payment->branch?->name ?: 'Central Operations' }} | Payment Date: {{ $payment->payment_date->format('d M Y') }}</div>
    </div>
    <div class="text-end">
        <div class="badge bg-success fs--2 mb-1">
            {{ strtoupper($payment->status) }}
        </div>
        <div class="fs-1 fw-bolder text-success">{{ $payment->payment_number }}</div>
        <div class="fs--2 text-muted">Method: {{ strtoupper(str_replace('_', ' ', $payment->payment_method)) }}</div>
    </div>
</div>

<div class="row g-2 mb-3">
    <div class="col-6">
        <div class="p-2 border rounded bg-light h-100">
            <h6 class="fw-bold fs--2 text-uppercase text-muted mb-1">BENEFICIARY DETAILS:</h6>
            <div class="fs--1"><strong>Owner Name:</strong> {{ $payment->owner?->name }}</div>
            <div class="fs--2 text-muted">Business: {{ $payment->owner?->company_name ?: 'Third-Party Truck Owner' }}</div>
            <div class="fs--2 text-muted">Phone: {{ $payment->owner?->phone }} | CNIC: {{ $payment->owner?->cnic_number ?: 'N/A' }}</div>
            <div class="fs--2 text-muted">Bank: {{ $payment->bank_name ?: $payment->owner?->bank_name }}</div>
            <div class="fs--2 text-muted">A/C: {{ $payment->bank_account_number ?: $payment->owner?->bank_account_number }}</div>
        </div>
    </div>
    <div class="col-6">
        <div class="p-2 border rounded bg-light h-100">
            <h6 class="fw-bold fs--2 text-uppercase text-muted mb-1">TRANSACTION DETAILS:</h6>
            <div class="fs--1"><strong>Payment Method:</strong> {{ strtoupper(str_replace('_', ' ', $payment->payment_method)) }}</div>
            @if ($payment->cheque_number)
                <div class="fs--2 text-muted">Cheque Number: {{ $payment->cheque_number }} (Date: {{ $payment->cheque_date?->format('d M Y') }})</div>
            @endif
            @if ($payment->payment_reference)
                <div class="fs--2 text-muted">Bank Reference / FT: {{ $payment->payment_reference }}</div>
            @endif
            <div class="fs--2 text-muted">Settlement Ref: {{ $payment->settlement?->settlement_number ?: 'On-Account Payout' }}</div>
            <div class="fs--2 text-muted">Recorded By: {{ $payment->createdByUser?->name ?: 'Accountant' }}</div>
        </div>
    </div>
</div>

<table class="table table-print mb-4 w-100">
    <thead>
        <tr>
            <th style="width: 10%;">#</th>
            <th style="width: 60%;">Description</th>
            <th style="width: 30%;" class="text-end">Amount Paid (PKR)</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="text-center">1</td>
            <td>
                <div class="fw-bold fs-0">Disbursement to Vehicle Owner {{ $payment->owner?->name }}</div>
                <div class="fs--2 text-muted">
                    @if ($payment->settlement)
                        Full/Partial freight payment for Settlement {{ $payment->settlement->settlement_number }} (Trip: {{ $payment->settlement->trip?->trip_number }}).
                    @else
                        Direct on-account payout toward owner running balance.
                    @endif
                </div>
            </td>
            <td class="text-end fw-bold fs-1 text-success">
                PKR {{ number_format($payment->amount, 2) }}
            </td>
        </tr>
    </tbody>
</table>

<div class="p-3 bg-light rounded border mb-4">
    <div class="fs--1"><strong>Remaining Outstanding Balance:</strong> PKR {{ number_format($payment->owner?->getCurrentBalance(), 2) }}</div>
    <div class="fs--2 text-muted">Amount debited from accounts payable ledger in favor of beneficiary.</div>
</div>

<!-- Signatures -->
<div class="row mt-5 pt-3 text-center border-top">
    <div class="col-6">
        <div class="border-top pt-2 mx-5">
            <div class="fw-bold fs--1">{{ $payment->createdByUser?->name ?: 'Accounts Officer' }}</div>
            <div class="fs--2 text-muted">Cashier / Disbursing Officer</div>
        </div>
    </div>
    <div class="col-6">
        <div class="border-top pt-2 mx-5">
            <div class="fw-bold fs--1">{{ $payment->owner?->name }}</div>
            <div class="fs--2 text-muted">Receiver / Owner Signature</div>
        </div>
    </div>
</div>
@endsection


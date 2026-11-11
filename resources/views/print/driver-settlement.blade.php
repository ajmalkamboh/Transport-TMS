@extends('layouts.print')

@section('title', 'Driver Settlement - ' . $settlement->settlement_number)

@section('content')
<div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
    <div>
        <h4 class="fw-bold text-primary mb-0">{{ $settlement->company->name }}</h4>
        <div class="text-muted fs--1">DRIVER VOYAGE SETTLEMENT SHEET</div>
        <div class="text-muted fs--2">{{ $settlement->branch?->name ?: 'Central Operations' }} | Date: {{ $settlement->settlement_date->format('d M Y') }}</div>
    </div>
    <div class="text-end">
        <div class="badge bg-{{ $settlement->status === 'posted' ? 'success' : 'secondary' }} fs--2 mb-1">
            {{ strtoupper($settlement->status) }}
        </div>
        <div class="fs-1 fw-bolder text-primary">{{ $settlement->settlement_number }}</div>
    </div>
</div>

<div class="row g-2 mb-3">
    <div class="col-6">
        <div class="p-2 border rounded bg-light h-100">
            <h6 class="fw-bold fs--2 text-uppercase text-muted mb-1">DRIVER PARTICULARS:</h6>
            <div class="fs--1"><strong>{{ $settlement->driver?->name }}</strong></div>
            <div class="fs--2 text-muted">CNIC: {{ $settlement->driver?->cnic_number ?: 'N/A' }} | License: {{ $settlement->driver?->license_number }}</div>
            <div class="fs--2 text-muted">Phone: {{ $settlement->driver?->phone }}</div>
        </div>
    </div>
    <div class="col-6">
        <div class="p-2 border rounded bg-light h-100">
            <h6 class="fw-bold fs--2 text-uppercase text-muted mb-1">VOYAGE DETAILS:</h6>
            <div class="fs--1"><strong>Trip Reference:</strong> {{ $settlement->trip?->trip_number ?: 'General Voyage Duty' }}</div>
            @if($settlement->voucher)
                <div class="fs--2 text-muted">GL Voucher Ref: {{ $settlement->voucher->voucher_number }}</div>
            @endif
        </div>
    </div>
</div>

<table class="table table-print mb-3 w-100">
    <thead>
        <tr>
            <th style="width: 10%;">#</th>
            <th style="width: 60%;">Settlement Component</th>
            <th style="width: 30%;" class="text-end">Amount (PKR)</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="text-center">1</td>
            <td>Trip Duty Allowance (Voyage earnings)</td>
            <td class="text-end font-monospace">{{ number_format($settlement->driver_allowance, 2) }}</td>
        </tr>
        <tr>
            <td class="text-center">2</td>
            <td>Out-of-Pocket Route Expenses Borne by Driver</td>
            <td class="text-end font-monospace">{{ number_format($settlement->out_of_pocket_expenses, 2) }}</td>
        </tr>
        <tr class="fw-bold">
            <td colspan="2" class="text-end">Gross Driver Credits:</td>
            <td class="text-end font-monospace">{{ number_format((float)$settlement->driver_allowance + (float)$settlement->out_of_pocket_expenses, 2) }}</td>
        </tr>
        <tr>
            <td class="text-center">3</td>
            <td>Less: Voyage Advances Disbursed (Advance Clearing)</td>
            <td class="text-end font-monospace text-danger">-{{ number_format($settlement->total_advances, 2) }}</td>
        </tr>
    </tbody>
    <tfoot>
        @if((float)$settlement->net_payable > 0)
            <tr class="fw-bold fs-6 border-top border-2">
                <td colspan="2" class="text-end text-uppercase text-success">Net Amount Payable to Driver:</td>
                <td class="text-end font-monospace text-success">PKR {{ number_format($settlement->net_payable, 2) }}</td>
            </tr>
        @elseif((float)$settlement->net_recoverable > 0)
            <tr class="fw-bold fs-6 border-top border-2">
                <td colspan="2" class="text-end text-uppercase text-danger">Net Amount Recoverable from Driver:</td>
                <td class="text-end font-monospace text-danger">PKR {{ number_format($settlement->net_recoverable, 2) }}</td>
            </tr>
        @else
            <tr class="fw-bold fs-6 border-top border-2">
                <td colspan="2" class="text-end text-uppercase text-muted">Net Settlement Balance:</td>
                <td class="text-end font-monospace text-muted">PKR 0.00 (Balanced)</td>
            </tr>
        @endif
    </tfoot>
</table>

<div class="row text-center mt-5">
    <div class="col-6">
        <div class="border-top pt-1 fs--2">Driver Signature / Acknowledgment</div>
    </div>
    <div class="col-6">
        <div class="border-top pt-1 fs--2">Terminal Manager / Dispatcher Signature</div>
    </div>
</div>
@endsection

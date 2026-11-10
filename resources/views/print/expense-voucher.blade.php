@extends('layouts.print')

@section('title', 'Expense Voucher - ' . $expense->voucher_number)

@section('content')
<div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
    <div>
        <h4 class="fw-bold text-primary mb-0">{{ $expense->company->name }}</h4>
        <div class="text-muted fs--1">FINANCE & OPERATIONAL DISBURSEMENT VOUCHER</div>
        <div class="text-muted fs--2">{{ $expense->branch?->name ?: 'Central Operations' }} | Date: {{ $expense->expense_date->format('d M Y') }}</div>
    </div>
    <div class="text-end">
        <div class="badge bg-{{ $expense->status === 'posted' ? 'dark' : ($expense->status === 'approved' ? 'success' : 'warning text-dark') }} fs--2 mb-1">
            {{ strtoupper($expense->status) }}
        </div>
        <div class="fs-1 fw-bolder text-primary">{{ $expense->voucher_number }}</div>
        <div class="fs--2 text-muted">Category: {{ strtoupper(str_replace('_', ' ', $expense->expense_category)) }}</div>
    </div>
</div>

<div class="row g-2 mb-3">
    <div class="col-6">
        <div class="p-2 border rounded bg-light h-100">
            <h6 class="fw-bold fs--2 text-uppercase text-muted mb-1">TRIP & ASSET ALLOCATION:</h6>
            <div class="fs--1"><strong>Trip Reference:</strong> {{ $expense->trip?->trip_number ?: 'General Fleet Expense' }}</div>
            @if($expense->trip)
                <div class="fs--2 text-muted">Route: {{ $expense->trip->origin_city }} &rarr; {{ $expense->trip->destination_city }}</div>
            @endif
            <div class="fs--1 mt-1"><strong>Vehicle:</strong> {{ $expense->vehicle?->registration_number ?: 'N/A' }}</div>
            <div class="fs--1"><strong>Driver / Claimant:</strong> {{ $expense->driver?->name ?: 'Duty Driver' }}</div>
        </div>
    </div>
    <div class="col-6">
        <div class="p-2 border rounded bg-light h-100">
            <h6 class="fw-bold fs--2 text-uppercase text-muted mb-1">PAYMENT & APPROVAL AUDIT:</h6>
            <div class="fs--1"><strong>Payment Mode:</strong> {{ strtoupper(str_replace('_', ' ', $expense->payment_method)) }}</div>
            <div class="fs--2 text-muted">Recorded By: {{ $expense->recorder?->name ?: 'Operator' }}</div>
            <div class="fs--2 text-muted">Approved By: {{ $expense->approver?->name ?: 'Pending Approval' }} {{ $expense->approved_at ? '(' . $expense->approved_at->format('d M H:i') . ')' : '' }}</div>
            <div class="fs--2 text-muted">Posted By: {{ $expense->poster?->name ?: 'Not Posted' }} {{ $expense->posted_at ? '(' . $expense->posted_at->format('d M H:i') . ')' : '' }}</div>
        </div>
    </div>
</div>

<table class="table table-print mb-3 w-100">
    <thead>
        <tr>
            <th style="width: 10%;">#</th>
            <th style="width: 50%;">Expense Category & Description</th>
            <th style="width: 20%;">Payment Source</th>
            <th style="width: 20%;" class="text-end">Amount (PKR)</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="text-center">1</td>
            <td>
                <div class="fw-bold fs-0">{{ strtoupper(str_replace('_', ' ', $expense->expense_category)) }}</div>
                <div class="fs--2 text-muted">{{ $expense->notes ?: 'Authorized operational disbursement for goods transport voyage.' }}</div>
            </td>
            <td>
                <span class="badge bg-secondary fs--2">{{ strtoupper(str_replace('_', ' ', $expense->payment_method)) }}</span>
            </td>
            <td class="text-end fw-bold fs-1 text-success">
                PKR {{ number_format($expense->amount, 2) }}
            </td>
        </tr>
    </tbody>
    <tfoot>
        <tr class="fw-bold bg-light">
            <td colspan="3" class="text-end">NET VOUCHER TOTAL:</td>
            <td class="text-end text-primary fs-1">PKR {{ number_format($expense->amount, 2) }}</td>
        </tr>
    </tfoot>
</table>

<div class="row g-2 mt-4 pt-3 border-top">
    <div class="col-4 text-center">
        <div style="height: 45px;"></div>
        <div class="border-top pt-1 fw-bold fs--1">Claimant / Driver</div>
        <div class="fs--2 text-muted">Received disbursement</div>
    </div>
    <div class="col-4 text-center">
        <div style="height: 45px;"></div>
        <div class="border-top pt-1 fw-bold fs--1">Approving Manager</div>
        <div class="fs--2 text-muted">Verified trip justification</div>
    </div>
    <div class="col-4 text-center">
        <div style="height: 45px;"></div>
        <div class="border-top pt-1 fw-bold fs--1">Accounts & Finance</div>
        <div class="fs--2 text-muted">Posted to general ledger</div>
    </div>
</div>
@endsection

@extends('layouts.print')

@section('title', 'Owner Settlement - ' . $settlement->settlement_number)

@section('content')
<div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
    <div>
        <h4 class="fw-bold text-primary mb-0">{{ $settlement->company->name }}</h4>
        <div class="text-muted fs--1">ATTACHED VEHICLE OWNER FREIGHT SETTLEMENT SHEET</div>
        <div class="text-muted fs--2">{{ $settlement->branch?->name ?: 'Head Office Operations' }} | Settlement Date: {{ $settlement->settlement_date->format('d M Y') }}</div>
    </div>
    <div class="text-end">
        <div class="badge bg-{{ $settlement->status === 'posted' ? 'success' : ($settlement->status === 'paid' ? 'dark' : 'warning text-dark') }} fs--2 mb-1">
            {{ strtoupper($settlement->status) }}
        </div>
        <div class="fs-1 fw-bolder text-primary">{{ $settlement->settlement_number }}</div>
        <div class="fs--2 text-muted">Model: {{ strtoupper(str_replace('_', ' ', $settlement->payment_model)) }}</div>
    </div>
</div>

<div class="row g-2 mb-3">
    <div class="col-6">
        <div class="p-2 border rounded bg-light h-100">
            <h6 class="fw-bold fs--2 text-uppercase text-muted mb-1">VEHICLE OWNER DETAILS:</h6>
            <div class="fs--1"><strong>Owner Name:</strong> {{ $settlement->owner?->name }}</div>
            <div class="fs--2 text-muted">Company / Title: {{ $settlement->owner?->company_name ?: 'Individual Truck Owner' }}</div>
            <div class="fs--2 text-muted">Phone: {{ $settlement->owner?->phone }} | CNIC: {{ $settlement->owner?->cnic_number ?: 'N/A' }}</div>
            <div class="fs--2 text-muted">Bank: {{ $settlement->owner?->bank_name ?: 'N/A' }} (A/C: {{ $settlement->owner?->bank_account_number ?: 'N/A' }})</div>
        </div>
    </div>
    <div class="col-6">
        <div class="p-2 border rounded bg-light h-100">
            <h6 class="fw-bold fs--2 text-uppercase text-muted mb-1">VOYAGE & VEHICLE ALLOCATION:</h6>
            <div class="fs--1"><strong>Vehicle:</strong> {{ $settlement->vehicle?->registration_number }} ({{ $settlement->vehicle?->make }} {{ $settlement->vehicle?->model }})</div>
            <div class="fs--1"><strong>Trip Reference:</strong> {{ $settlement->trip?->trip_number ?: 'Periodic Settlement' }}</div>
            @if ($settlement->trip)
                <div class="fs--2 text-muted">Route: {{ $settlement->trip->origin_city }} &rarr; {{ $settlement->trip->destination_city }} ({{ $settlement->trip->total_distance_km }} KM)</div>
            @endif
            <div class="fs--2 text-muted">Contract Ref: {{ $settlement->contract?->contract_number ?: 'Direct Agreemnt' }}</div>
        </div>
    </div>
</div>

<!-- Commercial Breakdown Table -->
<table class="table table-print mb-3 w-100">
    <thead>
        <tr>
            <th style="width: 10%;">#</th>
            <th style="width: 50%;">Description / Commercial Item</th>
            <th style="width: 20%;" class="text-end">Calculation Base</th>
            <th style="width: 20%;" class="text-end">Amount (PKR)</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="text-center">1</td>
            <td>
                <div class="fw-bold">Agreed Freight Payable (Gross)</div>
                <div class="fs--2 text-muted">{{ $settlement->calculation_details['formula'] ?? 'Agreed commercial rate' }}</div>
            </td>
            <td class="text-end fs--1 text-muted">Customer Freight: PKR {{ number_format($settlement->customer_freight_total, 2) }}</td>
            <td class="text-end fw-bold text-dark fs-0">PKR {{ number_format($settlement->agreed_owner_payable, 2) }}</td>
        </tr>
        @if ($settlement->additional_charges > 0)
            <tr>
                <td class="text-center">2</td>
                <td>
                    <div class="fw-bold">Additional Charges / Bonus / Demurrage</div>
                    <div class="fs--2 text-muted">Authorized voyage allowance</div>
                </td>
                <td class="text-end fs--1 text-muted">Approved addition</td>
                <td class="text-end fw-bold text-success fs-0">+PKR {{ number_format($settlement->additional_charges, 2) }}</td>
            </tr>
        @endif
    </tbody>
</table>

<!-- Authorized Deductions Table -->
<h6 class="fw-bold fs--1 text-uppercase text-danger mb-2">Authorized Deductions:</h6>
<table class="table table-print mb-3 w-100">
    <thead>
        <tr>
            <th style="width: 10%;">#</th>
            <th style="width: 30%;">Deduction Category</th>
            <th style="width: 40%;">Description & Reference</th>
            <th style="width: 20%;" class="text-end">Amount Deducted (PKR)</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($settlement->deductionItems as $idx => $ded)
            <tr>
                <td class="text-center">{{ $idx + 1 }}</td>
                <td><span class="badge bg-secondary">{{ strtoupper($ded->deduction_type) }}</span></td>
                <td class="fs--1">{{ $ded->description }}</td>
                <td class="text-end fw-bold text-danger">-PKR {{ number_format($ded->amount, 2) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="4" class="text-center text-muted fs--1 py-2">No deductions recorded against this trip settlement.</td>
            </tr>
        @endforelse
        <tr class="table-light">
            <td colspan="3" class="text-end fw-bold">TOTAL APPROVED DEDUCTIONS:</td>
            <td class="text-end fw-bold text-danger fs-0">-PKR {{ number_format($settlement->total_deductions, 2) }}</td>
        </tr>
    </tbody>
</table>

<!-- Summary Calculation Box -->
<div class="row justify-content-end mb-4">
    <div class="col-6">
        <table class="table table-sm table-bordered">
            <tr>
                <td class="fw-semibold">Agreed Gross Payable:</td>
                <td class="text-end">PKR {{ number_format($settlement->agreed_owner_payable, 2) }}</td>
            </tr>
            @if ($settlement->additional_charges > 0)
                <tr>
                    <td class="fw-semibold">Additional Charges:</td>
                    <td class="text-end text-success">+PKR {{ number_format($settlement->additional_charges, 2) }}</td>
                </tr>
            @endif
            <tr>
                <td class="fw-semibold text-danger">Total Deductions:</td>
                <td class="text-end text-danger">-PKR {{ number_format($settlement->total_deductions, 2) }}</td>
            </tr>
            <tr class="table-success">
                <td class="fw-bold fs-0">NET PAYABLE TO OWNER:</td>
                <td class="text-end fw-bold fs-0 text-success">PKR {{ number_format($settlement->net_payable, 2) }}</td>
            </tr>
            <tr>
                <td class="fw-semibold text-muted">Amount Paid to Date:</td>
                <td class="text-end">PKR {{ number_format($settlement->paid_amount, 2) }}</td>
            </tr>
            <tr class="table-warning">
                <td class="fw-bold">BALANCE REMAINING DUE:</td>
                <td class="text-end fw-bold text-dark">PKR {{ number_format($settlement->balance_due, 2) }}</td>
            </tr>
        </table>
    </div>
</div>

<!-- Signatures & Audit Footers -->
<div class="row mt-5 pt-3 text-center border-top">
    <div class="col-4">
        <div class="border-top pt-2 mx-3">
            <div class="fw-bold fs--1">{{ $settlement->preparedByUser?->name ?: 'Prepared By' }}</div>
            <div class="fs--2 text-muted">Settlement Accountant</div>
        </div>
    </div>
    <div class="col-4">
        <div class="border-top pt-2 mx-3">
            <div class="fw-bold fs--1">{{ $settlement->approvedByUser?->name ?: 'Authorized Manager' }}</div>
            <div class="fs--2 text-muted">Approved By</div>
        </div>
    </div>
    <div class="col-4">
        <div class="border-top pt-2 mx-3">
            <div class="fw-bold fs--1">{{ $settlement->owner?->name }}</div>
            <div class="fs--2 text-muted">Vehicle Owner Acknowledgment</div>
        </div>
    </div>
</div>
@endsection


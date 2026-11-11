@extends('layouts.print')

@section('title', 'Owner Ledger - ' . $owner->name)

@section('content')
<div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
    <div>
        <h4 class="fw-bold text-primary mb-0">{{ $owner->company->name }}</h4>
        <div class="text-muted fs--1">STATEMENT OF ACCOUNT / VEHICLE OWNER RUNNING LEDGER</div>
        <div class="text-muted fs--2">
            Period: {{ $fromDate ? \Carbon\Carbon::parse($fromDate)->format('d M Y') : 'Beginning' }} to {{ $toDate ? \Carbon\Carbon::parse($toDate)->format('d M Y') : 'Current' }}
        </div>
    </div>
    <div class="text-end">
        <div class="fs-1 fw-bolder text-dark">{{ $owner->name }}</div>
        <div class="fs--2 text-muted">{{ $owner->company_name ?: 'Vehicle Owner' }} | Phone: {{ $owner->phone }}</div>
        <div class="fs--2 text-muted">Bank: {{ $owner->bank_name }} ({{ $owner->bank_account_number }})</div>
    </div>
</div>

<!-- Ledger Balances Summary -->
<div class="row g-2 mb-3">
    <div class="col-3">
        <div class="p-2 border rounded bg-light text-center">
            <div class="fs--2 text-muted text-uppercase fw-bold">Opening Balance</div>
            <div class="fs-0 fw-bold">PKR {{ number_format($ledgerSummary['opening_balance'], 2) }}</div>
        </div>
    </div>
    <div class="col-3">
        <div class="p-2 border rounded bg-light text-center">
            <div class="fs--2 text-muted text-uppercase fw-bold">Total Payables (+Cr)</div>
            <div class="fs-0 fw-bold text-primary">PKR {{ number_format($ledgerSummary['total_trip_payables'], 2) }}</div>
        </div>
    </div>
    <div class="col-3">
        <div class="p-2 border rounded bg-light text-center">
            <div class="fs--2 text-muted text-uppercase fw-bold">Total Paid / Deducted (-Dr)</div>
            <div class="fs-0 fw-bold text-danger">PKR {{ number_format($ledgerSummary['total_payments'] + $ledgerSummary['total_deductions'] + $ledgerSummary['total_advances'], 2) }}</div>
        </div>
    </div>
    <div class="col-3">
        <div class="p-2 border rounded bg-light text-center">
            <div class="fs--2 text-muted text-uppercase fw-bold">Closing Balance</div>
            <div class="fs-0 fw-bold text-success">PKR {{ number_format($ledgerSummary['closing_balance'], 2) }}</div>
        </div>
    </div>
</div>

<table class="table table-print mb-4 w-100">
    <thead>
        <tr>
            <th style="width: 12%;">Date</th>
            <th style="width: 15%;">Type</th>
            <th style="width: 15%;">Reference #</th>
            <th style="width: 28%;">Description</th>
            <th style="width: 10%;" class="text-end">Credit (+Cr)</th>
            <th style="width: 10%;" class="text-end">Debit (-Dr)</th>
            <th style="width: 10%;" class="text-end">Balance</th>
        </tr>
    </thead>
    <tbody>
        <tr class="table-light">
            <td colspan="6" class="fw-bold">Opening Balance as of {{ $fromDate ?: 'Start' }}:</td>
            <td class="text-end fw-bold">PKR {{ number_format($ledgerSummary['opening_balance'], 2) }}</td>
        </tr>
        @forelse ($ledgerSummary['entries'] as $entry)
            <tr>
                <td>{{ $entry->transaction_date->format('d/m/Y') }}</td>
                <td><span class="badge bg-light text-dark border">{{ strtoupper(str_replace('_', ' ', $entry->transaction_type)) }}</span></td>
                <td class="fw-semibold text-primary">{{ $entry->reference_number ?: '-' }}</td>
                <td class="fs--1">{{ $entry->description }}</td>
                <td class="text-end text-success fw-semibold">
                    {{ $entry->entry_type === 'credit' ? number_format($entry->amount, 2) : '-' }}
                </td>
                <td class="text-end text-danger fw-semibold">
                    {{ $entry->entry_type === 'debit' ? number_format($entry->amount, 2) : '-' }}
                </td>
                <td class="text-end fw-bold">
                    {{ number_format($entry->running_balance, 2) }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="text-center text-muted py-3">No transactions found for this period.</td>
            </tr>
        @endforelse
        <tr class="table-light">
            <td colspan="6" class="fw-bold text-end">CLOSING OUTSTANDING PAYABLE BALANCE:</td>
            <td class="text-end fw-bold text-success fs-0">PKR {{ number_format($ledgerSummary['closing_balance'], 2) }}</td>
        </tr>
    </tbody>
</table>

<div class="row mt-5 pt-3 text-center border-top">
    <div class="col-6">
        <div class="border-top pt-2 mx-5">
            <div class="fw-bold fs--1">Accounts Department</div>
            <div class="fs--2 text-muted">Financial Auditor</div>
        </div>
    </div>
    <div class="col-6">
        <div class="border-top pt-2 mx-5">
            <div class="fw-bold fs--1">{{ $owner->name }}</div>
            <div class="fs--2 text-muted">Vehicle Owner / Authorized Signature</div>
        </div>
    </div>
</div>
@endsection


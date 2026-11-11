@extends('layouts.print')

@section('title', 'Journal Voucher - ' . $voucher->voucher_number)

@section('content')
<div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
    <div>
        <h4 class="fw-bold text-primary mb-0">{{ $voucher->company->name }}</h4>
        <div class="text-muted fs--1">GENERAL ACCOUNTING VOUCHER (DOUBLE-ENTRY)</div>
        <div class="text-muted fs--2">{{ $voucher->branch?->name ?: 'Central Operations' }} | Date: {{ $voucher->voucher_date->format('d M Y') }}</div>
    </div>
    <div class="text-end">
        <div class="badge bg-{{ $voucher->isPosted() ? 'success' : 'danger' }} fs--2 mb-1">
            {{ strtoupper($voucher->status) }}
        </div>
        <div class="fs-1 fw-bolder text-primary">{{ $voucher->voucher_number }}</div>
        <div class="fs--2 text-muted">Type: {{ strtoupper(str_replace('_', ' ', $voucher->voucher_type)) }}</div>
    </div>
</div>

<div class="p-2 border rounded bg-light mb-3 fs--1">
    <strong>Narration / Explanation:</strong> {{ $voucher->narration }}
    @if($voucher->reference_number)
        <span class="ms-3 text-muted">| Reference: {{ $voucher->reference_number }}</span>
    @endif
</div>

<table class="table table-print mb-3 w-100">
    <thead>
        <tr>
            <th style="width: 5%;">#</th>
            <th style="width: 15%;">Account Code</th>
            <th style="width: 35%;">Account Title</th>
            <th style="width: 25%;">Line Memo</th>
            <th style="width: 10%;" class="text-end">Debit (PKR)</th>
            <th style="width: 10%;" class="text-end">Credit (PKR)</th>
        </tr>
    </thead>
    <tbody>
        @foreach($voucher->items as $idx => $item)
            <tr>
                <td class="text-center">{{ $idx + 1 }}</td>
                <td class="font-monospace fw-bold">{{ $item->account?->account_code }}</td>
                <td>{{ $item->account?->account_name }}</td>
                <td class="fs--2 text-muted">{{ $item->description }}</td>
                <td class="text-end font-monospace">{{ $item->debit > 0 ? number_format($item->debit, 2) : '—' }}</td>
                <td class="text-end font-monospace">{{ $item->credit > 0 ? number_format($item->credit, 2) : '—' }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr class="fw-bold fs-6 border-top border-2">
            <td colspan="4" class="text-end text-uppercase">Totals:</td>
            <td class="text-end font-monospace text-primary">{{ number_format($voucher->total_debit, 2) }}</td>
            <td class="text-end font-monospace text-primary">{{ number_format($voucher->total_credit, 2) }}</td>
        </tr>
    </tfoot>
</table>

<div class="row text-center mt-5">
    <div class="col-4">
        <div class="border-top pt-1 fs--2">Prepared By: {{ $voucher->createdByUser?->name ?? 'System' }}</div>
    </div>
    <div class="col-4">
        <div class="border-top pt-1 fs--2">Verified By</div>
    </div>
    <div class="col-4">
        <div class="border-top pt-1 fs--2">Authorized Signatory: {{ $voucher->postedByUser?->name }}</div>
    </div>
</div>
@endsection

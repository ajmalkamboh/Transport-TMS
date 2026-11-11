@extends('layouts.print')

@section('title', 'Voyage Profitability - ' . $trip->trip_number)

@section('content')
<div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
    <div>
        <h4 class="fw-bold text-primary mb-0">{{ $trip->company->name }}</h4>
        <div class="text-muted fs--1">VOYAGE & TRIP OPERATIONAL PROFITABILITY STATEMENT</div>
        <div class="text-muted fs--2">Trip: {{ $trip->trip_number }} | Route: {{ $trip->origin_city }} to {{ $trip->destination_city }}</div>
    </div>
    <div class="text-end">
        <div class="badge bg-{{ $report['is_profitable'] ? 'success' : 'danger' }} fs--2 mb-1">
            {{ $report['is_profitable'] ? 'PROFITABLE VOYAGE' : 'VOYAGE LOSS' }}
        </div>
        <div class="fs-1 fw-bolder text-primary">PKR {{ number_format($report['net_voyage_margin'], 2) }}</div>
        <div class="fs--2 text-muted">Margin: {{ $report['margin_percentage'] }}%</div>
    </div>
</div>

<div class="row g-2 mb-3">
    <div class="col-6">
        <div class="p-2 border rounded bg-light h-100">
            <h6 class="fw-bold fs--2 text-uppercase text-muted mb-1">VOYAGE LOGISTICS:</h6>
            <div class="fs--1"><strong>Vehicle:</strong> {{ $trip->vehicle?->registration_number }} ({{ $trip->vehicle?->make }} {{ $trip->vehicle?->model }})</div>
            <div class="fs--1"><strong>Driver:</strong> {{ $trip->driver?->name }} ({{ $trip->driver?->phone }})</div>
            <div class="fs--2 text-muted">Total Distance: {{ number_format($trip->total_distance_km ?? 0, 1) }} KM</div>
        </div>
    </div>
    <div class="col-6">
        <div class="p-2 border rounded bg-light h-100">
            <h6 class="fw-bold fs--2 text-uppercase text-muted mb-1">COMMERCIAL SUMMARY:</h6>
            <div class="fs--1"><strong>Total Freight Revenue:</strong> PKR {{ number_format($report['freight_revenue'], 2) }}</div>
            <div class="fs--1"><strong>Total Direct Costs:</strong> PKR {{ number_format($report['total_direct_costs'], 2) }}</div>
            <div class="fs--2 text-muted">Profit Ratio: {{ $report['margin_percentage'] }}% of Gross Revenue</div>
        </div>
    </div>
</div>

<table class="table table-print mb-3 w-100">
    <thead>
        <tr>
            <th style="width: 10%;">#</th>
            <th style="width: 60%;">Operational Revenue & Direct Cost Breakdown</th>
            <th style="width: 30%;" class="text-end">Amount (PKR)</th>
        </tr>
    </thead>
    <tbody>
        <tr class="table-light fw-bold">
            <td class="text-center">A</td>
            <td>Gross Freight Revenue (Invoiced / Consignments)</td>
            <td class="text-end font-monospace text-success">{{ number_format($report['freight_revenue'], 2) }}</td>
        </tr>
        <tr>
            <td class="text-center">1</td>
            <td>Fuel Purchase & Consumption ({{ number_format($report['fuel_liters'], 1) }} Liters)</td>
            <td class="text-end font-monospace text-danger">-{{ number_format($report['fuel_cost'], 2) }}</td>
        </tr>
        <tr>
            <td class="text-center">2</td>
            <td>Motorway Tolls, Weigh-bridge & Route Expenses</td>
            <td class="text-end font-monospace text-danger">-{{ number_format($report['tolls_and_expenses'], 2) }}</td>
        </tr>
        <tr>
            <td class="text-center">3</td>
            <td>Driver Voyage Duty Allowance</td>
            <td class="text-end font-monospace text-danger">-{{ number_format($report['driver_allowances'], 2) }}</td>
        </tr>
        @if((float)$report['owner_freight_cost'] > 0)
            <tr>
                <td class="text-center">4</td>
                <td>Attached Third-Party Vehicle Freight Cost</td>
                <td class="text-end font-monospace text-danger">-{{ number_format($report['owner_freight_cost'], 2) }}</td>
            </tr>
        @endif
        <tr class="fw-bold">
            <td colspan="2" class="text-end">Total Direct Voyage Costs:</td>
            <td class="text-end font-monospace text-danger">-{{ number_format($report['total_direct_costs'], 2) }}</td>
        </tr>
    </tbody>
    <tfoot>
        <tr class="fw-bold fs-6 border-top border-2">
            <td colspan="2" class="text-end text-uppercase {{ $report['is_profitable'] ? 'text-success' : 'text-danger' }}">
                Net Voyage Operational Margin:
            </td>
            <td class="text-end font-monospace {{ $report['is_profitable'] ? 'text-success' : 'text-danger' }}">
                PKR {{ number_format($report['net_voyage_margin'], 2) }}
            </td>
        </tr>
    </tfoot>
</table>

<div class="row text-center mt-5">
    <div class="col-6">
        <div class="border-top pt-1 fs--2">Fleet Operations Officer</div>
    </div>
    <div class="col-6">
        <div class="border-top pt-1 fs--2">Financial Controller</div>
    </div>
</div>
@endsection

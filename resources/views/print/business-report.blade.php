@extends('layouts.print')

@section('title', $title)

@section('content')
<div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
    <div>
        <h4 class="fw-bold text-primary mb-0">{{ $company->name }}</h4>
        <div class="text-muted fs--1 text-uppercase">{{ $title }}</div>
        <div class="text-muted fs--2">Report Period: {{ date('d M Y', strtotime($fromDate)) }} to {{ date('d M Y', strtotime($toDate)) }}</div>
    </div>
    <div class="text-end">
        <div class="fs--2 text-muted">Generated: {{ now()->format('d M Y H:i:s') }}</div>
        <div class="fs--2 text-muted">User: {{ auth()->user()->name }}</div>
    </div>
</div>

@if ($tab === 'executive')
    <div class="row g-2 mb-3 fs--1">
        <div class="col-3 p-2 border bg-light"><strong>Total Fleet:</strong> {{ $data['total_fleet'] ?? 0 }}</div>
        <div class="col-3 p-2 border bg-light"><strong>Active Drivers:</strong> {{ $data['active_drivers'] ?? 0 }}</div>
        <div class="col-3 p-2 border bg-light"><strong>Completed Trips:</strong> {{ $data['completed_trips'] ?? 0 }}</div>
        <div class="col-3 p-2 border bg-light"><strong>Pending Deliveries:</strong> {{ $data['pending_deliveries'] ?? 0 }}</div>
    </div>
    <div class="row g-2 mb-4 fs--1">
        <div class="col-3 p-2 border bg-light"><strong>Freight Revenue:</strong> PKR {{ number_format($data['monthly_freight_revenue'] ?? 0, 2) }}</div>
        <div class="col-3 p-2 border bg-light"><strong>Trip Expenses:</strong> PKR {{ number_format($data['trip_expenses'] ?? 0, 2) }}</div>
        <div class="col-3 p-2 border bg-light"><strong>Customer Receivables:</strong> PKR {{ number_format($data['outstanding_receivables'] ?? 0, 2) }}</div>
        <div class="col-3 p-2 border bg-light"><strong>Finalized Profit:</strong> PKR {{ number_format($data['finalized_profit'] ?? 0, 2) }}</div>
    </div>

@elseif ($tab === 'fleet')
    <table class="table table-print mb-3 w-100">
        <thead>
            <tr>
                <th>Reg #</th>
                <th>Type / Owner</th>
                <th class="text-center">Status</th>
                <th class="text-end">Trips</th>
                <th class="text-end">KM</th>
                <th class="text-end">Freight (PKR)</th>
                <th class="text-end">Running Cost</th>
                <th class="text-end">Net Profit</th>
                <th class="text-end">KM/L</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data as $r)
                <tr>
                    <td class="font-monospace fw-bold">{{ $r['registration_number'] }}</td>
                    <td>{{ $r['ownership_type'] }} &bull; {{ $r['owner_name'] }}</td>
                    <td class="text-center">{{ $r['status'] }}</td>
                    <td class="text-end">{{ $r['trip_count'] }}</td>
                    <td class="text-end font-monospace">{{ number_format($r['distance_km'], 0) }}</td>
                    <td class="text-end font-monospace">{{ number_format($r['freight_revenue'], 2) }}</td>
                    <td class="text-end font-monospace">{{ number_format($r['total_running_cost'], 2) }}</td>
                    <td class="text-end font-monospace fw-bold">{{ number_format($r['net_profit'], 2) }}</td>
                    <td class="text-end font-monospace">{{ $r['fuel_efficiency_kml'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

@elseif ($tab === 'customer')
    <table class="table table-print mb-3 w-100">
        <thead>
            <tr>
                <th>Code & Customer</th>
                <th class="text-end">Total Billed</th>
                <th class="text-end">Collected</th>
                <th class="text-end">Outstanding</th>
                <th class="text-end">0-30 Days</th>
                <th class="text-end">31-60 Days</th>
                <th class="text-end">61-90 Days</th>
                <th class="text-end">90+ Days</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data as $r)
                <tr>
                    <td><strong>{{ $r['name'] }}</strong> ({{ $r['code'] }})</td>
                    <td class="text-end font-monospace">{{ number_format($r['total_billed'], 2) }}</td>
                    <td class="text-end font-monospace">{{ number_format($r['total_collected'], 2) }}</td>
                    <td class="text-end font-monospace fw-bold text-danger">{{ number_format($r['total_outstanding'], 2) }}</td>
                    <td class="text-end font-monospace">{{ number_format($r['current_0_to_30'], 2) }}</td>
                    <td class="text-end font-monospace">{{ number_format($r['days_31_to_60'], 2) }}</td>
                    <td class="text-end font-monospace">{{ number_format($r['days_61_to_90'], 2) }}</td>
                    <td class="text-end font-monospace fw-bold text-danger">{{ number_format($r['days_90_plus'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

@elseif ($tab === 'driver')
    <table class="table table-print mb-3 w-100">
        <thead>
            <tr>
                <th>Code & Driver</th>
                <th>License & Phone</th>
                <th class="text-end">Trips</th>
                <th class="text-end">Advances</th>
                <th class="text-end">Expenses</th>
                <th class="text-end">Allowances</th>
                <th class="text-end">Net Balance</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data as $r)
                <tr>
                    <td><strong>{{ $r['name'] }}</strong> ({{ $r['employee_code'] }})</td>
                    <td>{{ $r['license_number'] }} &bull; {{ $r['phone'] }}</td>
                    <td class="text-end">{{ $r['completed_trips'] }} / {{ $r['total_trips'] }}</td>
                    <td class="text-end font-monospace">{{ number_format($r['total_advances'], 2) }}</td>
                    <td class="text-end font-monospace">{{ number_format($r['approved_expenses'], 2) }}</td>
                    <td class="text-end font-monospace">{{ number_format($r['allowances_paid'], 2) }}</td>
                    <td class="text-end font-monospace fw-bold">{{ number_format($r['net_settlement_payable'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

@elseif ($tab === 'inventory')
    <table class="table table-print mb-3 w-100">
        <thead>
            <tr>
                <th>Warehouse</th>
                <th>Item Code & Name</th>
                <th>Category</th>
                <th class="text-end">On Hand</th>
                <th class="text-end">Available</th>
                <th class="text-end">Avg Cost</th>
                <th class="text-end">Valuation</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data['summary'] as $r)
                <tr>
                    <td>{{ $r['warehouse_name'] }}</td>
                    <td><strong>{{ $r['item_name'] }}</strong> ({{ $r['item_code'] }})</td>
                    <td>{{ $r['category'] }}</td>
                    <td class="text-end font-monospace">{{ number_format($r['quantity_on_hand'], 2) }} {{ $r['uom'] }}</td>
                    <td class="text-end font-monospace">{{ number_format($r['available_quantity'], 2) }}</td>
                    <td class="text-end font-monospace">{{ number_format($r['average_unit_cost'], 2) }}</td>
                    <td class="text-end font-monospace fw-bold">{{ number_format($r['total_valuation'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="fw-bold fs-6 border-top">
                <td colspan="6" class="text-end">TOTAL STOCK VALUATION:</td>
                <td class="text-end font-monospace text-primary">PKR {{ number_format($data['total_stock_valuation'], 2) }}</td>
            </tr>
        </tfoot>
    </table>
@endif

<div class="row text-center mt-5 pt-4">
    <div class="col-4">
        <div class="border-top pt-2 fs--1 text-muted">Prepared By</div>
    </div>
    <div class="col-4">
        <div class="border-top pt-2 fs--1 text-muted">Operations Manager</div>
    </div>
    <div class="col-4">
        <div class="border-top pt-2 fs--1 text-muted">Executive Auditor</div>
    </div>
</div>
@endsection


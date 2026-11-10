@extends('layouts.print')

@section('title', 'Job Card - ' . $jobCard->job_card_number)

@section('content')
<div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
    <div>
        <h4 class="fw-bold text-primary mb-0">{{ $jobCard->company->name }}</h4>
        <div class="text-muted fs--1">WORKSHOP & FLEET PREVENTIVE MAINTENANCE JOB CARD</div>
        <div class="text-muted fs--2">{{ $jobCard->branch?->name ?: 'Central Mechanical Yard' }} | Scheduled: {{ $jobCard->scheduled_date->format('d M Y') }}</div>
    </div>
    <div class="text-end">
        <div class="badge bg-{{ $jobCard->status === 'completed' ? 'success' : 'primary' }} fs--2 mb-1">
            {{ strtoupper($jobCard->status) }}
        </div>
        <div class="fs-1 fw-bolder text-primary">{{ $jobCard->job_card_number }}</div>
        <div class="fs--2 text-muted">Service: {{ strtoupper(str_replace('_', ' ', $jobCard->service_type)) }}</div>
    </div>
</div>

<div class="row g-2 mb-3">
    <div class="col-4">
        <div class="p-2 border rounded bg-light h-100">
            <h6 class="fw-bold fs--2 text-uppercase text-muted mb-1">VEHICLE IDENTIFICATION:</h6>
            <div class="fs--1"><strong>Reg #:</strong> {{ $jobCard->vehicle?->registration_number }}</div>
            <div class="fs--2 text-muted">{{ $jobCard->vehicle?->make_model }} ({{ $jobCard->vehicle?->vehicle_type }})</div>
            <div class="fs--1 mt-1"><strong>Odometer:</strong> {{ number_format($jobCard->odometer_km) }} KM</div>
            <div class="fs--2 text-muted">Driver: {{ $jobCard->driver?->name ?: 'Fleet Assigned' }}</div>
        </div>
    </div>
    <div class="col-4">
        <div class="p-2 border rounded bg-light h-100">
            <h6 class="fw-bold fs--2 text-uppercase text-muted mb-1">WORKSHOP & MECHANIC:</h6>
            <div class="fs--1"><strong>Facility:</strong> {{ $jobCard->workshop_name ?: 'Internal Terminal Workshop' }}</div>
            <div class="fs--2 text-muted">Type: {{ strtoupper(str_replace('_', ' ', $jobCard->workshop_type)) }}</div>
            <div class="fs--1 mt-1"><strong>Lead Technician:</strong> {{ $jobCard->technician_name ?: 'Chief Mechanic' }}</div>
            <div class="fs--2 text-muted">Priority: <span class="badge bg-secondary fs--2">{{ strtoupper($jobCard->priority) }}</span></div>
        </div>
    </div>
    <div class="col-4">
        <div class="p-2 border rounded bg-light h-100">
            <h6 class="fw-bold fs--2 text-uppercase text-muted mb-1">DOWNTIME & TIMINGS:</h6>
            <div class="fs--2">Started: <strong>{{ $jobCard->started_at ? $jobCard->started_at->format('d M H:i') : '-' }}</strong></div>
            <div class="fs--2">Completed: <strong>{{ $jobCard->completed_at ? $jobCard->completed_at->format('d M H:i') : '-' }}</strong></div>
            <div class="fs--1 mt-1"><strong>Fleet Downtime:</strong> <span class="badge bg-warning text-dark">{{ $jobCard->downtime_hours }} Hours</span></div>
        </div>
    </div>
</div>

@if($jobCard->issue_description)
    <div class="p-2 border rounded bg-light mb-3 fs--1">
        <strong>Reported Issue / Work Order:</strong> {{ $jobCard->issue_description }}
        @if($jobCard->work_performed)
            <div class="mt-1"><strong>Remedial Work Performed:</strong> {{ $jobCard->work_performed }}</div>
        @endif
    </div>
@endif

<h6 class="fw-bold text-uppercase fs--2 mb-2">SPARE PARTS & CONSUMABLES UTILIZED</h6>
<table class="table table-print mb-3 w-100">
    <thead>
        <tr>
            <th style="width: 5%;">#</th>
            <th style="width: 45%;">Spare Part Description</th>
            <th style="width: 15%;">Part #</th>
            <th style="width: 10%;" class="text-center">Qty</th>
            <th style="width: 12%;" class="text-end">Unit Cost</th>
            <th style="width: 13%;" class="text-end">Total Cost</th>
        </tr>
    </thead>
    <tbody>
        @forelse($jobCard->items as $idx => $part)
            <tr>
                <td class="text-center">{{ $idx + 1 }}</td>
                <td>
                    <div class="fw-bold">{{ $part->part_name }}</div>
                    @if($part->notes)<div class="fs--2 text-muted">{{ $part->notes }}</div>@endif
                </td>
                <td class="font-monospace fs--2">{{ $part->part_number ?: '-' }}</td>
                <td class="text-center fw-bold">{{ $part->quantity }}</td>
                <td class="text-end">PKR {{ number_format($part->unit_cost, 2) }}</td>
                <td class="text-end fw-bold">PKR {{ number_format($part->total_cost, 2) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="text-center text-muted py-2">No replacement parts billed. General inspection/service only.</td>
            </tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5" class="text-end fw-bold">Parts & Consumables Total:</td>
            <td class="text-end fw-bold">PKR {{ number_format($jobCard->parts_total_cost, 2) }}</td>
        </tr>
        <tr>
            <td colspan="5" class="text-end fw-bold">Workshop Labour & Diagnostic Charges:</td>
            <td class="text-end fw-bold">PKR {{ number_format($jobCard->labour_charges, 2) }}</td>
        </tr>
        @if($jobCard->tax_amount > 0)
            <tr>
                <td colspan="5" class="text-end fw-bold">Applicable Taxes:</td>
                <td class="text-end fw-bold">PKR {{ number_format($jobCard->tax_amount, 2) }}</td>
            </tr>
        @endif
        <tr class="fw-bold bg-light">
            <td colspan="5" class="text-end fs-0">TOTAL MAINTENANCE COST:</td>
            <td class="text-end fs-1 text-primary">PKR {{ number_format($jobCard->total_cost, 2) }}</td>
        </tr>
    </tfoot>
</table>

<div class="row g-2 mt-4 pt-3 border-top">
    <div class="col-6">
        <div class="text-center">
            <div style="height: 45px;"></div>
            <div class="border-top pt-1 fw-bold fs--1">Head Mechanical Technician</div>
            <div class="fs--2 text-muted">Certified repair and road-worthiness testing completed</div>
        </div>
    </div>
    <div class="col-6">
        <div class="text-center">
            <div style="height: 45px;"></div>
            <div class="border-top pt-1 fw-bold fs--1">Workshop Operations Manager</div>
            <div class="fs--2 text-muted">Vehicle authorized to return to operational service</div>
        </div>
    </div>
</div>
@endsection


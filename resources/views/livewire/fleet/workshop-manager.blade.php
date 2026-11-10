<div>
    {{-- Header --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6">
            <h3 class="mb-0 text-primary fw-bold"><i class="fas fa-tools me-2"></i>Workshop & Vehicle Maintenance</h3>
            <p class="text-muted fs--1 mb-0">Job Cards, Preventive Schedules, Spare Parts Billing, Vehicle Downtime & Component Life Tracking</p>
        </div>
        <div class="col-12 col-md-6 text-md-end d-flex gap-2 justify-content-md-end">
            <button wire:click="openCreateModal" class="btn btn-primary">
                <i class="fas fa-plus-circle me-1"></i> New Job Card
            </button>
            <button wire:click="$set('showScheduleModal', true)" class="btn btn-outline-secondary">
                <i class="fas fa-calendar-alt me-1"></i> New Schedule
            </button>
            <button wire:click="$set('showComponentModal', true)" class="btn btn-outline-secondary">
                <i class="fas fa-car-battery me-1"></i> Track Component
            </button>
        </div>
    </div>

    {{-- Flash Notifications --}}
    @if(session()->has('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Navigation Tabs --}}
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link {{ $activeTab === 'job_cards' ? 'active fw-bold' : '' }}" href="javascript:void(0)" wire:click="$set('activeTab', 'job_cards')">
                <i class="fas fa-file-invoice me-1"></i> Job Cards ({{ $jobCards->total() }})
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $activeTab === 'schedules' ? 'active fw-bold' : '' }}" href="javascript:void(0)" wire:click="$set('activeTab', 'schedules')">
                <i class="fas fa-calendar-check me-1"></i> Maintenance Schedules ({{ $schedules->count() }})
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $activeTab === 'components' ? 'active fw-bold' : '' }}" href="javascript:void(0)" wire:click="$set('activeTab', 'components')">
                <i class="fas fa-ring me-1"></i> Tyres & Components ({{ $components->count() }})
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $activeTab === 'alerts' ? 'active fw-bold' : '' }}" href="javascript:void(0)" wire:click="$set('activeTab', 'alerts')">
                <i class="fas fa-bell me-1 text-danger"></i> Maintenance Alerts ({{ $alerts->count() }})
            </a>
        </li>
    </ul>

    {{-- Tab 1: Job Cards --}}
    @if($activeTab === 'job_cards')
        <div class="card mb-3">
            <div class="card-body p-3">
                <div class="row g-2 align-items-center">
                    <div class="col-md-7">
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" wire:model.live.debounce.300ms="search" class="form-control" placeholder="Search by Job Card #, Vehicle Reg, Technician...">
                        </div>
                    </div>
                    <div class="col-md-5">
                        <select wire:model.live="statusFilter" class="form-select">
                            <option value="">All Job Card Statuses</option>
                            <option value="scheduled">Scheduled</option>
                            <option value="in_progress">In Progress (Downtime Active)</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Job Card # & Date</th>
                            <th>Vehicle & Odometer</th>
                            <th>Service Type & Workshop</th>
                            <th>Downtime</th>
                            <th class="text-end">Parts & Labour (PKR)</th>
                            <th>Status</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($jobCards as $jc)
                            <tr>
                                <td>
                                    <span class="fw-bold text-primary">{{ $jc->job_card_number }}</span>
                                    <div class="fs--2 text-muted">{{ $jc->scheduled_date->format('d M Y') }}</div>
                                </td>
                                <td>
                                    <div><i class="fas fa-truck text-muted me-1"></i><strong>{{ $jc->vehicle?->registration_number }}</strong></div>
                                    <div class="fs--2 text-muted">{{ number_format($jc->odometer_km) }} KM</div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary fs--2">{{ strtoupper(str_replace('_', ' ', $jc->service_type)) }}</span>
                                    <div class="fs--2 text-muted mt-1">{{ $jc->workshop_name ?: 'In-house' }}</div>
                                </td>
                                <td>
                                    @if($jc->status === 'completed')
                                        <span class="badge bg-light text-dark border">{{ $jc->downtime_hours }} hrs</span>
                                    @elseif($jc->status === 'in_progress')
                                        <span class="badge bg-warning text-dark"><i class="fas fa-spinner fa-spin me-1"></i>Active</span>
                                    @else
                                        <span class="text-muted fs--2">-</span>
                                    @endif
                                </td>
                                <td class="text-end fw-bold fs-0 text-success">
                                    PKR {{ number_format($jc->total_cost, 2) }}
                                    <div class="fs--2 text-muted">Parts: PKR {{ number_format($jc->parts_total_cost, 2) }}</div>
                                </td>
                                <td>
                                    @if($jc->status === 'in_progress')
                                        <span class="badge bg-warning text-dark"><i class="fas fa-wrench me-1"></i>IN PROGRESS</span>
                                    @elseif($jc->status === 'completed')
                                        <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>COMPLETED</span>
                                    @elseif($jc->status === 'scheduled')
                                        <span class="badge bg-info text-dark">SCHEDULED</span>
                                    @else
                                        <span class="badge bg-secondary">{{ strtoupper($jc->status) }}</span>
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-flex justify-content-end gap-1">
                                        @if($jc->status === 'in_progress')
                                            <button wire:click="openPartModal({{ $jc->id }})" class="btn btn-sm btn-outline-warning py-0 fs--2" title="Add Spare Part">
                                                <i class="fas fa-plus me-1"></i> Part
                                            </button>
                                            <button wire:click="openCompleteModal({{ $jc->id }})" class="btn btn-sm btn-outline-success py-0 fs--2" title="Complete Job Card">
                                                <i class="fas fa-check-double me-1"></i> Complete
                                            </button>
                                        @endif
                                        <a href="{{ route('print.job-card', $jc->id) }}" target="_blank" class="btn btn-sm btn-outline-primary" title="Print Job Card">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="fas fa-wrench fa-2x mb-2 d-block"></i>
                                    No workshop job cards found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-light p-2">
                {{ $jobCards->links() }}
            </div>
        </div>
    @endif

    {{-- Tab 2: Maintenance Schedules --}}
    @if($activeTab === 'schedules')
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Schedule Title</th>
                            <th>Vehicle</th>
                            <th>Category</th>
                            <th>Intervals</th>
                            <th>Last Performed</th>
                            <th>Next Due</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($schedules as $sched)
                            <tr>
                                <td class="fw-bold text-dark">{{ $sched->title }}</td>
                                <td>
                                    @if($sched->vehicle)
                                        <strong>{{ $sched->vehicle->registration_number }}</strong>
                                    @else
                                        <span class="badge bg-secondary fs--2">Generic Fleet Rule</span>
                                    @endif
                                </td>
                                <td><span class="badge bg-info text-dark fs--2">{{ strtoupper(str_replace('_', ' ', $sched->maintenance_category)) }}</span></td>
                                <td>
                                    @if($sched->interval_km) <div>{{ number_format($sched->interval_km) }} KM</div> @endif
                                    @if($sched->interval_days) <div class="fs--2 text-muted">{{ $sched->interval_days }} Days</div> @endif
                                </td>
                                <td>
                                    <div>{{ $sched->last_performed_at ? $sched->last_performed_at->format('d M Y') : '-' }}</div>
                                    <div class="fs--2 text-muted">{{ $sched->last_performed_odometer_km ? number_format($sched->last_performed_odometer_km) . ' KM' : '' }}</div>
                                </td>
                                <td>
                                    @if($sched->next_due_odometer_km)
                                        <div class="fw-bold">{{ number_format($sched->next_due_odometer_km) }} KM</div>
                                    @endif
                                    @if($sched->next_due_date)
                                        <div class="fs--2 text-muted">{{ $sched->next_due_date->format('d M Y') }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if($sched->status === 'overdue')
                                        <span class="badge bg-danger"><i class="fas fa-exclamation-circle me-1"></i>OVERDUE</span>
                                    @elseif($sched->status === 'due')
                                        <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>DUE SOON</span>
                                    @else
                                        <span class="badge bg-success">ACTIVE</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center py-4 text-muted">No maintenance schedules defined.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Tab 3: Components Tracking --}}
    @if($activeTab === 'components')
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Vehicle</th>
                            <th>Component Type</th>
                            <th>Brand & Specs</th>
                            <th>Serial / Position</th>
                            <th>Installed Odo & Date</th>
                            <th>Usage (KM)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($components as $comp)
                            <tr>
                                <td><strong>{{ $comp->vehicle?->registration_number }}</strong></td>
                                <td><span class="badge bg-secondary fs--2">{{ strtoupper($comp->component_type) }}</span></td>
                                <td>
                                    <div class="fw-semi-bold">{{ $comp->brand_name }}</div>
                                    <div class="fs--2 text-muted">{{ $comp->model_or_size }}</div>
                                </td>
                                <td>
                                    <div>{{ $comp->position ? strtoupper(str_replace('_', ' ', $comp->position)) : 'N/A' }}</div>
                                    <div class="fs--2 text-muted font-monospace">{{ $comp->serial_number ?: '-' }}</div>
                                </td>
                                <td>
                                    <div>{{ number_format($comp->installed_odometer_km) }} KM</div>
                                    <div class="fs--2 text-muted">{{ $comp->installed_date->format('d M Y') }}</div>
                                </td>
                                <td>
                                    <div class="fw-bold">{{ number_format($comp->getUsageKm($comp->vehicle?->current_odometer ?? 0)) }} KM</div>
                                    @if($comp->expected_life_km)
                                        <div class="fs--2 text-muted">Limit: {{ number_format($comp->expected_life_km) }} KM</div>
                                    @endif
                                </td>
                                <td>
                                    @if($comp->status === 'active')
                                        <span class="badge bg-success">ACTIVE</span>
                                    @else
                                        <span class="badge bg-secondary">{{ strtoupper($comp->status) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center py-4 text-muted">No components logged yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Tab 4: Maintenance Alerts --}}
    @if($activeTab === 'alerts')
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Severity</th>
                            <th>Vehicle</th>
                            <th>Alert Title & Description</th>
                            <th>Due Threshold</th>
                            <th>Date Generated</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($alerts as $alert)
                            <tr>
                                <td>
                                    @if($alert->severity === 'critical')
                                        <span class="badge bg-danger"><i class="fas fa-exclamation-triangle me-1"></i>CRITICAL</span>
                                    @elseif($alert->severity === 'warning')
                                        <span class="badge bg-warning text-dark"><i class="fas fa-bell me-1"></i>WARNING</span>
                                    @else
                                        <span class="badge bg-info text-dark">INFO</span>
                                    @endif
                                </td>
                                <td><strong>{{ $alert->vehicle?->registration_number }}</strong></td>
                                <td>
                                    <div class="fw-bold">{{ $alert->title }}</div>
                                    <div class="fs--2 text-muted">{{ $alert->description }}</div>
                                </td>
                                <td>
                                    @if($alert->due_odometer_km)
                                        <div>{{ number_format($alert->due_odometer_km) }} KM</div>
                                    @endif
                                    @if($alert->due_date)
                                        <div class="fs--2 text-muted">{{ $alert->due_date->format('d M Y') }}</div>
                                    @endif
                                </td>
                                <td class="fs--2 text-muted">{{ $alert->created_at->format('d M Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    <i class="fas fa-check-circle fa-2x text-success mb-2 d-block"></i>
                                    All fleet vehicles are operating within normal preventive maintenance thresholds.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Create Job Card Modal --}}
    @if($showCreateModal)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="fas fa-tools me-2"></i>Create Workshop Job Card</h5>
                        <button type="button" class="btn-close btn-close-white" wire:click="$set('showCreateModal', false)"></button>
                    </div>
                    <form wire:submit.prevent="saveJobCard">
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Vehicle <span class="text-danger">*</span></label>
                                    <select wire:model.live="vehicle_id" class="form-select" required>
                                        <option value="">-- Choose Vehicle --</option>
                                        @foreach($vehicles as $veh)
                                            <option value="{{ $veh->id }}">{{ $veh->registration_number }} &bull; {{ $veh->make_model }} (Odo: {{ number_format($veh->current_odometer) }} KM)</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Reporting Driver</label>
                                    <select wire:model="driver_id" class="form-select">
                                        <option value="">-- Assigned Driver --</option>
                                        @foreach($drivers as $drv)
                                            <option value="{{ $drv->id }}">{{ $drv->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Current Odometer (KM) <span class="text-danger">*</span></label>
                                    <input type="number" wire:model="odometer_km" class="form-control" min="0" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Scheduled Service Date <span class="text-danger">*</span></label>
                                    <input type="date" wire:model="scheduled_date" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Service Type <span class="text-danger">*</span></label>
                                    <select wire:model="service_type" class="form-select" required>
                                        <option value="preventive">Preventive Maintenance</option>
                                        <option value="breakdown">Breakdown Repair</option>
                                        <option value="scheduled">Scheduled Routine Service</option>
                                        <option value="repair">General Mechanical Repair</option>
                                        <option value="tyre_service">Tyre Replacement / Balancing</option>
                                        <option value="oil_lube">Oil & Lube Service</option>
                                        <option value="body_work">Body Work / Denting Painting</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Workshop Facility</label>
                                    <select wire:model="workshop_type" class="form-select">
                                        <option value="in_house">In-House Terminal Workshop</option>
                                        <option value="third_party">Third-Party External Workshop</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Workshop Name</label>
                                    <input type="text" wire:model="workshop_name" class="form-control">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold">Reported Faults & Work Order Description</label>
                                    <textarea wire:model="issue_description" class="form-control" rows="2" placeholder="Driver reported brake vibration, oil leak, abnormal exhaust noise..."></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="$set('showCreateModal', false)">Cancel</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-wrench me-1"></i> Initialize Job Card</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Add Spare Part Modal --}}
    @if($showPartModal && $selectedJobCard)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add Spare Part &bull; {{ $selectedJobCard->job_card_number }}</h5>
                        <button type="button" class="btn-close" wire:click="$set('showPartModal', false)"></button>
                    </div>
                    <form wire:submit.prevent="addPart">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Part Name <span class="text-danger">*</span></label>
                                <input type="text" wire:model="part_name" class="form-control" placeholder="e.g. Brake Lining Set / Oil Filter" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Part Number / SKU</label>
                                <input type="text" wire:model="part_number" class="form-control" placeholder="e.g. HINO-FLT-8822">
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label fw-bold">Quantity <span class="text-danger">*</span></label>
                                    <input type="number" step="0.5" wire:model="part_quantity" class="form-control" min="1" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-bold">Unit Cost (PKR) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" wire:model="part_unit_cost" class="form-control" min="0" required>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="$set('showPartModal', false)">Cancel</button>
                            <button type="submit" class="btn btn-warning"><i class="fas fa-save me-1"></i> Add Part to Job Card</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Complete Job Card Modal --}}
    @if($showCompleteModal && $selectedJobCard)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title"><i class="fas fa-check-double me-2"></i>Complete Job Card &bull; {{ $selectedJobCard->job_card_number }}</h5>
                        <button type="button" class="btn-close btn-close-white" wire:click="$set('showCompleteModal', false)"></button>
                    </div>
                    <form wire:submit.prevent="completeJobCard">
                        <div class="modal-body">
                            <div class="alert alert-info fs--1">
                                <strong>Vehicle Handover:</strong> Completing this job card closes workshop downtime, calculates total maintenance charges, and automatically restores vehicle status back to "Available".
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Parts Total Cost</label>
                                <input type="text" class="form-control bg-light" value="PKR {{ number_format($selectedJobCard->parts_total_cost, 2) }}" readonly>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label fw-bold">Labour Charges (PKR)</label>
                                    <input type="number" step="0.01" wire:model="complete_labour" class="form-control" min="0">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-bold">Applicable Taxes (PKR)</label>
                                    <input type="number" step="0.01" wire:model="complete_tax" class="form-control" min="0">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Summary of Work Performed</label>
                                <textarea wire:model="work_performed" class="form-control" rows="2" placeholder="Replaced brake pads, bled hydraulic lines, road tested sound..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="$set('showCompleteModal', false)">Cancel</button>
                            <button type="submit" class="btn btn-success"><i class="fas fa-check-circle me-1"></i> Complete & Release Vehicle</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Add Schedule Modal --}}
    @if($showScheduleModal)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title"><i class="fas fa-calendar-alt me-2"></i>Configure Maintenance Schedule</h5>
                        <button type="button" class="btn-close btn-close-white" wire:click="$set('showScheduleModal', false)"></button>
                    </div>
                    <form wire:submit.prevent="saveSchedule">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Schedule Title <span class="text-danger">*</span></label>
                                <input type="text" wire:model="sched_title" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Category</label>
                                <select wire:model="sched_category" class="form-select">
                                    <option value="oil_change">Engine Oil Change</option>
                                    <option value="service_interval">General Service Interval</option>
                                    <option value="brake_inspection">Brake Inspection</option>
                                    <option value="tyre_rotation">Tyre Rotation</option>
                                    <option value="battery_check">Battery Health Check</option>
                                    <option value="general_overhaul">Major Overhaul</option>
                                </select>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label fw-bold">Interval (KM)</label>
                                    <input type="number" wire:model="sched_interval_km" class="form-control" placeholder="10000">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-bold">Interval (Days)</label>
                                    <input type="number" wire:model="sched_interval_days" class="form-control" placeholder="90">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Vehicle Binding (Optional)</label>
                                <select wire:model="sched_vehicle_id" class="form-select">
                                    <option value="">-- Applies to All Fleet Trucks --</option>
                                    @foreach($vehicles as $veh)
                                        <option value="{{ $veh->id }}">{{ $veh->registration_number }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="$set('showScheduleModal', false)">Cancel</button>
                            <button type="submit" class="btn btn-dark"><i class="fas fa-save me-1"></i> Save Schedule Rule</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Track Component Modal --}}
    @if($showComponentModal)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-secondary text-white">
                        <h5 class="modal-title"><i class="fas fa-ring me-2"></i>Install / Track Vehicle Component</h5>
                        <button type="button" class="btn-close btn-close-white" wire:click="$set('showComponentModal', false)"></button>
                    </div>
                    <form wire:submit.prevent="saveComponent">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Vehicle <span class="text-danger">*</span></label>
                                <select wire:model="comp_vehicle_id" class="form-select" required>
                                    <option value="">-- Choose Vehicle --</option>
                                    @foreach($vehicles as $veh)
                                        <option value="{{ $veh->id }}">{{ $veh->registration_number }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label fw-bold">Component Type <span class="text-danger">*</span></label>
                                    <select wire:model="component_type" class="form-select" required>
                                        <option value="tyre">Tyre</option>
                                        <option value="battery">Battery</option>
                                        <option value="engine_oil">Engine Oil</option>
                                        <option value="filter">Filter</option>
                                        <option value="brake_pad">Brake Pad</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-bold">Brand Name <span class="text-danger">*</span></label>
                                    <input type="text" wire:model="brand_name" class="form-control" placeholder="e.g. Michelin / AGS" required>
                                </div>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label fw-bold">Position (for Tyres)</label>
                                    <select wire:model="position" class="form-select">
                                        <option value="front_left">Front Left</option>
                                        <option value="front_right">Front Right</option>
                                        <option value="rear_outer_left">Rear Outer Left</option>
                                        <option value="rear_inner_left">Rear Inner Left</option>
                                        <option value="rear_outer_right">Rear Outer Right</option>
                                        <option value="rear_inner_right">Rear Inner Right</option>
                                        <option value="spare">Spare Stepney</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-bold">Rated Expected Life (KM)</label>
                                    <input type="number" wire:model="expected_life_km" class="form-control" placeholder="80000">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="$set('showComponentModal', false)">Cancel</button>
                            <button type="submit" class="btn btn-secondary"><i class="fas fa-save me-1"></i> Register Component</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>

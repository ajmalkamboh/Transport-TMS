<div>
    {{-- Header & Stats --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6">
            <h3 class="mb-0 text-primary fw-bold"><i class="fas fa-route me-2"></i>Trip Planning & Dispatch Operations</h3>
            <p class="text-muted fs--1 mb-0">Nationwide Freight Movement, Multi-Consignment Dispatch, Fleet Combinations & Route Control</p>
        </div>
        <div class="col-12 col-md-6 text-md-end">
            <button wire:click="openCreateModal" class="btn btn-primary">
                <i class="fas fa-plus-circle me-1"></i> Plan New Trip
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
    @if(session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-1"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Filters Card --}}
    <div class="card mb-3">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" wire:model.live.debounce.300ms="search" class="form-control" placeholder="Search by Trip #, Origin, Destination, Route...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select wire:model.live="statusFilter" class="form-select">
                        <option value="">All Operational Statuses</option>
                        <option value="planned">Planned</option>
                        <option value="loading">Loading</option>
                        <option value="in_transit">In Transit (Dispatched)</option>
                        <option value="delivered">Delivered</option>
                        <option value="operationally_completed">Operationally Completed</option>
                        <option value="closed">Financially Closed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <select wire:model.live="branchFilter" class="form-select">
                        <option value="">All Operating Branches</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    {{-- Trips Table Card --}}
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Trip # & Date</th>
                        <th>Origin &rarr; Destination</th>
                        <th>Deployed Fleet & Crew</th>
                        <th>Allocated Cargo</th>
                        <th>Operational Status</th>
                        <th>Financial Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($trips as $trip)
                        <tr>
                            <td>
                                <a href="javascript:void(0)" wire:click="viewDetails({{ $trip->id }})" class="fw-bold text-primary text-decoration-none">
                                    {{ $trip->trip_number }}
                                </a>
                                <div class="fs--2 text-muted">
                                    <i class="far fa-calendar-alt me-1"></i>{{ $trip->trip_date->format('d M Y') }}
                                    @if($trip->branch) &bull; {{ $trip->branch->name }} @endif
                                </div>
                            </td>
                            <td>
                                <div class="fw-semi-bold">
                                    <span class="text-dark">{{ $trip->origin_city }}</span>
                                    <i class="fas fa-arrow-right text-muted mx-1"></i>
                                    <span class="text-dark">{{ $trip->destination_city }}</span>
                                </div>
                                <div class="fs--2 text-muted">{{ $trip->planned_route ?: 'Direct Highway' }}</div>
                            </td>
                            <td>
                                @php
                                    $primaryVeh = $trip->primaryVehicle();
                                    $primaryDrv = $trip->primaryDriver();
                                @endphp
                                @if($primaryVeh)
                                    <div><i class="fas fa-truck text-primary me-1"></i><strong>{{ $primaryVeh->registration_number }}</strong></div>
                                @else
                                    <span class="badge bg-warning text-dark fs--2"><i class="fas fa-exclamation me-1"></i>No Truck</span>
                                @endif
                                @if($primaryDrv)
                                    <div class="fs--2 text-muted"><i class="fas fa-user-circle me-1"></i>{{ $primaryDrv->name }}</div>
                                @else
                                    <span class="badge bg-warning text-dark fs--2"><i class="fas fa-exclamation me-1"></i>No Driver</span>
                                @endif
                            </td>
                            <td>
                                <div class="fw-bold">{{ $trip->total_packages_count }} Pkgs</div>
                                <div class="fs--2 text-muted">{{ $trip->total_consignments_count }} Consignments &bull; {{ $trip->total_weight_tons }} Tons</div>
                            </td>
                            <td>
                                @if($trip->trip_status === 'planned')
                                    <span class="badge bg-secondary">PLANNED</span>
                                @elseif($trip->trip_status === 'loading')
                                    <span class="badge bg-info text-dark">LOADING</span>
                                @elseif($trip->trip_status === 'in_transit')
                                    <span class="badge bg-primary">IN TRANSIT</span>
                                @elseif($trip->trip_status === 'delivered')
                                    <span class="badge bg-success">DELIVERED</span>
                                @elseif($trip->trip_status === 'operationally_completed')
                                    <span class="badge bg-success"><i class="fas fa-check-double me-1"></i>OP COMPLETED</span>
                                @elseif($trip->trip_status === 'closed')
                                    <span class="badge bg-dark">CLOSED</span>
                                @else
                                    <span class="badge bg-light text-dark">{{ strtoupper($trip->trip_status) }}</span>
                                @endif
                                @if($trip->departure_odometer_km)
                                    <div class="fs--2 text-muted">{{ number_format($trip->departure_odometer_km) }} &rarr; {{ $trip->arrival_odometer_km ? number_format($trip->arrival_odometer_km) : '...' }} KM</div>
                                @endif
                            </td>
                            <td>
                                @if($trip->financial_status === 'closed')
                                    <span class="badge bg-dark">AUDITED & CLOSED</span>
                                @else
                                    <span class="badge bg-warning text-dark">PENDING SETTLEMENT</span>
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                <div class="dropdown font-sans-serif btn-reveal-trigger d-inline-block">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        Manage
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                        <li><a class="dropdown-item" href="javascript:void(0)" wire:click="viewDetails({{ $trip->id }})"><i class="fas fa-eye me-2 text-primary"></i>Trip Overview</a></li>
                                        <li><a class="dropdown-item" href="javascript:void(0)" wire:click="openResourceModal({{ $trip->id }})"><i class="fas fa-truck-moving me-2 text-info"></i>Assign Fleet & Crew</a></li>
                                        <li><a class="dropdown-item" href="javascript:void(0)" wire:click="openAllocationModal({{ $trip->id }})"><i class="fas fa-boxes me-2 text-warning"></i>Allocate Consignments</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item" href="javascript:void(0)" wire:click="openLoadingModal({{ $trip->id }})"><i class="fas fa-dolly me-2 text-success"></i>Cargo Loading Sign-off</a></li>
                                        <li><a class="dropdown-item" href="{{ route('print.loading-slip', $trip->id) }}" target="_blank"><i class="fas fa-print me-2 text-muted"></i>Print Loading Slip</a></li>
                                        <li><a class="dropdown-item" href="javascript:void(0)" wire:click="openDispatchModal({{ $trip->id }})"><i class="fas fa-paper-plane me-2 text-primary"></i>Execute Dispatch</a></li>
                                        <li><a class="dropdown-item" href="{{ route('print.trip-sheet', $trip->id) }}" target="_blank"><i class="fas fa-file-alt me-2 text-muted"></i>Print Trip Sheet</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item" href="javascript:void(0)" wire:click="openCheckpointModal({{ $trip->id }})"><i class="fas fa-map-marker-alt me-2 text-danger"></i>Route Checkpoints</a></li>
                                        <li><a class="dropdown-item" href="javascript:void(0)" wire:click="openCompleteModal({{ $trip->id }})"><i class="fas fa-check-double me-2 text-success"></i>Operational Completion</a></li>
                                        @if($trip->isOperationallyCompleted() && !$trip->isFinanciallyClosed())
                                            <li><a class="dropdown-item" href="javascript:void(0)" wire:click="openFinanceModal({{ $trip->id }})"><i class="fas fa-lock me-2 text-dark"></i>Financial Closure</a></li>
                                        @endif
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="fas fa-route fa-2x mb-2 d-block"></i>
                                No trips matching your filter criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-light p-2">
            {{ $trips->links() }}
        </div>
    </div>

    {{-- 1. Create Trip Modal --}}
    @if($showCreateModal)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="fas fa-route me-2"></i>Plan New Transport Trip</h5>
                        <button type="button" class="btn-close btn-close-white" wire:click="$set('showCreateModal', false)"></button>
                    </div>
                    <form wire:submit.prevent="createTrip">
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Operating Branch</label>
                                    <select wire:model="branch_id" class="form-select">
                                        <option value="">Head Office / Central Dispatch</option>
                                        @foreach($branches as $br)
                                            <option value="{{ $br->id }}">{{ $br->name }} ({{ $br->code }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Trip Date <span class="text-danger">*</span></label>
                                    <input type="date" wire:model="trip_date" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Origin Terminal / City <span class="text-danger">*</span></label>
                                    <input type="text" wire:model="origin_city" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Destination Terminal / City <span class="text-danger">*</span></label>
                                    <input type="text" wire:model="destination_city" class="form-control" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold">Planned Route / Highway</label>
                                    <input type="text" wire:model="planned_route" class="form-control" placeholder="e.g. Karachi -> Hyderabad -> Sukkur -> Multan -> Lahore">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Estimated Departure</label>
                                    <input type="datetime-local" wire:model="estimated_departure" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Estimated Arrival</label>
                                    <input type="datetime-local" wire:model="estimated_arrival" class="form-control">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold">Operational Notes</label>
                                    <textarea wire:model="trip_notes" class="form-control" rows="2" placeholder="Driver instructions, security protocol, checkpoint requirements..."></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="$set('showCreateModal', false)">Cancel</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-check-circle me-1"></i> Create Trip Plan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- 2. Resource Assignment Modal --}}
    @if($showResourceModal && $selectedTrip)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title"><i class="fas fa-truck-moving me-2"></i>Assign Fleet Resources & Crew &bull; {{ $selectedTrip->trip_number }}</h5>
                        <button type="button" class="btn-close btn-close-white" wire:click="$set('showResourceModal', false)"></button>
                    </div>
                    <div class="modal-body">
                        @if(session()->has('resource_success'))
                            <div class="alert alert-success p-2 fs--1">{{ session('resource_success') }}</div>
                        @endif
                        @if(session()->has('resource_error'))
                            <div class="alert alert-danger p-2 fs--1"><i class="fas fa-ban me-1"></i>{{ session('resource_error') }}</div>
                        @endif

                        <h6 class="fw-bold mb-2">Current Active Deployments</h6>
                        <table class="table table-sm table-bordered align-middle mb-3">
                            <thead class="table-light">
                                <tr>
                                    <th>Role</th>
                                    <th>Resource Details</th>
                                    <th>Assigned At</th>
                                    <th>Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($selectedTrip->resources as $res)
                                    <tr>
                                        <td><span class="badge bg-secondary">{{ strtoupper(str_replace('_', ' ', $res->role)) }}</span></td>
                                        <td>
                                            @if($res->resource_type === 'vehicle')
                                                <strong>{{ $res->vehicle?->registration_number }}</strong> ({{ $res->vehicle?->make_model }})
                                            @else
                                                <strong>{{ $res->driver?->name }}</strong> (Cell: {{ $res->driver?->phone }})
                                            @endif
                                            @if($res->override_allowed)
                                                <span class="badge bg-warning text-dark fs--2">Override: {{ $res->override_reason }}</span>
                                            @endif
                                        </td>
                                        <td class="fs--2">{{ $res->assigned_at->format('d M H:i') }}</td>
                                        <td>
                                            @if($res->is_active)
                                                <span class="badge bg-success">ACTIVE</span>
                                            @else
                                                <span class="badge bg-secondary">RELEASED</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @if($res->is_active)
                                                <button wire:click="releaseResource({{ $res->id }})" class="btn btn-sm btn-outline-danger py-0 fs--2">
                                                    Release
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted">No resources assigned yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>

                        <hr>
                        <h6 class="fw-bold mb-2">Deploy Additional Vehicle or Driver</h6>
                        <form wire:submit.prevent="assignResource">
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <label class="form-label fs--1 fw-bold">Resource Type</label>
                                    <select wire:model.live="resource_type" class="form-select form-select-sm">
                                        <option value="vehicle">Vehicle / Truck / Trailer</option>
                                        <option value="driver">Commercial Driver</option>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label fs--1 fw-bold">Select {{ ucfirst($resource_type) }}</label>
                                    @if($resource_type === 'vehicle')
                                        <select wire:model="resource_id" class="form-select form-select-sm" required>
                                            <option value="">-- Choose Vehicle --</option>
                                            @foreach($availableVehicles as $veh)
                                                <option value="{{ $veh->id }}">{{ $veh->registration_number }} &bull; {{ $veh->make_model }} (Status: {{ $veh->status }})</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <select wire:model="resource_id" class="form-select form-select-sm" required>
                                            <option value="">-- Choose Driver --</option>
                                            @foreach($availableDrivers as $drv)
                                                <option value="{{ $drv->id }}">{{ $drv->name }} &bull; {{ $drv->license_number }} (Status: {{ $drv->status }})</option>
                                            @endforeach
                                        </select>
                                    @endif
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-bold">Role Assignment</label>
                                    <select wire:model="role" class="form-select form-select-sm">
                                        @if($resource_type === 'vehicle')
                                            <option value="primary_vehicle">Primary Prime Mover</option>
                                            <option value="trailer">Attached Semi-Trailer</option>
                                            <option value="secondary_vehicle">Support / Escort Vehicle</option>
                                        @else
                                            <option value="primary_driver">Primary Commercial Driver</option>
                                            <option value="co_driver">Relief / Co-Driver</option>
                                        @endif
                                    </select>
                                </div>
                                <div class="col-12 mt-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" wire:model.live="override_allowed" id="overrideCheck">
                                        <label class="form-check-label fs--1" for="overrideCheck">
                                            Allow Authorized Conflict Override (Dispatch even if resource is marked on trip)
                                        </label>
                                    </div>
                                    @if($override_allowed)
                                        <input type="text" wire:model="override_reason" class="form-control form-control-sm mt-1" placeholder="Mandatory override authorization reason..." required>
                                    @endif
                                </div>
                                <div class="col-12 text-end mt-2">
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        <i class="fas fa-link me-1"></i> Confirm Resource Assignment
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="$set('showResourceModal', false)">Close</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- 3. Consignment Allocation Modal --}}
    @if($showAllocationModal && $selectedTrip)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title"><i class="fas fa-boxes me-2"></i>Multi-Consignment Cargo Allocation &bull; {{ $selectedTrip->trip_number }}</h5>
                        <button type="button" class="btn-close" wire:click="$set('showAllocationModal', false)"></button>
                    </div>
                    <div class="modal-body">
                        @if(session()->has('allocation_success'))
                            <div class="alert alert-success p-2 fs--1">{{ session('allocation_success') }}</div>
                        @endif
                        @if(session()->has('allocation_error'))
                            <div class="alert alert-danger p-2 fs--1">{{ session('allocation_error') }}</div>
                        @endif

                        <h6 class="fw-bold mb-2">Allocated Consignments on this Trip</h6>
                        <table class="table table-sm table-bordered align-middle mb-3">
                            <thead class="table-light">
                                <tr>
                                    <th>Bilty #</th>
                                    <th>Customer & Consignee</th>
                                    <th class="text-center">Assigned Pkgs</th>
                                    <th class="text-end">Weight (KG)</th>
                                    <th>Loading Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($selectedTrip->tripConsignments as $alloc)
                                    <tr>
                                        <td><strong>{{ $alloc->consignment?->consignment_number }}</strong></td>
                                        <td>{{ $alloc->consignment?->customer?->name }} &rarr; {{ $alloc->consignment?->consignee_city }}</td>
                                        <td class="text-center fw-bold">{{ $alloc->assigned_quantity }}</td>
                                        <td class="text-end">{{ number_format($alloc->weight_kg, 1) }}</td>
                                        <td><span class="badge bg-light text-dark border">{{ strtoupper($alloc->loading_status) }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted">No consignments allocated yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>

                        <hr>
                        <h6 class="fw-bold mb-2">Allocate Consignment to Trip</h6>
                        <form wire:submit.prevent="allocateConsignment">
                            <div class="row g-2">
                                <div class="col-md-8">
                                    <label class="form-label fs--1 fw-bold">Select Active Consignment (Bilty)</label>
                                    <select wire:model="selected_consignment_id" class="form-select form-select-sm" required>
                                        <option value="">-- Choose Consignment --</option>
                                        @foreach($availableConsignments as $cs)
                                            <option value="{{ $cs->id }}">
                                                {{ $cs->consignment_number }} &bull; {{ $cs->customer?->name }} (Remaining: {{ $cs->remaining_quantity }} pkgs / {{ number_format($cs->chargeable_weight_kg) }} kg) &rarr; {{ $cs->consignee_city }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-bold">Quantity (Pkgs to Load)</label>
                                    <input type="number" wire:model="allocated_quantity" class="form-control form-control-sm" min="1" required>
                                </div>
                                <div class="col-12 text-end mt-2">
                                    <button type="submit" class="btn btn-sm btn-warning">
                                        <i class="fas fa-plus me-1"></i> Add Cargo to Trip
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="$set('showAllocationModal', false)">Close</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- 4. Loading Modal --}}
    @if($showLoadingModal && $selectedTrip)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title"><i class="fas fa-dolly me-2"></i>Cargo Loading Sign-off &bull; {{ $selectedTrip->trip_number }}</h5>
                        <button type="button" class="btn-close btn-close-white" wire:click="$set('showLoadingModal', false)"></button>
                    </div>
                    <form wire:submit.prevent="completeLoading">
                        <div class="modal-body">
                            <div class="p-2 border rounded bg-light mb-3">
                                <div class="fs--1">Total Consignments: <strong>{{ $selectedTrip->tripConsignments->count() }}</strong></div>
                                <div class="fs--1">Total Loaded Packages: <strong>{{ $selectedTrip->total_packages_count }} Pkgs</strong></div>
                                <div class="fs--1">Gross Tonnage: <strong>{{ $selectedTrip->total_weight_tons }} Tons</strong></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Loading Supervisor <span class="text-danger">*</span></label>
                                <input type="text" wire:model="loading_supervisor" class="form-control" required>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label fs--1 fw-bold">Loading Started</label>
                                    <input type="datetime-local" wire:model="loading_started_at" class="form-control form-control-sm">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fs--1 fw-bold">Loading Completed</label>
                                    <input type="datetime-local" wire:model="loading_completed_at" class="form-control form-control-sm">
                                </div>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="verifyCargoCheck" required>
                                <label class="form-check-label fs--1" for="verifyCargoCheck">
                                    I certify that all allocated consignments have been inspected, weighed, and securely loaded onto the vehicle.
                                </label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="$set('showLoadingModal', false)">Cancel</button>
                            <button type="submit" class="btn btn-success"><i class="fas fa-check me-1"></i> Verify Loading & Generate Slip</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- 5. Dispatch Modal --}}
    @if($showDispatchModal && $selectedTrip)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="fas fa-paper-plane me-2"></i>Authorize Trip Dispatch &bull; {{ $selectedTrip->trip_number }}</h5>
                        <button type="button" class="btn-close btn-close-white" wire:click="$set('showDispatchModal', false)"></button>
                    </div>
                    <form wire:submit.prevent="dispatchTrip">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Departure Odometer (KM) <span class="text-danger">*</span></label>
                                <input type="number" wire:model="departure_odometer" class="form-control" min="0" required>
                                <div class="fs--2 text-muted">Current vehicle dashboard odometer reading at gate exit.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Actual Departure Time <span class="text-danger">*</span></label>
                                <input type="datetime-local" wire:model="actual_departure" class="form-control" required>
                            </div>
                            <div class="p-2 border rounded bg-light mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" wire:model="driver_signoff_confirmed" id="driverSignCheck" required>
                                    <label class="form-check-label fw-bold fs--1" for="driverSignCheck">
                                        Driver Handover Confirmed
                                    </label>
                                </div>
                                <div class="fs--2 text-muted mt-1">
                                    By checking this, you confirm that the driver has received the vehicle keys, physical cargo manifest (Trip Sheet), and authorized toll/fuel expenses.
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="$set('showDispatchModal', false)">Cancel</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-truck-moving me-1"></i> Dispatch Trip Now</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- 6. Checkpoint Modal --}}
    @if($showCheckpointModal && $selectedTrip)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title"><i class="fas fa-map-marker-alt me-2"></i>Route Checkpoints & Milestones &bull; {{ $selectedTrip->trip_number }}</h5>
                        <button type="button" class="btn-close btn-close-white" wire:click="$set('showCheckpointModal', false)"></button>
                    </div>
                    <div class="modal-body">
                        @if(session()->has('checkpoint_success'))
                            <div class="alert alert-success p-2 fs--1">{{ session('checkpoint_success') }}</div>
                        @endif

                        <h6 class="fw-bold mb-2">Milestone Log</h6>
                        <table class="table table-sm table-bordered align-middle mb-3">
                            <thead class="table-light">
                                <tr>
                                    <th>Stop #</th>
                                    <th>Checkpoint / City</th>
                                    <th>Arrival Time</th>
                                    <th>Odometer</th>
                                    <th>Delays / Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($selectedTrip->checkpoints as $cp)
                                    <tr>
                                        <td class="text-center">{{ $cp->stop_order }}</td>
                                        <td><strong>{{ $cp->checkpoint_name }}</strong></td>
                                        <td>{{ $cp->actual_arrival?->format('d M H:i') }}</td>
                                        <td>{{ $cp->odometer_km ? number_format($cp->odometer_km) . ' KM' : '-' }}</td>
                                        <td>
                                            @if($cp->delay_minutes > 0)
                                                <span class="badge bg-warning text-dark">{{ $cp->delay_minutes }}m delay</span>
                                                <span class="fs--2">{{ $cp->delay_reason }}</span>
                                            @else
                                                <span class="badge bg-success fs--2">On Time</span>
                                            @endif
                                            @if($cp->notes)<div class="fs--2 text-muted">{{ $cp->notes }}</div>@endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted">No checkpoints recorded yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>

                        <hr>
                        <h6 class="fw-bold mb-2">Log New Route Milestone</h6>
                        <form wire:submit.prevent="recordCheckpoint">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label fs--1 fw-bold">Checkpoint / Toll Plaza / Station <span class="text-danger">*</span></label>
                                    <input type="text" wire:model="checkpoint_name" class="form-control form-control-sm" placeholder="e.g. Sukkur Toll Plaza / Multan Hub" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fs--1 fw-bold">Odometer (KM)</label>
                                    <input type="number" wire:model="checkpoint_odometer" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-bold">Delay (Minutes)</label>
                                    <input type="number" wire:model="delay_minutes" class="form-control form-control-sm" min="0">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label fs--1 fw-bold">Delay Reason (if any)</label>
                                    <input type="text" wire:model="delay_reason" class="form-control form-control-sm" placeholder="Traffic jam, tire puncture, police check...">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fs--1 fw-bold">Milestone Notes</label>
                                    <input type="text" wire:model="checkpoint_notes" class="form-control form-control-sm" placeholder="Driver status, fuel level, weather...">
                                </div>
                                <div class="col-12 text-end mt-2">
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="fas fa-save me-1"></i> Record Milestone
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="$set('showCheckpointModal', false)">Close</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- 7. Operational Completion Modal --}}
    @if($showCompleteModal && $selectedTrip)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title"><i class="fas fa-check-double me-2"></i>Operational Completion &bull; {{ $selectedTrip->trip_number }}</h5>
                        <button type="button" class="btn-close btn-close-white" wire:click="$set('showCompleteModal', false)"></button>
                    </div>
                    <form wire:submit.prevent="completeOperationally">
                        <div class="modal-body">
                            <div class="alert alert-info fs--1">
                                <strong>Operational Closure Protocol:</strong> This action records destination arrival, closes trip odometer, and automatically releases all assigned vehicles and drivers back to "Available" status.
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Departure Odometer (KM)</label>
                                <input type="text" class="form-control bg-light" value="{{ number_format($selectedTrip->departure_odometer_km) }} KM" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Arrival Odometer (KM) <span class="text-danger">*</span></label>
                                <input type="number" wire:model="arrival_odometer" class="form-control" min="{{ $selectedTrip->departure_odometer_km ?? 0 }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Actual Arrival Timestamp <span class="text-danger">*</span></label>
                                <input type="datetime-local" wire:model="actual_arrival" class="form-control" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="$set('showCompleteModal', false)">Cancel</button>
                            <button type="submit" class="btn btn-success"><i class="fas fa-check-double me-1"></i> Complete Operationally & Release Fleet</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- 8. Financial Closure Modal --}}
    @if($showFinanceModal && $selectedTrip)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title"><i class="fas fa-lock me-2"></i>Final Financial Audit & Closure &bull; {{ $selectedTrip->trip_number }}</h5>
                        <button type="button" class="btn-close btn-close-white" wire:click="$set('showFinanceModal', false)"></button>
                    </div>
                    <form wire:submit.prevent="closeFinancially">
                        <div class="modal-body">
                            <div class="alert alert-warning fs--1">
                                <strong>Financial Lock:</strong> Once financially closed, all freight billing, driver expense settlements, and vehicle hire payable records for this trip are permanently finalized.
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Audit & Settlement Notes</label>
                                <textarea wire:model="financial_notes" class="form-control" rows="3" placeholder="Audit verification notes, final settlement balance confirmation..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="$set('showFinanceModal', false)">Cancel</button>
                            <button type="submit" class="btn btn-dark"><i class="fas fa-lock me-1"></i> Finalize Financial Closure</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- 9. Trip Detail Modal --}}
    @if($showDetailModal && $selectedTrip)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header bg-light">
                        <h5 class="modal-title text-primary"><i class="fas fa-route me-2"></i>Trip Details &bull; {{ $selectedTrip->trip_number }}</h5>
                        <button type="button" class="btn-close" wire:click="$set('showDetailModal', false)"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <div class="p-2 border rounded bg-light">
                                    <div class="fs--2 text-muted text-uppercase">Trip Date</div>
                                    <div class="fw-bold">{{ $selectedTrip->trip_date->format('d M Y') }}</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-2 border rounded bg-light">
                                    <div class="fs--2 text-muted text-uppercase">Route</div>
                                    <div class="fw-bold">{{ $selectedTrip->origin_city }} &rarr; {{ $selectedTrip->destination_city }}</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-2 border rounded bg-light">
                                    <div class="fs--2 text-muted text-uppercase">Odometer</div>
                                    <div class="fw-bold">{{ $selectedTrip->departure_odometer_km ? number_format($selectedTrip->departure_odometer_km) . ' KM' : 'N/A' }} &rarr; {{ $selectedTrip->arrival_odometer_km ? number_format($selectedTrip->arrival_odometer_km) . ' KM' : '...' }}</div>
                                    <div class="fs--2 text-muted">Distance: {{ $selectedTrip->total_distance_km ? number_format($selectedTrip->total_distance_km) . ' KM' : '-' }}</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-2 border rounded bg-light">
                                    <div class="fs--2 text-muted text-uppercase">Operational Status</div>
                                    <div><span class="badge bg-primary">{{ strtoupper($selectedTrip->trip_status) }}</span></div>
                                    <div class="fs--2 text-muted mt-1">Finance: {{ strtoupper($selectedTrip->financial_status) }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2 mb-3">
                            <a href="{{ route('print.loading-slip', $selectedTrip->id) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-print me-1"></i> Print Loading Slip
                            </a>
                            <a href="{{ route('print.trip-sheet', $selectedTrip->id) }}" target="_blank" class="btn btn-sm btn-outline-success">
                                <i class="fas fa-file-alt me-1"></i> Print Trip Sheet
                            </a>
                        </div>

                        <h6 class="fw-bold">Consignments Carried on this Trip</h6>
                        <table class="table table-sm table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Bilty #</th>
                                    <th>Customer (Sender)</th>
                                    <th>Consignee & Destination</th>
                                    <th class="text-center">Assigned Pkgs</th>
                                    <th class="text-center">Delivered Pkgs</th>
                                    <th>Delivery Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($selectedTrip->tripConsignments as $alloc)
                                    <tr>
                                        <td><strong>{{ $alloc->consignment?->consignment_number }}</strong></td>
                                        <td>{{ $alloc->consignment?->customer?->name }}</td>
                                        <td>{{ $alloc->consignment?->consignee_name }} ({{ $alloc->consignment?->consignee_city }})</td>
                                        <td class="text-center fw-bold">{{ $alloc->assigned_quantity }}</td>
                                        <td class="text-center fw-bold text-success">{{ $alloc->delivered_quantity }}</td>
                                        <td><span class="badge bg-light text-dark border">{{ strtoupper($alloc->delivery_status) }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="$set('showDetailModal', false)">Close</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>


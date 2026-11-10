<div>
    @if(session()->has('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Action & Filters Bar -->
    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <div class="row g-2 align-items-center justify-content-between">
                <div class="col-md-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="fas fa-search text-400"></i></span>
                        <input type="text" class="form-control" placeholder="Search plate #, make, model..." wire:model.live.debounce.300ms="search">
                    </div>
                </div>
                <div class="col-md-auto d-flex flex-wrap gap-2 align-items-center">
                    <select class="form-select form-select-sm" wire:model.live="ownershipFilter" style="width: 170px;">
                        <option value="">All Ownership Types</option>
                        <option value="company_owned">Company Owned</option>
                        <option value="market_attached">Market Attached</option>
                        <option value="rented">Rented / Leased</option>
                    </select>

                    <select class="form-select form-select-sm" wire:model.live="statusFilter" style="width: 150px;">
                        <option value="">All Statuses</option>
                        <option value="available">Available</option>
                        <option value="on_trip">On Trip</option>
                        <option value="maintenance">Maintenance</option>
                        <option value="inactive">Inactive</option>
                    </select>

                    <span class="fs--1 text-600 ms-1 me-2">
                        Fleet Count: <strong>{{ $vehicles->total() }}</strong>
                        @if($company->plan)
                            (Cap: <strong>{{ $company->plan->max_vehicles === -1 ? 'Unlimited' : $company->plan->max_vehicles }}</strong>)
                        @endif
                    </span>

                    @if($canAddVehicle)
                        <button type="button" class="btn btn-primary btn-sm shadow-sm" wire:click="openCreateModal">
                            <i class="fas fa-plus-circle me-1"></i> Register Vehicle
                        </button>
                    @else
                        <button type="button" class="btn btn-secondary btn-sm" disabled title="Fleet quota reached for current plan">
                            <i class="fas fa-lock me-1"></i> Fleet Limit Reached
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Vehicles Table -->
    <div class="card shadow-sm mb-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped fs--1 mb-0 align-middle">
                    <thead class="bg-200 text-900">
                        <tr>
                            <th>Registration / Fleet #</th>
                            <th>Ownership & Category</th>
                            <th>Make & Model</th>
                            <th>Payload & Volume</th>
                            <th>Assigned Driver</th>
                            <th>Compliance & Expiry</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($vehicles as $v)
                            <tr>
                                <td>
                                    <div class="fw-bold text-primary font-monospace">{{ $v->registration_number }}</div>
                                    @if($v->fleet_number)
                                        <div class="fs--2 text-muted">Fleet ID: <span class="badge bg-light text-dark">{{ $v->fleet_number }}</span></div>
                                    @endif
                                </td>
                                <td>
                                    @if($v->isCompanyOwned())
                                        <span class="badge bg-soft-primary text-primary"><i class="fas fa-shield-alt me-1"></i> Company Owned</span>
                                    @else
                                        <span class="badge bg-soft-warning text-warning"><i class="fas fa-handshake me-1"></i> Market Attached</span>
                                        @if($v->owner)
                                            <div class="fs--2 text-dark fw-semi-bold">{{ $v->owner->name }}</div>
                                        @endif
                                    @endif
                                    <div class="fs--2 text-500">{{ $v->vehicle_category }} &bull; {{ $v->vehicle_type }}</div>
                                </td>
                                <td>
                                    <div class="fw-semi-bold">{{ $v->make ?: 'N/A' }} {{ $v->model }}</div>
                                    <div class="fs--2 text-muted">Year: {{ $v->manufacturing_year ?: 'N/A' }} &bull; Odo: {{ number_format($v->current_odometer_km) }} KM</div>
                                </td>
                                <td>
                                    <div><strong>{{ number_format($v->payload_capacity_tons, 1) }}</strong> Tons</div>
                                    @if($v->volume_capacity_cbm)
                                        <div class="fs--2 text-muted">{{ number_format($v->volume_capacity_cbm, 1) }} CBM</div>
                                    @endif
                                </td>
                                <td>
                                    @if($v->currentDriver)
                                        <div class="fw-bold text-dark"><i class="fas fa-user text-primary me-1"></i> {{ $v->currentDriver->name }}</div>
                                        <div class="fs--2 text-muted">{{ $v->currentDriver->phone }}</div>
                                    @else
                                        <span class="badge bg-soft-secondary text-secondary">No Driver Assigned</span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $fitnessExpired = $v->fitness_expiry_date && $v->fitness_expiry_date->isPast();
                                        $insuranceExpired = $v->insurance_expiry_date && $v->insurance_expiry_date->isPast();
                                    @endphp
                                    <div>
                                        <span class="fs--2 text-muted">Fitness:</span>
                                        @if($v->fitness_expiry_date)
                                            <span class="badge {{ $fitnessExpired ? 'bg-danger' : 'bg-soft-success text-success' }} fs--2">
                                                {{ $v->fitness_expiry_date->format('M d, Y') }}
                                            </span>
                                        @else
                                            <span class="fs--2 text-muted">N/A</span>
                                        @endif
                                    </div>
                                    <div class="mt-1">
                                        <span class="fs--2 text-muted">Insurance:</span>
                                        @if($v->insurance_expiry_date)
                                            <span class="badge {{ $insuranceExpired ? 'bg-danger' : 'bg-soft-info text-info' }} fs--2">
                                                {{ $v->insurance_expiry_date->format('M d, Y') }}
                                            </span>
                                        @else
                                            <span class="fs--2 text-muted">N/A</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    @if($v->status === 'available')
                                        <span class="badge bg-soft-success text-success">Available</span>
                                    @elseif($v->status === 'on_trip')
                                        <span class="badge bg-soft-primary text-primary">On Trip</span>
                                    @elseif($v->status === 'maintenance')
                                        <span class="badge bg-soft-warning text-warning">Maintenance</span>
                                    @else
                                        <span class="badge bg-soft-secondary text-secondary">{{ ucfirst($v->status) }}</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2 fs--2" wire:click="openAssignDriverModal({{ $v->id }})" title="Assign Driver">
                                            <i class="fas fa-user-plus"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 fs--2" wire:click="editVehicle({{ $v->id }})" title="Edit Vehicle">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">No fleet vehicles found matching your criteria.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($vehicles->hasPages())
            <div class="card-footer bg-light py-2">
                {{ $vehicles->links() }}
            </div>
        @endif
    </div>

    <!-- Create / Edit Vehicle Modal -->
    @if($showModal)
    <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-truck me-1 text-primary"></i>
                        {{ $isEditing ? 'Edit Vehicle: ' . $registration_number : 'Register New Fleet Vehicle' }}
                    </h5>
                    <button type="button" class="btn-close" wire:click="$set('showModal', false)"></button>
                </div>
                <form wire:submit.prevent="saveVehicle">
                    <div class="modal-body">
                        <div class="row g-3">
                            <!-- Registration & Ownership -->
                            <div class="col-md-3">
                                <label class="form-label fs--1 fw-semi-bold">Registration Plate # <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm @error('registration_number') is-invalid @enderror" wire:model="registration_number" placeholder="e.g. LES-19-4820">
                                @error('registration_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fs--1 fw-semi-bold">Internal Fleet ID</label>
                                <input type="text" class="form-control form-control-sm" wire:model="fleet_number" placeholder="e.g. TRK-01">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fs--1 fw-semi-bold">Ownership Model <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm" wire:model.live="ownership_type">
                                    <option value="company_owned">Company Owned</option>
                                    <option value="market_attached">Market Attached Truck</option>
                                    <option value="rented">Rented / Long-Term Leased</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fs--1 fw-semi-bold">Home Branch</label>
                                <select class="form-select form-select-sm" wire:model="branch_id">
                                    <option value="">-- Unassigned --</option>
                                    @foreach($branches as $b)
                                        <option value="{{ $b->id }}">{{ $b->name }} ({{ $b->city }})</option>
                                    @endforeach
                                </select>
                            </div>

                            @if($ownership_type !== 'company_owned')
                            <div class="col-md-6 bg-light p-3 rounded border">
                                <label class="form-label fs--1 fw-semi-bold">Linked Vehicle Owner <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm @error('vehicle_owner_id') is-invalid @enderror" wire:model.live="vehicle_owner_id">
                                    <option value="">-- Select Owner --</option>
                                    @foreach($owners as $o)
                                        <option value="{{ $o->id }}">{{ $o->name }} ({{ $o->phone }})</option>
                                    @endforeach
                                </select>
                                @error('vehicle_owner_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6 bg-light p-3 rounded border">
                                <label class="form-label fs--1 fw-semi-bold">Active Owner Contract</label>
                                <select class="form-select form-select-sm" wire:model="active_contract_id">
                                    <option value="">-- No Active Contract --</option>
                                    @foreach($contracts as $c)
                                        <option value="{{ $c->id }}">{{ $c->contract_number }} ({{ $c->payment_model }})</option>
                                    @endforeach
                                </select>
                            </div>
                            @endif

                            <!-- Technical Specs -->
                            <div class="col-md-3">
                                <label class="form-label fs--1 fw-semi-bold">Vehicle Category <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm" wire:model="vehicle_category">
                                    <option value="HCV">HCV (Heavy Commercial)</option>
                                    <option value="MCV">MCV (Medium Commercial)</option>
                                    <option value="LCV">LCV (Light Commercial)</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fs--1 fw-semi-bold">Body / Truck Configuration <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm" wire:model="vehicle_type">
                                    <option value="TRAILER_22W">22-Wheeler Articulated Trailer</option>
                                    <option value="FLATBED_10W">10-Wheeler Rigid Flatbed</option>
                                    <option value="BOX_6W">6-Wheeler Enclosed Box Truck</option>
                                    <option value="CONTAINER_SKELETAL">40ft Container Skeletal Chassis</option>
                                    <option value="REEFER_TRUCK">Reefer Refrigerated Truck</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fs--1 fw-semi-bold">Make</label>
                                <input type="text" class="form-control form-control-sm" wire:model="make" placeholder="e.g. Hino, Isuzu, Volvo">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fs--1 fw-semi-bold">Model</label>
                                <input type="text" class="form-control form-control-sm" wire:model="model" placeholder="e.g. 500 Series, FH16">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fs--1 fw-semi-bold">Payload Capacity (Tons) <span class="text-danger">*</span></label>
                                <input type="number" step="0.5" class="form-control form-control-sm @error('payload_capacity_tons') is-invalid @enderror" wire:model="payload_capacity_tons">
                                @error('payload_capacity_tons') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fs--1 fw-semi-bold">Volume Capacity (CBM)</label>
                                <input type="number" step="0.5" class="form-control form-control-sm" wire:model="volume_capacity_cbm">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fs--1 fw-semi-bold">Fuel Type <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm" wire:model="fuel_type">
                                    <option value="DIESEL">High Speed Diesel (HSD)</option>
                                    <option value="PETROL">Petrol</option>
                                    <option value="CNG">CNG</option>
                                    <option value="ELECTRIC">Commercial EV</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fs--1 fw-semi-bold">Current Odometer (KM)</label>
                                <input type="number" class="form-control form-control-sm" wire:model="current_odometer_km">
                            </div>

                            <!-- Compliance & Expiry Dates -->
                            <div class="col-12"><hr class="my-1"><div class="fw-bold fs--1 text-600 mb-2">Compliance Document Expiries</div></div>

                            <div class="col-md-3">
                                <label class="form-label fs--2 fw-semi-bold">Registration Expiry</label>
                                <input type="date" class="form-control form-control-sm" wire:model="registration_expiry_date">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fs--2 fw-semi-bold">Insurance Expiry</label>
                                <input type="date" class="form-control form-control-sm" wire:model="insurance_expiry_date">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fs--2 fw-semi-bold">Fitness Certificate Expiry</label>
                                <input type="date" class="form-control form-control-sm" wire:model="fitness_expiry_date">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fs--2 fw-semi-bold">Route Permit Expiry</label>
                                <input type="date" class="form-control form-control-sm" wire:model="route_permit_expiry_date">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fs--1 fw-semi-bold">Chassis Number</label>
                                <input type="text" class="form-control form-control-sm" wire:model="chassis_number">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fs--1 fw-semi-bold">Engine Number</label>
                                <input type="text" class="form-control form-control-sm" wire:model="engine_number">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fs--1 fw-semi-bold">Initial Status</label>
                                <select class="form-select form-select-sm" wire:model="status">
                                    <option value="available">Available for Dispatch</option>
                                    <option value="on_trip">On Trip</option>
                                    <option value="maintenance">Maintenance</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="$set('showModal', false)">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-save me-1"></i> {{ $isEditing ? 'Save Changes' : 'Register Vehicle' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    <!-- Assign Driver Modal -->
    @if($showDriverModal)
    <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-id-card me-1 text-primary"></i>
                        Assign Driver to {{ $assigningVehicle?->registration_number }}
                    </h5>
                    <button type="button" class="btn-close" wire:click="$set('showDriverModal', false)"></button>
                </div>
                <form wire:submit.prevent="assignDrivers">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fs--1 fw-semi-bold">Primary Driver <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm @error('primary_driver_id') is-invalid @enderror" wire:model="primary_driver_id">
                                    <option value="">-- Select Available Primary Driver --</option>
                                    @foreach($drivers as $d)
                                        <option value="{{ $d->id }}">{{ $d->name }} ({{ $d->license_category }}: {{ $d->license_number }})</option>
                                    @endforeach
                                </select>
                                @error('primary_driver_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label fs--1 fw-semi-bold">Co-Driver / Relief Driver (Optional for Long-Haul)</label>
                                <select class="form-select form-select-sm @error('co_driver_id') is-invalid @enderror" wire:model="co_driver_id">
                                    <option value="">-- No Co-Driver --</option>
                                    @foreach($drivers as $d)
                                        @if($d->id !== $primary_driver_id)
                                            <option value="{{ $d->id }}">{{ $d->name }} ({{ $d->license_category }})</option>
                                        @endif
                                    @endforeach
                                </select>
                                @error('co_driver_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label fs--1 fw-semi-bold">Assignment Notes</label>
                                <textarea class="form-control form-control-sm" wire:model="assignment_notes" rows="2" placeholder="Route details, special equipment, trip duty instructions"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2 d-flex justify-content-between">
                        @if($assigningVehicle?->current_driver_id)
                            <button type="button" class="btn btn-outline-danger btn-sm" wire:click="unassignDrivers({{ $assigningVehicle->id }})">
                                <i class="fas fa-user-times me-1"></i> Remove Current Driver
                            </button>
                        @else
                            <div></div>
                        @endif
                        <div>
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="$set('showDriverModal', false)">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-sm">Confirm Driver Duty</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>

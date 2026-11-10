<div>
    @if(session()->has('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Action Bar -->
    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <div class="row g-2 align-items-center justify-content-between">
                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="fas fa-search text-400"></i></span>
                        <input type="text" class="form-control" placeholder="Search owner name, CNIC, phone..." wire:model.live.debounce.300ms="search">
                    </div>
                </div>
                <div class="col-md-auto d-flex gap-2 align-items-center">
                    <select class="form-select form-select-sm" wire:model.live="statusFilter" style="width: 150px;">
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="blacklisted">Blacklisted</option>
                    </select>

                    <button type="button" class="btn btn-primary btn-sm shadow-sm" wire:click="openCreateOwnerModal">
                        <i class="fas fa-user-plus me-1"></i> Register Vehicle Owner
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Owners Table -->
    <div class="card shadow-sm mb-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped fs--1 mb-0 align-middle">
                    <thead class="bg-200 text-900">
                        <tr>
                            <th>Owner Name & Company</th>
                            <th>CNIC / NTN</th>
                            <th>Contact Phone & Email</th>
                            <th>Attached Trucks</th>
                            <th>Banking Details</th>
                            <th>Active Contract</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($owners as $owner)
                            <tr>
                                <td>
                                    <div class="fw-bold text-primary">{{ $owner->name }}</div>
                                    @if($owner->company_name)
                                        <div class="fs--2 text-muted">{{ $owner->company_name }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="font-monospace">{{ $owner->cnic_number ?: 'N/A' }}</div>
                                    @if($owner->tax_number)
                                        <div class="fs--2 text-muted">NTN: {{ $owner->tax_number }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div>{{ $owner->phone }}</div>
                                    <div class="fs--2 text-muted">{{ $owner->email ?: 'No email' }}</div>
                                </td>
                                <td>
                                    <span class="badge bg-soft-info text-info fs--2">
                                        <i class="fas fa-truck me-1"></i> {{ $owner->vehicles_count }} Trucks
                                    </span>
                                </td>
                                <td>
                                    @if($owner->bank_name)
                                        <div class="fw-semi-bold">{{ $owner->bank_name }}</div>
                                        <div class="fs--2 text-muted font-monospace">{{ $owner->bank_account_number }}</div>
                                    @else
                                        <span class="text-muted fs--2">Not provided</span>
                                    @endif
                                </td>
                                <td>
                                    @php $activeCon = $owner->activeContract; @endphp
                                    @if($activeCon)
                                        <span class="badge bg-soft-success text-success">{{ $activeCon->contract_number }}</span>
                                        <div class="fs--2 text-muted">{{ str_replace('_', ' ', ucfirst($activeCon->payment_model)) }}</div>
                                    @else
                                        <span class="badge bg-soft-warning text-warning">No Contract</span>
                                    @endif
                                </td>
                                <td>
                                    @if($owner->status === 'active')
                                        <span class="badge bg-soft-success text-success">Active</span>
                                    @elseif($owner->status === 'blacklisted')
                                        <span class="badge bg-danger text-white">Blacklisted</span>
                                    @else
                                        <span class="badge bg-soft-secondary text-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-info btn-sm py-0 px-2 fs--2" wire:click="viewOwner({{ $owner->id }})" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-success btn-sm py-0 px-2 fs--2" wire:click="openContractModal({{ $owner->id }})" title="Create Contract">
                                            <i class="fas fa-file-contract"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 fs--2" wire:click="editOwner({{ $owner->id }})" title="Edit Profile">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">No vehicle owners registered yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($owners->hasPages())
            <div class="card-footer bg-light py-2">
                {{ $owners->links() }}
            </div>
        @endif
    </div>

    <!-- Create / Edit Owner Modal -->
    @if($showOwnerModal)
    <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-user-tie me-1 text-primary"></i>
                        {{ $isEditingOwner ? 'Edit Owner Profile' : 'Register Vehicle Owner' }}
                    </h5>
                    <button type="button" class="btn-close" wire:click="$set('showOwnerModal', false)"></button>
                </div>
                <form wire:submit.prevent="saveOwner">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">Owner Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm @error('name') is-invalid @enderror" wire:model="name" placeholder="e.g. Chaudhry Nadeem Akhtar">
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">Company / Business Name</label>
                                <input type="text" class="form-control form-control-sm" wire:model="company_name" placeholder="e.g. Nadeem Freight Forwarders">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fs--1 fw-semi-bold">CNIC / Identity #</label>
                                <input type="text" class="form-control form-control-sm" wire:model="cnic_number" placeholder="35201-1234567-1">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fs--1 fw-semi-bold">Primary Phone <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm @error('phone') is-invalid @enderror" wire:model="phone" placeholder="+92-300-1234567">
                                @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fs--1 fw-semi-bold">Secondary Phone</label>
                                <input type="text" class="form-control form-control-sm" wire:model="secondary_phone">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">Email Address</label>
                                <input type="email" class="form-control form-control-sm" wire:model="email">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">City / District</label>
                                <input type="text" class="form-control form-control-sm" wire:model="city" placeholder="e.g. Rawalpindi">
                            </div>

                            <div class="col-12"><hr class="my-1"><div class="fw-bold fs--1 text-600 mb-2">Banking & Settlement Coordinates</div></div>

                            <div class="col-md-4">
                                <label class="form-label fs--1 fw-semi-bold">Bank Name</label>
                                <input type="text" class="form-control form-control-sm" wire:model="bank_name" placeholder="e.g. Habib Bank Ltd">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fs--1 fw-semi-bold">Account Title</label>
                                <input type="text" class="form-control form-control-sm" wire:model="bank_account_title">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fs--1 fw-semi-bold">Account / IBAN Number</label>
                                <input type="text" class="form-control form-control-sm font-monospace" wire:model="bank_account_number" placeholder="PK00HABB0000...">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">Owner Status</label>
                                <select class="form-select form-select-sm" wire:model="status">
                                    <option value="active">Active (Eligible for Hire)</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="blacklisted">Blacklisted</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">Tax NTN #</label>
                                <input type="text" class="form-control form-control-sm" wire:model="tax_number">
                            </div>

                            <div class="col-12">
                                <label class="form-label fs--1 fw-semi-bold">Address & Terminal Location</label>
                                <textarea class="form-control form-control-sm" wire:model="address" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="$set('showOwnerModal', false)">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-save me-1"></i> {{ $isEditingOwner ? 'Save Changes' : 'Register Owner' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    <!-- Create Contract Modal -->
    @if($showContractModal && $contractOwner)
    <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-file-contract me-1 text-success"></i>
                        Establish Contract for: {{ $contractOwner->name }}
                    </h5>
                    <button type="button" class="btn-close" wire:click="$set('showContractModal', false)"></button>
                </div>
                <form wire:submit.prevent="saveContract">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">Contract Reference # <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm @error('contract_number') is-invalid @enderror" wire:model="contract_number">
                                @error('contract_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">Payment Model <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm" wire:model.live="payment_model">
                                    @foreach($paymentModels as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            @if($payment_model === 'percentage_of_freight')
                                <div class="col-md-6">
                                    <label class="form-label fs--1 fw-semi-bold">Owner Freight Share (%)</label>
                                    <input type="number" step="0.5" class="form-control form-control-sm" wire:model="freight_percentage">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fs--1 fw-semi-bold">Company Commission (%)</label>
                                    <input type="number" step="0.5" class="form-control form-control-sm" wire:model="commission_percentage">
                                </div>
                            @else
                                <div class="col-md-6">
                                    <label class="form-label fs--1 fw-semi-bold">Agreed Rate (PKR) <span class="text-danger">*</span></label>
                                    <input type="number" step="100" class="form-control form-control-sm" wire:model="rate_amount">
                                    <div class="fs--2 text-muted">Fixed per-trip, monthly rental, or per-KM amount</div>
                                </div>
                            @endif

                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">Route Origin (Optional)</label>
                                <input type="text" class="form-control form-control-sm" wire:model="route_origin" placeholder="e.g. Karachi Port">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">Route Destination (Optional)</label>
                                <input type="text" class="form-control form-control-sm" wire:model="route_destination" placeholder="e.g. Lahore Hub">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">Start Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control form-control-sm" wire:model="start_date">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">End / Expiry Date</label>
                                <input type="date" class="form-control form-control-sm" wire:model="end_date">
                            </div>

                            <div class="col-12"><hr class="my-1"><div class="fw-bold fs--1 text-600 mb-1">Standard Settlement Deductions</div></div>

                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="deductFuelCheck" wire:model="deduct_fuel">
                                    <label class="form-check-label fs--1 fw-semi-bold" for="deductFuelCheck">Deduct Company Fuel</label>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="deductAdvancesCheck" wire:model="deduct_advances">
                                    <label class="form-check-label fs--1 fw-semi-bold" for="deductAdvancesCheck">Deduct Trip Advances</label>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="deductTollsCheck" wire:model="deduct_toll_taxes">
                                    <label class="form-check-label fs--1 fw-semi-bold" for="deductTollsCheck">Deduct Highway Tolls</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="$set('showContractModal', false)">Cancel</button>
                        <button type="submit" class="btn btn-success btn-sm">Save & Activate Contract</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    <!-- Owner Detail Modal -->
    @if($showDetailModal && $viewingOwner)
    <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-id-badge me-1 text-primary"></i> Owner Profile: {{ $viewingOwner->name }}
                    </h5>
                    <button type="button" class="btn-close" wire:click="$set('showDetailModal', false)"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="fs--2 text-muted text-uppercase fw-semi-bold">CNIC / Tax Number</div>
                            <div class="fw-bold">{{ $viewingOwner->cnic_number ?: 'N/A' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="fs--2 text-muted text-uppercase fw-semi-bold">Contact Coordinates</div>
                            <div class="fw-bold">{{ $viewingOwner->phone }} &bull; {{ $viewingOwner->email ?: 'No email' }}</div>
                        </div>
                        <div class="col-md-12">
                            <div class="fs--2 text-muted text-uppercase fw-semi-bold">Bank Coordinates</div>
                            <div class="fw-bold">{{ $viewingOwner->bank_name ?: 'N/A' }} &mdash; {{ $viewingOwner->bank_account_number }} (Title: {{ $viewingOwner->bank_account_title }})</div>
                        </div>
                        <div class="col-12"><hr class="my-1"></div>
                        <div class="col-12">
                            <h6 class="fw-bold text-600 mb-2">Attached Vehicles ({{ $viewingOwner->vehicles->count() }})</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped fs--2 mb-0">
                                    <thead><tr><th>Plate #</th><th>Type</th><th>Capacity</th><th>Status</th></tr></thead>
                                    <tbody>
                                        @forelse($viewingOwner->vehicles as $veh)
                                            <tr>
                                                <td class="fw-bold font-monospace">{{ $veh->registration_number }}</td>
                                                <td>{{ $veh->vehicle_type }}</td>
                                                <td>{{ $veh->payload_capacity_tons }} Tons</td>
                                                <td><span class="badge bg-soft-success text-success">{{ $veh->status }}</span></td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="4" class="text-center text-muted">No trucks currently attached.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="$set('showDetailModal', false)">Close</button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

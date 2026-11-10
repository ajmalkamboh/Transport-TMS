<div>
    @if(session()->has('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Action Bar & Filters -->
    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <div class="row g-2 align-items-center justify-content-between">
                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="fas fa-search text-400"></i></span>
                        <input type="text" class="form-control" placeholder="Search driver name, CNIC, license, phone..." wire:model.live.debounce.300ms="search">
                    </div>
                </div>
                <div class="col-md-auto d-flex gap-2 align-items-center">
                    <select class="form-select form-select-sm" wire:model.live="licenseFilter" style="width: 130px;">
                        <option value="">All Licenses</option>
                        <option value="HTV">HTV</option>
                        <option value="LTV">LTV</option>
                        <option value="PSV">PSV</option>
                        <option value="MOTORCYCLE">Motorcycle</option>
                    </select>

                    <select class="form-select form-select-sm" wire:model.live="statusFilter" style="width: 130px;">
                        <option value="">All Statuses</option>
                        <option value="available">Available</option>
                        <option value="on_trip">On Trip</option>
                        <option value="on_leave">On Leave</option>
                        <option value="suspended">Suspended</option>
                        <option value="terminated">Terminated</option>
                    </select>

                    <button type="button" class="btn btn-primary btn-sm shadow-sm" wire:click="openCreateDriverModal">
                        <i class="fas fa-plus me-1"></i> Register Driver
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Drivers Table -->
    <div class="card shadow-sm mb-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped fs--1 mb-0 align-middle">
                    <thead class="bg-200 text-900">
                        <tr>
                            <th>Driver Profile</th>
                            <th>CNIC & Contact</th>
                            <th>License & Expiry</th>
                            <th>Current Vehicle</th>
                            <th>Branch / Base</th>
                            <th>Compensation Structure</th>
                            <th>Advances Outstanding</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($drivers as $driver)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar avatar-m me-2">
                                            <div class="avatar-name rounded-circle bg-soft-primary text-primary fw-bold">
                                                {{ strtoupper(substr($driver->name, 0, 2)) }}
                                            </div>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-primary">{{ $driver->name }}</div>
                                            @if($driver->father_name)
                                                <div class="fs--2 text-muted">S/O {{ $driver->father_name }}</div>
                                            @endif
                                            @if($driver->blood_group)
                                                <span class="badge badge-subtle-danger fs--2">Blood: {{ $driver->blood_group }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semi-bold">{{ $driver->cnic_number }}</div>
                                    <div class="text-muted"><i class="fas fa-phone-alt fs--2 me-1"></i> {{ $driver->phone }}</div>
                                    @if($driver->emergency_contact_phone)
                                        <div class="fs--2 text-muted" title="Emergency Contact: {{ $driver->emergency_contact_name }} ({{ $driver->emergency_contact_relation }})">
                                            <i class="fas fa-exclamation-circle text-warning fs--2 me-1"></i> {{ $driver->emergency_contact_phone }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-soft-info text-info fw-bold">{{ $driver->license_category }}</span>
                                    <span class="fw-semi-bold ms-1">{{ $driver->license_number }}</span>
                                    <div class="fs--2 mt-1">
                                        @php
                                            $exp = \Carbon\Carbon::parse($driver->license_expiry_date);
                                            $isPast = $exp->isPast();
                                            $days = (int) now()->diffInDays($exp, false);
                                        @endphp
                                        @if($isPast)
                                            <span class="text-danger fw-bold"><i class="fas fa-exclamation-triangle me-1"></i> Expired ({{ $exp->format('d M Y') }})</span>
                                        @elseif($days <= 30)
                                            <span class="text-warning fw-bold"><i class="fas fa-clock me-1"></i> Expiring in {{ $days }}d ({{ $exp->format('d M Y') }})</span>
                                        @else
                                            <span class="text-success"><i class="fas fa-check me-1"></i> Valid to {{ $exp->format('d M Y') }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    @if($driver->currentVehicle)
                                        <span class="badge bg-soft-primary text-primary fw-bold">
                                            <i class="fas fa-truck me-1"></i> {{ $driver->currentVehicle->registration_number }}
                                        </span>
                                        @if($driver->currentVehicle->fleet_number)
                                            <div class="fs--2 text-muted">Fleet # {{ $driver->currentVehicle->fleet_number }}</div>
                                        @endif
                                    @else
                                        <span class="badge bg-soft-secondary text-secondary">Unassigned</span>
                                    @endif
                                </td>
                                <td>
                                    {{ $driver->branch?->name ?? 'Headquarters' }}
                                    <div class="fs--2 text-capitalize text-muted">{{ str_replace('_', ' ', $driver->employment_type) }}</div>
                                </td>
                                <td>
                                    <div class="fw-semi-bold">PKR {{ number_format($driver->basic_salary, 2) }} <span class="fs--2 text-muted">/mo</span></div>
                                    <div class="fs--2 text-muted">Trip: PKR {{ number_format($driver->per_trip_allowance, 2) }} | Batta: PKR {{ number_format($driver->daily_batta, 2) }}</div>
                                </td>
                                <td>
                                    @php
                                        $advanceBal = (float) $driver->total_advances_balance;
                                    @endphp
                                    @if($advanceBal > 0)
                                        <span class="badge bg-soft-warning text-dark fw-bold">PKR {{ number_format($advanceBal, 2) }}</span>
                                    @else
                                        <span class="badge bg-soft-success text-success">PKR 0.00</span>
                                    @endif
                                </td>
                                <td>
                                    @switch($driver->status)
                                        @case('available')
                                            <span class="badge bg-soft-success text-success"><i class="fas fa-check-circle me-1"></i> Available</span>
                                            @break
                                        @case('on_trip')
                                            <span class="badge bg-soft-primary text-primary"><i class="fas fa-route me-1"></i> On Trip</span>
                                            @break
                                        @case('on_leave')
                                            <span class="badge bg-soft-warning text-warning"><i class="fas fa-umbrella-beach me-1"></i> On Leave</span>
                                            @break
                                        @case('suspended')
                                            <span class="badge bg-soft-danger text-danger"><i class="fas fa-ban me-1"></i> Suspended</span>
                                            @break
                                        @case('terminated')
                                            <span class="badge bg-soft-dark text-dark"><i class="fas fa-times-circle me-1"></i> Terminated</span>
                                            @break
                                        @default
                                            <span class="badge bg-soft-secondary text-secondary">{{ ucfirst($driver->status) }}</span>
                                    @endswitch
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-falcon-default" wire:click="viewDriver({{ $driver->id }})" title="View Driver Dossier">
                                            <i class="fas fa-eye text-primary"></i>
                                        </button>
                                        <button type="button" class="btn btn-falcon-default" wire:click="openAdvanceModal({{ $driver->id }})" title="Disburse Cash Advance">
                                            <i class="fas fa-hand-holding-usd text-warning"></i>
                                        </button>
                                        <button type="button" class="btn btn-falcon-default" wire:click="editDriver({{ $driver->id }})" title="Edit Driver Profile">
                                            <i class="fas fa-edit text-info"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">
                                    <i class="fas fa-id-card fa-2x mb-2 d-block text-300"></i>
                                    No commercial drivers found matching your filter criteria.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($drivers->hasPages())
            <div class="card-footer py-2">
                {{ $drivers->links() }}
            </div>
        @endif
    </div>

    <!-- CREATE / EDIT DRIVER MODAL -->
    @if($showDriverModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);" aria-modal="true" role="dialog">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-light py-2">
                        <h6 class="modal-title text-primary fw-bold">
                            <i class="fas fa-user-plus me-1"></i> {{ $isEditingDriver ? 'Edit Commercial Driver Profile' : 'Register New Commercial Driver' }}
                        </h6>
                        <button type="button" class="btn-close" wire:click="$set('showDriverModal', false)"></button>
                    </div>
                    <form wire:submit.prevent="saveDriver">
                        <div class="modal-body p-3">
                            <h6 class="fw-bold fs--1 text-uppercase text-700 border-bottom pb-1 mb-2">1. Personal & Contact Identity</h6>
                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fs--1 fw-semi-bold">Driver Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm @error('name') is-invalid @enderror" wire:model="name" placeholder="e.g. Muhammad Aslam Khan">
                                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fs--1 fw-semi-bold">Father's Name</label>
                                    <input type="text" class="form-control form-control-sm @error('father_name') is-invalid @enderror" wire:model="father_name" placeholder="e.g. Haji Ghulam Rasool">
                                    @error('father_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-semi-bold">CNIC Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm @error('cnic_number') is-invalid @enderror" wire:model="cnic_number" placeholder="37405-1234567-1">
                                    @error('cnic_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-semi-bold">Mobile Phone <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm @error('phone') is-invalid @enderror" wire:model="phone" placeholder="0300-1234567">
                                    @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-semi-bold">Secondary Phone</label>
                                    <input type="text" class="form-control form-control-sm @error('secondary_phone') is-invalid @enderror" wire:model="secondary_phone" placeholder="0345-7654321">
                                    @error('secondary_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-semi-bold">Date of Birth</label>
                                    <input type="date" class="form-control form-control-sm @error('date_of_birth') is-invalid @enderror" wire:model="date_of_birth">
                                    @error('date_of_birth') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-semi-bold">Blood Group</label>
                                    <select class="form-select form-select-sm @error('blood_group') is-invalid @enderror" wire:model="blood_group">
                                        <option value="A+">A+</option>
                                        <option value="A-">A-</option>
                                        <option value="B+">B+</option>
                                        <option value="B-">B-</option>
                                        <option value="AB+">AB+</option>
                                        <option value="AB-">AB-</option>
                                        <option value="O+">O+</option>
                                        <option value="O-">O-</option>
                                    </select>
                                    @error('blood_group') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-semi-bold">Branch / Terminal Base</label>
                                    <select class="form-select form-select-sm @error('branch_id') is-invalid @enderror" wire:model="branch_id">
                                        <option value="">Headquarters / All Branches</option>
                                        @foreach($branches as $branch)
                                            <option value="{{ $branch->id }}">{{ $branch->name }} ({{ $branch->code }})</option>
                                        @endforeach
                                    </select>
                                    @error('branch_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label fs--1 fw-semi-bold">Residential Address</label>
                                    <input type="text" class="form-control form-control-sm @error('address') is-invalid @enderror" wire:model="address" placeholder="e.g. House # 12, Mohallah Transport, Rawalpindi">
                                    @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <h6 class="fw-bold fs--1 text-uppercase text-700 border-bottom pb-1 mb-2">2. Commercial Driving License Compliance</h6>
                            <div class="row g-2 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-semi-bold">License Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm @error('license_number') is-invalid @enderror" wire:model="license_number" placeholder="DL-12345678">
                                    @error('license_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-semi-bold">License Category <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm @error('license_category') is-invalid @enderror" wire:model="license_category">
                                        <option value="HTV">HTV (Heavy Transport Vehicle)</option>
                                        <option value="LTV">LTV (Light Transport Vehicle)</option>
                                        <option value="PSV">PSV (Public Service Vehicle)</option>
                                        <option value="TRACTOR_COMMERCIAL">Tractor Commercial</option>
                                        <option value="MOTORCYCLE">Motorcycle</option>
                                    </select>
                                    @error('license_category') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-semi-bold">License Expiry Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control form-control-sm @error('license_expiry_date') is-invalid @enderror" wire:model="license_expiry_date">
                                    @error('license_expiry_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label fs--1 fw-semi-bold">Issuing Authority</label>
                                    <input type="text" class="form-control form-control-sm @error('license_issuing_authority') is-invalid @enderror" wire:model="license_issuing_authority" placeholder="e.g. National Highways & Motorway Police / Islamabad Traffic Police">
                                    @error('license_issuing_authority') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <h6 class="fw-bold fs--1 text-uppercase text-700 border-bottom pb-1 mb-2">3. Emergency Contact Details</h6>
                            <div class="row g-2 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-semi-bold">Emergency Contact Name</label>
                                    <input type="text" class="form-control form-control-sm @error('emergency_contact_name') is-invalid @enderror" wire:model="emergency_contact_name" placeholder="e.g. Tariq Khan">
                                    @error('emergency_contact_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-semi-bold">Relationship</label>
                                    <input type="text" class="form-control form-control-sm @error('emergency_contact_relation') is-invalid @enderror" wire:model="emergency_contact_relation" placeholder="e.g. Brother, Uncle, Wife">
                                    @error('emergency_contact_relation') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-semi-bold">Emergency Phone</label>
                                    <input type="text" class="form-control form-control-sm @error('emergency_contact_phone') is-invalid @enderror" wire:model="emergency_contact_phone" placeholder="0301-9876543">
                                    @error('emergency_contact_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <h6 class="fw-bold fs--1 text-uppercase text-700 border-bottom pb-1 mb-2">4. Employment Terms & Compensation Structure</h6>
                            <div class="row g-2 mb-3">
                                <div class="col-md-3">
                                    <label class="form-label fs--1 fw-semi-bold">Employment Type <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm @error('employment_type') is-invalid @enderror" wire:model="employment_type">
                                        <option value="permanent">Permanent Staff</option>
                                        <option value="contractual">Contractual</option>
                                        <option value="trip_based">Trip Based (Commission)</option>
                                        <option value="daily_wages">Daily Wages</option>
                                    </select>
                                    @error('employment_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs--1 fw-semi-bold">Basic Monthly Salary (PKR)</label>
                                    <input type="number" step="0.01" class="form-control form-control-sm @error('basic_salary') is-invalid @enderror" wire:model="basic_salary">
                                    @error('basic_salary') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs--1 fw-semi-bold">Per-Trip Allowance (PKR)</label>
                                    <input type="number" step="0.01" class="form-control form-control-sm @error('per_trip_allowance') is-invalid @enderror" wire:model="per_trip_allowance">
                                    @error('per_trip_allowance') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs--1 fw-semi-bold">Daily Batta / Food (PKR)</label>
                                    <input type="number" step="0.01" class="form-control form-control-sm @error('daily_batta') is-invalid @enderror" wire:model="daily_batta">
                                    @error('daily_batta') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-semi-bold">Duty Status <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm @error('status') is-invalid @enderror" wire:model="status">
                                        <option value="available">Available for Dispatch</option>
                                        <option value="on_trip">On Active Trip</option>
                                        <option value="on_leave">On Authorized Leave</option>
                                        <option value="suspended">Suspended</option>
                                        <option value="terminated">Terminated</option>
                                    </select>
                                    @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label fs--1 fw-semi-bold">Operational Notes</label>
                                    <input type="text" class="form-control form-control-sm @error('notes') is-invalid @enderror" wire:model="notes" placeholder="e.g. Experienced in Karachi-Lahore N-5 / M-5 long-haul freight corridors">
                                    @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light py-2">
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="$set('showDriverModal', false)">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-save me-1"></i> {{ $isEditingDriver ? 'Save Changes' : 'Register Driver' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- DISBURSE CASH ADVANCE MODAL -->
    @if($showAdvanceModal && $advancingDriver)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);" aria-modal="true" role="dialog">
            <div class="modal-dialog modal-md">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-warning text-dark py-2">
                        <h6 class="modal-title fw-bold">
                            <i class="fas fa-hand-holding-usd me-1"></i> Disburse Cash Advance to Driver
                        </h6>
                        <button type="button" class="btn-close" wire:click="$set('showAdvanceModal', false)"></button>
                    </div>
                    <form wire:submit.prevent="disburseAdvance">
                        <div class="modal-body p-3">
                            <div class="alert alert-soft-warning py-2 mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>Driver:</strong> {{ $advancingDriver->name }} ({{ $advancingDriver->cnic_number }})
                                        <div class="fs--2 text-muted">Phone: {{ $advancingDriver->phone }}</div>
                                    </div>
                                    <div class="text-end">
                                        <div class="fs--2 text-muted">Current Balance</div>
                                        <div class="fw-bold text-dark fs-0">PKR {{ number_format($advancingDriver->total_advances_balance, 2) }}</div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fs--1 fw-semi-bold">Advance Amount (PKR) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control form-control-sm @error('advance_amount') is-invalid @enderror" wire:model="advance_amount">
                                @error('advance_amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label fs--1 fw-semi-bold">Purpose / Advance Heading <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm @error('advance_purpose') is-invalid @enderror" wire:model="advance_purpose">
                                    <option value="Trip Diesel & Highway Allowance">Trip Diesel & Highway Allowance</option>
                                    <option value="Toll Taxes & Weighbridge Cash">Toll Taxes & Weighbridge Cash</option>
                                    <option value="Emergency En-Route Repairs">Emergency En-Route Repairs</option>
                                    <option value="Personal Driver Salary Advance">Personal Driver Salary Advance</option>
                                    <option value="Other Operational Advance">Other Operational Advance</option>
                                </select>
                                @error('advance_purpose') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fs--1 fw-semi-bold">Payment Method <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm @error('payment_method') is-invalid @enderror" wire:model="payment_method">
                                        <option value="cash">Petty Cash</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="jazzcash">JazzCash</option>
                                        <option value="easypaisa">EasyPaisa</option>
                                        <option value="cheque">Cheque</option>
                                    </select>
                                    @error('payment_method') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fs--1 fw-semi-bold">Payment / Cheque / Txn Ref</label>
                                    <input type="text" class="form-control form-control-sm @error('advance_reference') is-invalid @enderror" wire:model="advance_reference" placeholder="e.g. TXN-839210">
                                    @error('advance_reference') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="mb-2">
                                <label class="form-label fs--1 fw-semi-bold">Remarks / Instructions</label>
                                <textarea class="form-control form-control-sm @error('advance_notes') is-invalid @enderror" rows="2" wire:model="advance_notes" placeholder="Any specific adjustment instructions or trip voucher notes..."></textarea>
                                @error('advance_notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="modal-footer bg-light py-2">
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="$set('showAdvanceModal', false)">Cancel</button>
                            <button type="submit" class="btn btn-warning btn-sm fw-bold">
                                <i class="fas fa-check-circle me-1"></i> Disburse Advance & Issue Voucher
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- DRIVER DOSSIER / VIEW MODAL -->
    @if($showDetailModal && $viewingDriver)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);" aria-modal="true" role="dialog">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-light py-2">
                        <h6 class="modal-title text-primary fw-bold">
                            <i class="fas fa-id-card me-1"></i> Driver Dossier: {{ $viewingDriver->name }}
                        </h6>
                        <button type="button" class="btn-close" wire:click="$set('showDetailModal', false)"></button>
                    </div>
                    <div class="modal-body p-3">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <div class="card h-100 bg-light border-0">
                                    <div class="card-body p-3">
                                        <h6 class="fw-bold fs--1 text-uppercase text-700 mb-2">Personal Information</h6>
                                        <div class="fs--1 mb-1"><strong>CNIC:</strong> {{ $viewingDriver->cnic_number }}</div>
                                        <div class="fs--1 mb-1"><strong>Father's Name:</strong> {{ $viewingDriver->father_name ?? 'N/A' }}</div>
                                        <div class="fs--1 mb-1"><strong>Phone:</strong> {{ $viewingDriver->phone }}</div>
                                        <div class="fs--1 mb-1"><strong>Secondary Phone:</strong> {{ $viewingDriver->secondary_phone ?? 'N/A' }}</div>
                                        <div class="fs--1 mb-1"><strong>Blood Group:</strong> <span class="badge bg-soft-danger text-danger">{{ $viewingDriver->blood_group ?? 'N/A' }}</span></div>
                                        <div class="fs--1 mb-1"><strong>Address:</strong> {{ $viewingDriver->address ?? 'N/A' }}</div>
                                        <div class="fs--1"><strong>Emergency:</strong> {{ $viewingDriver->emergency_contact_name }} ({{ $viewingDriver->emergency_contact_relation }}) - {{ $viewingDriver->emergency_contact_phone }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100 bg-light border-0">
                                    <div class="card-body p-3">
                                        <h6 class="fw-bold fs--1 text-uppercase text-700 mb-2">Professional & Duty Status</h6>
                                        <div class="fs--1 mb-1"><strong>License Number:</strong> {{ $viewingDriver->license_number }}</div>
                                        <div class="fs--1 mb-1"><strong>Category:</strong> <span class="badge bg-soft-info text-info">{{ $viewingDriver->license_category }}</span></div>
                                        <div class="fs--1 mb-1"><strong>Issuing Authority:</strong> {{ $viewingDriver->license_issuing_authority ?? 'N/A' }}</div>
                                        <div class="fs--1 mb-1"><strong>License Expiry:</strong> {{ $viewingDriver->license_expiry_date?->format('d M Y') }}</div>
                                        <div class="fs--1 mb-1"><strong>Current Vehicle:</strong> {{ $viewingDriver->currentVehicle?->registration_number ?? 'None (Unassigned)' }}</div>
                                        <div class="fs--1 mb-1"><strong>Branch:</strong> {{ $viewingDriver->branch?->name ?? 'Headquarters' }}</div>
                                        <div class="fs--1"><strong>Status:</strong> <span class="badge bg-soft-primary text-primary">{{ ucfirst($viewingDriver->status) }}</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Compensation & Advance Summary -->
                        <div class="card bg-soft-primary border-0 mb-3">
                            <div class="card-body p-3">
                                <div class="row g-2 text-center">
                                    <div class="col-md-3">
                                        <div class="fs--2 text-muted">Basic Salary</div>
                                        <div class="fw-bold fs-0 text-primary">PKR {{ number_format($viewingDriver->basic_salary, 2) }}</div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="fs--2 text-muted">Per Trip Allowance</div>
                                        <div class="fw-bold fs-0 text-info">PKR {{ number_format($viewingDriver->per_trip_allowance, 2) }}</div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="fs--2 text-muted">Daily Batta</div>
                                        <div class="fw-bold fs-0 text-success">PKR {{ number_format($viewingDriver->daily_batta, 2) }}</div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="fs--2 text-muted">Total Outstanding Advances</div>
                                        <div class="fw-bold fs-0 text-warning">PKR {{ number_format($viewingDriver->total_advances_balance, 2) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Advances Table -->
                        <h6 class="fw-bold fs--1 text-uppercase text-700 mb-2">Recent Cash Advances & Vouchers</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered fs--2 mb-0">
                                <thead class="bg-200">
                                    <tr>
                                        <th>Voucher #</th>
                                        <th>Date</th>
                                        <th>Purpose</th>
                                        <th>Amount</th>
                                        <th>Settled</th>
                                        <th>Balance</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($viewingDriver->advances as $adv)
                                        <tr>
                                            <td class="fw-bold text-primary">{{ $adv->advance_number }}</td>
                                            <td>{{ $adv->disbursed_at?->format('d M Y') ?? $adv->created_at->format('d M Y') }}</td>
                                            <td>{{ $adv->purpose }}</td>
                                            <td class="text-end fw-semi-bold">PKR {{ number_format($adv->amount, 2) }}</td>
                                            <td class="text-end text-success">PKR {{ number_format($adv->settled_amount, 2) }}</td>
                                            <td class="text-end fw-bold text-warning">PKR {{ number_format($adv->balance_amount, 2) }}</td>
                                            <td class="text-center">
                                                <span class="badge bg-soft-{{ $adv->status === 'settled' ? 'success' : ($adv->status === 'partially_settled' ? 'warning' : 'danger') }}">
                                                    {{ ucfirst($adv->status) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center py-2 text-muted">No advance disbursement records found.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
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


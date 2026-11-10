<div>
    <!-- Top Action Bar -->
    <div class="card mb-3 shadow-sm border">
        <div class="card-body p-3">
            <div class="row g-2 justify-content-between align-items-center">
                <div class="col-md-4">
                    <input type="text" class="form-control form-control-sm" placeholder="Search by name, code, email, phone..." wire:model.live.debounce.300ms="search">
                </div>
                <div class="col-md-3">
                    <select class="form-select form-select-sm" wire:model.live="statusFilter">
                        <option value="">All Company Statuses</option>
                        <option value="active">Active</option>
                        <option value="trial">Trial</option>
                        <option value="suspended">Suspended</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary btn-sm" wire:click="openCreateModal">
                        <i class="fas fa-plus-circle me-1"></i> Provision Transport Company
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tenants Table -->
    <div class="card shadow-sm border">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped fs--1 mb-0">
                    <thead class="bg-200 text-900">
                        <tr>
                            <th>Company</th>
                            <th>Plan & Cycle</th>
                            <th>Business Owner</th>
                            <th>Hubs / Staff</th>
                            <th>Status</th>
                            <th>Expiry</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($companies as $company)
                        <tr>
                            <td>
                                <div class="fw-bold text-primary">{{ $company->name }}</div>
                                <div class="fs--2 text-muted">Code: <code>{{ $company->code }}</code> &bull; NTN: {{ $company->tax_number ?? 'N/A' }}</div>
                                <div class="fs--2 text-muted">{{ $company->email }} &bull; {{ $company->phone }}</div>
                            </td>
                            <td>
                                <span class="badge bg-soft-info text-info">{{ $company->plan?->name ?? 'Custom Plan' }}</span>
                                <div class="fs--2 text-500 mt-1">PKR {{ number_format($company->plan?->monthly_price ?? 0, 0) }}/mo</div>
                            </td>
                            <td>
                                @if($company->owner)
                                    <div class="fw-bold text-dark">{{ $company->owner->name }}</div>
                                    <div class="fs--2 text-muted">{{ $company->owner->email }}</div>
                                    <div class="fs--2 text-muted">{{ $company->owner->phone }}</div>
                                @else
                                    <span class="badge bg-soft-warning text-warning">No Owner Assigned</span>
                                @endif
                            </td>
                            <td>
                                <div><i class="fas fa-code-branch me-1 text-secondary"></i> {{ $company->branches_count }} Hubs</div>
                                <div><i class="fas fa-users me-1 text-secondary"></i> {{ $company->users_count }} Staff</div>
                            </td>
                            <td>
                                @if($company->status === 'active')
                                    <span class="badge bg-soft-success text-success">Active</span>
                                @elseif($company->status === 'suspended')
                                    <span class="badge bg-soft-danger text-danger">Suspended</span>
                                @elseif($company->status === 'trial')
                                    <span class="badge bg-soft-info text-info">Free Trial</span>
                                @else
                                    <span class="badge bg-soft-secondary text-secondary">Inactive</span>
                                @endif
                            </td>
                            <td>
                                {{ $company->subscription_expires_at ? $company->subscription_expires_at->format('d M Y') : 'Lifetime' }}
                            </td>
                            <td class="text-end">
                                <button class="btn btn-outline-info btn-sm py-0 px-2" wire:click="viewTenant({{ $company->id }})" title="View Usage & Overrides">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-outline-primary btn-sm py-0 px-2 ms-1" wire:click="editTenant({{ $company->id }})" title="Edit Tenant">
                                    <i class="fas fa-edit"></i>
                                </button>
                                @if($company->status === 'active')
                                    <button class="btn btn-outline-danger btn-sm py-0 px-2 ms-1" wire:click="setStatus({{ $company->id }}, 'suspended')" title="Suspend Tenant">
                                        <i class="fas fa-ban"></i>
                                    </button>
                                @else
                                    <button class="btn btn-outline-success btn-sm py-0 px-2 ms-1" wire:click="setStatus({{ $company->id }}, 'active')" title="Activate Tenant">
                                        <i class="fas fa-check-circle"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-500">No transport companies match the search criteria.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3">
                {{ $companies->links() }}
            </div>
        </div>
    </div>

    <!-- Provision / Edit Tenant Modal -->
    @if($showModal)
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.6);">
        <div class="modal-dialog modal-lg">
            <div class="modal-content shadow-lg border-0">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold">
                        <i class="fas {{ $isEditing ? 'fa-edit' : 'fa-plus-circle' }} me-2 text-primary"></i>
                        {{ $isEditing ? 'Edit Transport Company Details' : 'Provision New Transport Company Tenant' }}
                    </h5>
                    <button type="button" class="btn-close" wire:click="$set('showModal', false)"></button>
                </div>
                <form wire:submit.prevent="saveTenant">
                    <div class="modal-body">
                        <!-- Company Details Section -->
                        <h6 class="fw-bold text-primary mb-3">1. Transport Company Corporate Information</h6>
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fs--1">Company Legal Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm @error('name') is-invalid @enderror" wire:model="name" placeholder="e.g. National Freight Express Ltd">
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fs--1">Company Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm @error('code') is-invalid @enderror" wire:model="code" placeholder="e.g. NFE">
                                @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fs--1">NTN / STRN</label>
                                <input type="text" class="form-control form-control-sm @error('tax_number') is-invalid @enderror" wire:model="tax_number" placeholder="e.g. 1234567-8">
                                @error('tax_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fs--1">Official Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control form-control-sm @error('email') is-invalid @enderror" wire:model="email" placeholder="operations@company.com">
                                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fs--1">Corporate Phone <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm @error('phone') is-invalid @enderror" wire:model="phone" placeholder="+92-42-35881234">
                                @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fs--1">Head Office Address</label>
                                <textarea class="form-control form-control-sm @error('address') is-invalid @enderror" wire:model="address" rows="2" placeholder="Full depot / office address"></textarea>
                                @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <!-- Plan & Subscription Section -->
                        <h6 class="fw-bold text-primary mb-3 mt-4">2. Subscription Plan Assignment</h6>
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fs--1">Subscription Plan <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm @error('plan_id') is-invalid @enderror" wire:model="plan_id">
                                    @foreach($plans as $p)
                                        <option value="{{ $p->id }}">{{ $p->name }} (PKR {{ number_format($p->monthly_price, 0) }}/mo) - {{ $p->max_vehicles }} trucks, {{ $p->max_users }} users</option>
                                    @endforeach
                                </select>
                                @error('plan_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fs--1">Billing Cycle</label>
                                <select class="form-select form-select-sm" wire:model="billing_cycle">
                                    <option value="monthly">Monthly</option>
                                    <option value="quarterly">Quarterly</option>
                                    <option value="annual">Annual</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-center mt-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_trial" wire:model="is_trial">
                                    <label class="form-check-label fs--1" for="is_trial">Grant Free Trial</label>
                                </div>
                            </div>
                        </div>

                        <!-- Initial Owner Credentials (Only on Create) -->
                        @if(!$isEditing)
                        <h6 class="fw-bold text-primary mb-3 mt-4">3. Business Owner & Primary Hub Credentials</h6>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label fs--1">Owner Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm @error('owner_name') is-invalid @enderror" wire:model="owner_name" placeholder="Managing Director / Owner Name">
                                @error('owner_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fs--1">Owner Login Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control form-control-sm @error('owner_email') is-invalid @enderror" wire:model="owner_email" placeholder="owner@company.com">
                                @error('owner_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fs--1">Initial Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control form-control-sm @error('owner_password') is-invalid @enderror" wire:model="owner_password" placeholder="Min 8 characters">
                                @error('owner_password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fs--1">Head Office City <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm @error('city') is-invalid @enderror" wire:model="city" placeholder="e.g. Lahore, Karachi">
                                @error('city') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        @endif
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="$set('showModal', false)">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm px-4">
                            <i class="fas fa-save me-1"></i> {{ $isEditing ? 'Save Changes' : 'Provision Tenant' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    <!-- View Tenant Detail & Usage Statistics Modal -->
    @if($showDetailModal && $viewingCompany)
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.6);">
        <div class="modal-dialog modal-lg">
            <div class="modal-content shadow-lg border-0">
                <div class="modal-header bg-light">
                    <div>
                        <h5 class="modal-title fw-bold text-primary mb-0">
                            <i class="fas fa-building me-2"></i> {{ $viewingCompany->name }}
                        </h5>
                        <div class="fs--1 text-muted">Tenant Code: <code>{{ $viewingCompany->code }}</code> &bull; Status: <span class="badge bg-soft-success text-success">{{ ucfirst($viewingCompany->status) }}</span></div>
                    </div>
                    <button type="button" class="btn-close" wire:click="closeDetailModal"></button>
                </div>
                <div class="modal-body">
                    <!-- Usage Statistics Limits -->
                    <h6 class="fw-bold text-dark mb-3">Resource Quota & Usage Limits</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="card bg-100 border p-3 text-center">
                                <div class="fs--1 text-600">Staff Users Quota</div>
                                <h4 class="fw-bolder mb-1 text-primary">{{ $usageStats['users']['current'] }} / {{ $usageStats['users']['limit'] }}</h4>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $usageStats['users']['percentage'] }}%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-100 border p-3 text-center">
                                <div class="fs--1 text-600">Operational Hubs Quota</div>
                                <h4 class="fw-bolder mb-1 text-success">{{ $usageStats['branches']['current'] }} / {{ $usageStats['branches']['limit'] }}</h4>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: {{ $usageStats['branches']['percentage'] }}%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-100 border p-3 text-center">
                                <div class="fs--1 text-600">Fleet Vehicles Quota</div>
                                <h4 class="fw-bolder mb-1 text-info">{{ $usageStats['vehicles']['current'] }} / {{ $usageStats['vehicles']['limit'] }}</h4>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-info" role="progressbar" style="width: {{ $usageStats['vehicles']['percentage'] }}%"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Configurable Feature Access & Overrides -->
                    <h6 class="fw-bold text-dark mb-2">Configurable Feature Access (Plan Default vs Tenant Override)</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered fs--1 mb-0">
                            <thead class="bg-200">
                                <tr>
                                    <th>Feature Name</th>
                                    <th>Plan Default</th>
                                    <th>Current State</th>
                                    <th class="text-center">Tenant Override Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($allFeatures as $slug => $label)
                                @php
                                    $planDefault = $viewingCompany->plan?->hasFeature($slug) ?? false;
                                    $currentState = $viewingCompany->hasFeature($slug);
                                    $hasOverride = $viewingCompany->feature_overrides && array_key_exists($slug, $viewingCompany->feature_overrides);
                                @endphp
                                <tr>
                                    <td class="fw-bold">{{ $label }}</td>
                                    <td>
                                        <span class="badge {{ $planDefault ? 'bg-soft-success text-success' : 'bg-soft-secondary text-secondary' }}">
                                            {{ $planDefault ? 'Included' : 'Excluded' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $currentState ? 'bg-success' : 'bg-danger' }}">
                                            {{ $currentState ? 'ENABLED' : 'DISABLED' }}
                                        </span>
                                        @if($hasOverride)
                                            <span class="badge bg-warning text-dark ms-1">Overridden</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-xs {{ $currentState ? 'btn-outline-danger' : 'btn-outline-success' }} py-0 px-2" wire:click="toggleFeatureOverride({{ $viewingCompany->id }}, '{{ $slug }}')">
                                            {{ $currentState ? 'Disable for Tenant' : 'Force Enable' }}
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="closeDetailModal">Close</button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>


<div>
    @if(session()->has('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fas fa-exclamation-triangle me-1"></i> {{ session('error') }}
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
                        <input type="text" class="form-control" placeholder="Search branch by name, code or city..." wire:model.live.debounce.300ms="search">
                    </div>
                </div>
                <div class="col-md-auto d-flex align-items-center">
                    <span class="fs--1 text-600 me-3">
                        Total Branches: <strong>{{ $branches->total() }}</strong>
                        @if($company->plan)
                            (Plan limit: <strong>{{ $company->plan->max_branches === -1 ? 'Unlimited' : $company->plan->max_branches }}</strong>)
                        @endif
                    </span>
                    @if($canAddBranch)
                        <button type="button" class="btn btn-primary btn-sm shadow-sm" wire:click="openCreateModal">
                            <i class="fas fa-plus-circle me-1"></i> Add Branch
                        </button>
                    @else
                        <button type="button" class="btn btn-secondary btn-sm" disabled title="Branch limit reached for current plan">
                            <i class="fas fa-lock me-1"></i> Branch Limit Reached
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Branch Table -->
    <div class="card shadow-sm mb-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped fs--1 mb-0 align-middle">
                    <thead class="bg-200 text-900">
                        <tr>
                            <th>Branch Name & Code</th>
                            <th>City & Location</th>
                            <th>Assigned Manager</th>
                            <th>Contact Phone & Email</th>
                            <th>Dock Capacity</th>
                            <th>Status & Type</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($branches as $branch)
                            <tr>
                                <td>
                                    <div class="fw-bold text-primary">{{ $branch->name }}</div>
                                    <div class="fs--2 text-500">Code: <span class="badge bg-light text-dark font-monospace">{{ $branch->code }}</span></div>
                                </td>
                                <td>
                                    <div class="fw-semi-bold">{{ $branch->city }}</div>
                                    <div class="fs--2 text-muted text-truncate" style="max-width: 180px;">{{ $branch->address ?: 'No address specified' }}</div>
                                </td>
                                <td>
                                    @if($branch->manager)
                                        <div class="fw-semi-bold text-dark">{{ $branch->manager->name }}</div>
                                        <div class="fs--2 text-muted">{{ $branch->manager->email }}</div>
                                    @else
                                        <span class="badge bg-soft-warning text-warning">Unassigned</span>
                                    @endif
                                </td>
                                <td>
                                    <div>{{ $branch->phone ?: 'N/A' }}</div>
                                    <div class="fs--2 text-muted">{{ $branch->email ?: 'N/A' }}</div>
                                </td>
                                <td>
                                    <span class="badge bg-soft-info text-info"><i class="fas fa-truck-loading me-1"></i> {{ $branch->dock_capacity }} Bays</span>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        @if($branch->is_head_office)
                                            <span class="badge bg-soft-primary text-primary"><i class="fas fa-crown me-1"></i> Head Office</span>
                                        @endif
                                        @if($branch->is_default)
                                            <span class="badge bg-soft-success text-success">Default</span>
                                        @endif
                                        @if($branch->is_active)
                                            <span class="badge bg-soft-success text-success">Active</span>
                                        @else
                                            <span class="badge bg-soft-danger text-danger">Inactive</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="editBranch({{ $branch->id }})" title="Edit Branch">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        @if(!$branch->is_head_office)
                                            <button type="button" class="btn btn-outline-{{ $branch->is_active ? 'warning' : 'success' }} btn-sm" wire:click="toggleBranchStatus({{ $branch->id }})" title="{{ $branch->is_active ? 'Deactivate' : 'Activate' }}">
                                                <i class="fas fa-{{ $branch->is_active ? 'ban' : 'check' }}"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-500">No branches found matching your search.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($branches->hasPages())
            <div class="card-footer bg-light py-2">
                {{ $branches->links() }}
            </div>
        @endif
    </div>

    <!-- Create / Edit Modal -->
    @if($showModal)
    <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-code-branch me-1 text-primary"></i>
                        {{ $isEditing ? 'Edit Branch' : 'Register New Operational Branch / Terminal' }}
                    </h5>
                    <button type="button" class="btn-close" wire:click="$set('showModal', false)"></button>
                </div>
                <form wire:submit.prevent="saveBranch">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label fs--1 fw-semi-bold">Branch Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm @error('name') is-invalid @enderror" wire:model="name" placeholder="e.g. Karachi Port Terminal">
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fs--1 fw-semi-bold">Branch Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm @error('code') is-invalid @enderror" wire:model="code" placeholder="e.g. FEC-KHI">
                                @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">City / Location <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm @error('city') is-invalid @enderror" wire:model="city" placeholder="e.g. Karachi">
                                @error('city') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">Assigned Branch Manager</label>
                                <select class="form-select form-select-sm" wire:model="manager_id">
                                    <option value="">-- Unassigned --</option>
                                    @foreach($companyUsers as $u)
                                        <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">Contact Phone</label>
                                <input type="text" class="form-control form-control-sm" wire:model="phone" placeholder="+92-21-32448800">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">Contact Email</label>
                                <input type="email" class="form-control form-control-sm" wire:model="email" placeholder="karachi@falconexpress.com">
                            </div>

                            <div class="col-md-8">
                                <label class="form-label fs--1 fw-semi-bold">Operating Hours</label>
                                <input type="text" class="form-control form-control-sm" wire:model="operating_hours" placeholder="e.g. 24/7 Dispatch or 08:00 AM - 10:00 PM">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fs--1 fw-semi-bold">Loading Dock Capacity (Bays)</label>
                                <input type="number" min="1" max="100" class="form-control form-control-sm" wire:model="dock_capacity">
                            </div>

                            <div class="col-12">
                                <label class="form-label fs--1 fw-semi-bold">Physical Depot Address</label>
                                <textarea class="form-control form-control-sm" wire:model="address" rows="2" placeholder="Warehouse address, gate number, industrial area"></textarea>
                            </div>

                            <div class="col-12"><hr class="my-1"></div>

                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="isDefaultCheck" wire:model="is_default">
                                    <label class="form-check-label fs--1 fw-semi-bold" for="isDefaultCheck">Default Branch</label>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="isHeadOfficeCheck" wire:model="is_head_office">
                                    <label class="form-check-label fs--1 fw-semi-bold" for="isHeadOfficeCheck">Company Head Office</label>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="isActiveCheck" wire:model="is_active">
                                    <label class="form-check-label fs--1 fw-semi-bold" for="isActiveCheck">Operational (Active)</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="$set('showModal', false)">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-save me-1"></i> {{ $isEditing ? 'Save Changes' : 'Create Branch' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>

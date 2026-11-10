<div>
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show py-2 fs--1 mb-3" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show py-2 fs--1 mb-3" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row g-3">
        <!-- User Form Column (Create / Edit) -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0 fw-bold">
                        <i class="fas {{ $isEditing ? 'fa-user-edit' : 'fa-user-plus' }} me-1 text-primary"></i>
                        {{ $isEditing ? 'Edit Company User' : 'Register New User' }}
                    </h6>
                </div>
                <div class="card-body">
                    <form wire:submit.prevent="save">
                        <div class="mb-2">
                            <label class="form-label fs--1">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm @error('name') is-invalid @enderror" wire:model="name" placeholder="e.g. Asif Ali">
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-2">
                            <label class="form-label fs--1">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control form-control-sm @error('email') is-invalid @enderror" wire:model="email" placeholder="name@company.com">
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-2">
                            <label class="form-label fs--1">Phone Number</label>
                            <input type="text" class="form-control form-control-sm @error('phone') is-invalid @enderror" wire:model="phone" placeholder="+92-300-1234567">
                            @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-2">
                            <label class="form-label fs--1">Password {{ $isEditing ? '(Leave blank to retain)' : '*' }}</label>
                            <input type="password" class="form-control form-control-sm @error('password') is-invalid @enderror" wire:model="password" placeholder="Min 8 characters">
                            @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-2">
                            <label class="form-label fs--1">Primary Assigned Branch <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm @error('branch_id') is-invalid @enderror" wire:model="branch_id">
                                <option value="">-- Select Home Hub --</option>
                                @foreach($branches as $b)
                                    <option value="{{ $b->id }}">{{ $b->name }} ({{ $b->city }})</option>
                                @endforeach
                            </select>
                            @error('branch_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-2">
                            <label class="form-label fs--1">Company Role <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm @error('role_id') is-invalid @enderror" wire:model="role_id">
                                <option value="">-- Select Role --</option>
                                @foreach($roles as $r)
                                    <option value="{{ $r->id }}">{{ $r->name }}</option>
                                @endforeach
                            </select>
                            @error('role_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-2">
                            <label class="form-label fs--1">Account Status <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm @error('status') is-invalid @enderror" wire:model="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                            @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label fs--1">Secondary Authorized Hubs (Optional)</label>
                            <div class="border rounded p-2 bg-50" style="max-height: 120px; overflow-y: auto;">
                                @foreach($branches as $b)
                                    <div class="form-check fs--1 mb-1">
                                        <input class="form-check-input" type="checkbox" value="{{ $b->id }}" id="sec_branch_{{ $b->id }}" wire:model="authorized_branch_ids">
                                        <label class="form-check-label" for="sec_branch_{{ $b->id }}">
                                            {{ $b->city }} ({{ $b->code }})
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary btn-sm px-3">
                                <i class="fas fa-save me-1"></i> {{ $isEditing ? 'Update User' : 'Save User' }}
                            </button>
                            @if($isEditing)
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="resetForm">
                                    Cancel
                                </button>
                            @endif
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- User Directory List Column -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold">Company Staff Directory</h6>
                    <div class="w-50">
                        <input type="text" class="form-control form-control-sm" placeholder="Search by name, email, phone..." wire:model.live.debounce.300ms="search">
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped fs--1 mb-0">
                            <thead class="bg-200 text-900">
                                <tr>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Primary Hub</th>
                                    <th>Multi-Branch</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($users as $u)
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark">{{ $u->name }}</div>
                                        <div class="fs--2 text-500">{{ $u->email }}</div>
                                        @if($u->phone) <div class="fs--2 text-muted">{{ $u->phone }}</div> @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-soft-info text-info">{{ $u->role?->name ?? 'Staff' }}</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-soft-secondary text-secondary">{{ $u->branch?->name ?? 'None' }}</span>
                                    </td>
                                    <td>
                                        @if($u->branches->count() > 0)
                                            <span class="badge bg-soft-success text-success">+{{ $u->branches->count() }} Authorized</span>
                                        @else
                                            <span class="text-muted fs--2">Home Only</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($u->status === 'active')
                                            <span class="badge bg-soft-success text-success">Active</span>
                                        @elseif($u->status === 'suspended')
                                            <span class="badge bg-soft-danger text-danger">Suspended</span>
                                        @else
                                            <span class="badge bg-soft-warning text-warning">Inactive</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-outline-primary btn-sm py-0 px-2" wire:click="editUser({{ $u->id }})" title="Edit User">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        @if(auth()->id() !== $u->id)
                                        <button class="btn btn-outline-danger btn-sm py-0 px-2 ms-1" wire:click="deleteUser({{ $u->id }})" onclick="return confirm('Are you sure you want to delete this user?')" title="Delete User">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-500">No users found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 text-primary fw-bold"><i class="fas fa-sitemap me-2"></i>Chart of Accounts (COA)</h4>
            <p class="text-muted small mb-0">Tenant-specific double-entry accounting structure with transport industry standards</p>
        </div>
        <div class="d-flex gap-2">
            <button wire:click="seedDefaults" wire:loading.attr="disabled" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-magic me-1"></i> Seed Default Accounts
            </button>
            <button wire:click="openCreateModal" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-1"></i> New Ledger Account
            </button>
        </div>
    </div>

    @if (session()->has('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Category Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-2 col-sm-4">
            <div class="card border-0 shadow-sm border-start border-primary border-4">
                <div class="card-body p-3">
                    <span class="text-muted small text-uppercase fw-semibold">Assets (1000s)</span>
                    <h4 class="fw-bold mb-0 text-primary">{{ $counts['assets'] }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4">
            <div class="card border-0 shadow-sm border-start border-danger border-4">
                <div class="card-body p-3">
                    <span class="text-muted small text-uppercase fw-semibold">Liabilities (2000s)</span>
                    <h4 class="fw-bold mb-0 text-danger">{{ $counts['liabilities'] }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4">
            <div class="card border-0 shadow-sm border-start border-info border-4">
                <div class="card-body p-3">
                    <span class="text-muted small text-uppercase fw-semibold">Equity (3000s)</span>
                    <h4 class="fw-bold mb-0 text-info">{{ $counts['equity'] }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm border-start border-success border-4">
                <div class="card-body p-3">
                    <span class="text-muted small text-uppercase fw-semibold">Revenue (4000s)</span>
                    <h4 class="fw-bold mb-0 text-success">{{ $counts['revenue'] }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm border-start border-warning border-4">
                <div class="card-body p-3">
                    <span class="text-muted small text-uppercase fw-semibold">Operating Costs (5000-6000s)</span>
                    <h4 class="fw-bold mb-0 text-warning">{{ $counts['expenses'] }}</h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters & Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-3">
            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" wire:model.live.debounce.300ms="search" class="form-control" placeholder="Search by account code, name, or classification...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select wire:model.live="typeFilter" class="form-select form-select-sm">
                        <option value="">All Account Classes</option>
                        <option value="asset">Assets (1000s)</option>
                        <option value="liability">Liabilities (2000s)</option>
                        <option value="equity">Equity (3000s)</option>
                        <option value="revenue">Revenue (4000s)</option>
                        <option value="expense">Expenses (5000s - 6000s)</option>
                    </select>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-uppercase fs--1">
                        <tr>
                            <th>Account Code</th>
                            <th>Account Title</th>
                            <th>Classification</th>
                            <th>Nature</th>
                            <th class="text-end">Opening Balance</th>
                            <th class="text-end">Current Balance</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($accounts as $acc)
                            <tr>
                                <td>
                                    <span class="badge bg-light text-dark font-monospace fw-bold">{{ $acc->account_code }}</span>
                                    @if($acc->is_system)
                                        <span class="badge bg-secondary-subtle text-secondary" title="System Standard Account"><i class="fas fa-lock"></i></span>
                                    @endif
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark">{{ $acc->account_name }}</span>
                                    @if($acc->description)
                                        <div class="text-muted fs--2">{{ $acc->description }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ match($acc->account_type) {
                                        'asset' => 'bg-primary-subtle text-primary',
                                        'liability' => 'bg-danger-subtle text-danger',
                                        'equity' => 'bg-info-subtle text-info',
                                        'revenue' => 'bg-success-subtle text-success',
                                        'expense' => 'bg-warning-subtle text-warning',
                                        default => 'bg-light text-dark'
                                    } }}">
                                        {{ ucfirst($acc->account_type) }}
                                    </span>
                                    <span class="text-muted fs--2 ms-1">({{ str_replace('_', ' ', $acc->account_group) }})</span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-muted">{{ strtoupper($acc->normal_balance) }}</span>
                                </td>
                                <td class="text-end font-monospace">
                                    {{ number_format($acc->opening_balance, 2) }}
                                </td>
                                <td class="text-end font-monospace fw-bold {{ (float) $acc->current_balance < 0 ? 'text-danger' : 'text-dark' }}">
                                    {{ number_format($acc->current_balance, 2) }}
                                </td>
                                <td class="text-center">
                                    @if($acc->is_active)
                                        <span class="badge bg-success-subtle text-success">Active</span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger">Inactive</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="fas fa-info-circle me-1"></i> No accounts found matching your query. Click "Seed Default Accounts" to load standard transport COA.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $accounts->links() }}
            </div>
        </div>
    </div>

    <!-- Create/Edit Modal -->
    @if($showCreateModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $editingAccountId ? 'Edit Account' : 'Create New Ledger Account' }}</h5>
                        <button type="button" wire:click="$set('showCreateModal', false)" class="btn-close"></button>
                    </div>
                    <form wire:submit.prevent="saveAccount">
                        <div class="modal-body">
                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Account Code <span class="text-danger">*</span></label>
                                    <input type="text" wire:model="account_code" class="form-control form-control-sm" placeholder="e.g. 5220" {{ $editingAccountId ? 'disabled' : '' }}>
                                    @error('account_code') <span class="text-danger fs--2">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Account Class <span class="text-danger">*</span></label>
                                    <select wire:model.live="account_type" class="form-select form-select-sm" {{ $editingAccountId ? 'disabled' : '' }}>
                                        <option value="asset">Asset (Debit)</option>
                                        <option value="liability">Liability (Credit)</option>
                                        <option value="equity">Equity (Credit)</option>
                                        <option value="revenue">Revenue (Credit)</option>
                                        <option value="expense">Expense (Debit)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-2">
                                <label class="form-label small fw-bold">Account Name <span class="text-danger">*</span></label>
                                <input type="text" wire:model="account_name" class="form-control form-control-sm" placeholder="e.g. Weigh-Bridge & Port Handling Charges">
                                @error('account_name') <span class="text-danger fs--2">{{ $message }}</span> @enderror
                            </div>

                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Sub-Group</label>
                                    <input type="text" wire:model="account_group" class="form-control form-control-sm" placeholder="e.g. direct_operating_cost">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Opening Balance</label>
                                    <input type="number" step="0.01" wire:model="opening_balance" class="form-control form-control-sm" {{ $editingAccountId ? 'disabled' : '' }}>
                                </div>
                            </div>

                            <div class="mb-2">
                                <label class="form-label small fw-bold">Description / Notes</label>
                                <textarea wire:model="description" class="form-control form-control-sm" rows="2" placeholder="Purpose and operational usage of this account..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" wire:click="$set('showCreateModal', false)" class="btn btn-secondary btn-sm">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-sm">Save Account</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>


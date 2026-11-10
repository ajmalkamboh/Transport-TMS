<div>
    @if(session()->has('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row g-3">
        <!-- Left: Financial Years Navigation & Info -->
        <div class="col-lg-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-calendar-alt me-1 text-primary"></i> Financial Years</h6>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="openCreateModal">
                        <i class="fas fa-plus-circle me-1"></i> New Fiscal Year
                    </button>
                </div>
                <div class="list-group list-group-flush fs--1">
                    @forelse($financialYears as $fy)
                        <a href="javascript:void(0)" wire:click="selectYear({{ $fy->id }})" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $selectedYear && $selectedYear->id === $fy->id ? 'active' : '' }}">
                            <div>
                                <div class="fw-bold">{{ $fy->name }}</div>
                                <div class="fs--2 {{ $selectedYear && $selectedYear->id === $fy->id ? 'text-white-50' : 'text-muted' }}">
                                    {{ $fy->start_date->format('M d, Y') }} &mdash; {{ $fy->end_date->format('M d, Y') }}
                                </div>
                            </div>
                            <div class="text-end">
                                @if($fy->is_current)
                                    <span class="badge bg-success mb-1">Current</span><br>
                                @endif
                                @if($fy->isOpen())
                                    <span class="badge bg-soft-info {{ $selectedYear && $selectedYear->id === $fy->id ? 'text-white' : 'text-info' }}">Open</span>
                                @else
                                    <span class="badge bg-soft-danger {{ $selectedYear && $selectedYear->id === $fy->id ? 'text-white' : 'text-danger' }}">Closed</span>
                                @endif
                            </div>
                        </a>
                    @empty
                        <div class="p-3 text-center text-muted">No financial years configured yet.</div>
                    @endforelse
                </div>
            </div>

            <!-- Currency Precision Card -->
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-coins me-1 text-warning"></i> Currency Precision</h6>
                </div>
                <div class="card-body fs--1">
                    <p class="text-600 mb-2">Transport ledger amounts are recorded in <strong>{{ $company->currency }}</strong> with precision of <strong>{{ $company->currency_precision }} decimal places</strong>.</p>
                    <div class="badge bg-200 text-dark font-monospace py-2 px-3">Example: {{ $company->currency }} {{ number_format(15420.5, $company->currency_precision) }}</div>
                </div>
            </div>
        </div>

        <!-- Right: Accounting Periods Detail -->
        <div class="col-lg-8">
            @if($selectedYear)
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 fw-bold">
                                {{ $selectedYear->name }} Accounting Periods
                                @if($selectedYear->isOpen())
                                    <span class="badge bg-soft-success text-success ms-2">Open for Postings</span>
                                @else
                                    <span class="badge bg-soft-danger text-danger ms-2">Fiscal Year Closed</span>
                                @endif
                            </h6>
                            <div class="fs--2 text-muted">12 sequential fiscal periods governing freight ledger journal entries.</div>
                        </div>
                        <div class="btn-group btn-group-sm">
                            @if(!$selectedYear->is_current)
                                <button type="button" class="btn btn-outline-success btn-sm" wire:click="makeCurrent({{ $selectedYear->id }})">
                                    <i class="fas fa-check me-1"></i> Set as Current Year
                                </button>
                            @endif
                            @if($selectedYear->isOpen())
                                <button type="button" class="btn btn-outline-danger btn-sm" wire:click="closeYear({{ $selectedYear->id }})" onclick="return confirm('Are you sure you want to close this entire financial year? All 12 periods will be locked.')">
                                    <i class="fas fa-lock me-1"></i> Close Fiscal Year
                                </button>
                            @endif
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped fs--1 mb-0 align-middle">
                                <thead class="bg-200">
                                    <tr>
                                        <th>#</th>
                                        <th>Period Title</th>
                                        <th>Date Range</th>
                                        <th>Status</th>
                                        <th>Closed Information</th>
                                        <th class="text-end">Period Control</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($selectedYear->periods as $period)
                                        <tr>
                                            <td class="fw-bold">{{ $period->period_number }}</td>
                                            <td class="fw-semi-bold text-primary">{{ $period->name }}</td>
                                            <td>{{ $period->start_date->format('M d, Y') }} &mdash; {{ $period->end_date->format('M d, Y') }}</td>
                                            <td>
                                                @if($period->isOpen())
                                                    <span class="badge bg-soft-success text-success"><i class="fas fa-lock-open me-1"></i> Open</span>
                                                @else
                                                    <span class="badge bg-soft-danger text-danger"><i class="fas fa-lock me-1"></i> Closed</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($period->isClosed())
                                                    <div class="fs--2 text-muted">Closed: {{ $period->closed_at?->format('M d, Y H:i') }}</div>
                                                    @if($period->closer)
                                                        <div class="fs--2 text-500">By: {{ $period->closer->name }}</div>
                                                    @endif
                                                @else
                                                    <span class="fs--2 text-muted">&mdash;</span>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                @if($period->isOpen())
                                                    <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 fs--2" wire:click="togglePeriodStatus({{ $period->id }})" title="Close Period">
                                                        <i class="fas fa-lock me-1"></i> Close
                                                    </button>
                                                @else
                                                    <button type="button" class="btn btn-outline-success btn-sm py-0 px-2 fs--2" wire:click="togglePeriodStatus({{ $period->id }})" title="Reopen Period">
                                                        <i class="fas fa-unlock me-1"></i> Reopen
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @else
                <div class="card shadow-sm p-4 text-center text-muted">
                    <p class="mb-2"><i class="fas fa-calendar-times fs-2 text-400"></i></p>
                    <h5>No Financial Year Selected</h5>
                    <p class="fs--1">Create your enterprise's first financial year to generate 12 monthly accounting periods.</p>
                    <button type="button" class="btn btn-primary btn-sm mx-auto" wire:click="openCreateModal">
                        <i class="fas fa-plus-circle me-1"></i> Create Financial Year
                    </button>
                </div>
            @endif
        </div>
    </div>

    <!-- Create Modal -->
    @if($showCreateModal)
    <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold"><i class="fas fa-calendar-plus me-1 text-primary"></i> Create Financial Year</h5>
                    <button type="button" class="btn-close" wire:click="$set('showCreateModal', false)"></button>
                </div>
                <form wire:submit.prevent="createFinancialYear">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fs--1 fw-semi-bold">Financial Year Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm @error('name') is-invalid @enderror" wire:model="name" placeholder="e.g. FY 2026-2027">
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">Start Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control form-control-sm @error('start_date') is-invalid @enderror" wire:model="start_date">
                                @error('start_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">End Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control form-control-sm @error('end_date') is-invalid @enderror" wire:model="end_date">
                                @error('end_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="makeCurrentCheck" wire:model="make_current">
                                    <label class="form-check-label fs--1 fw-semi-bold" for="makeCurrentCheck">Set as Active Current Fiscal Year</label>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="alert alert-info py-2 px-3 fs--2 mb-0">
                                    <i class="fas fa-info-circle me-1"></i> Creating a Financial Year will automatically initialize 12 sequential monthly accounting periods for transaction locking.
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="$set('showCreateModal', false)">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-check-circle me-1"></i> Initialize Fiscal Year
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>

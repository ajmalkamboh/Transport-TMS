<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 text-primary fw-bold"><i class="fas fa-id-card-alt me-2"></i>Driver Voyage Settlements</h4>
            <p class="text-muted small mb-0">Reconciliation of trip advances, duty allowances, out-of-pocket expenses, and net wage settlements</p>
        </div>
        <div class="d-flex gap-2">
            <button wire:click="openCreateModal" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-1"></i> New Driver Settlement
            </button>
        </div>
    </div>

    @if (session()->has('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if (session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-1"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Filters & Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-3">
            <div class="row g-2 mb-3">
                <div class="col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" wire:model.live.debounce.300ms="search" class="form-control" placeholder="Search by settlement #, driver name...">
                    </div>
                </div>
                <div class="col-md-4">
                    <select wire:model.live="driverFilter" class="form-select form-select-sm">
                        <option value="">All Drivers</option>
                        @foreach($drivers as $d)
                            <option value="{{ $d->id }}">{{ $d->name }} ({{ $d->license_number }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select wire:model.live="statusFilter" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="draft">Draft</option>
                        <option value="posted">Posted to GL</option>
                    </select>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-uppercase fs--1">
                        <tr>
                            <th>Settlement #</th>
                            <th>Date</th>
                            <th>Driver</th>
                            <th>Trip</th>
                            <th class="text-end">Total Advances</th>
                            <th class="text-end">Trip Allowance</th>
                            <th class="text-end">Reimbursements</th>
                            <th class="text-end">Net Balance</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($settlements as $st)
                            <tr>
                                <td>
                                    <span class="badge bg-light text-dark font-monospace fw-bold">{{ $st->settlement_number }}</span>
                                </td>
                                <td class="text-nowrap fs--1">{{ $st->settlement_date->format('d M Y') }}</td>
                                <td><span class="fw-semibold text-dark">{{ $st->driver?->name }}</span></td>
                                <td>
                                    @if($st->trip)
                                        <span class="badge bg-light text-muted">{{ $st->trip->trip_number }}</span>
                                    @else
                                        <span class="text-muted fs--2">—</span>
                                    @endif
                                </td>
                                <td class="text-end font-monospace text-muted">{{ number_format($st->total_advances, 2) }}</td>
                                <td class="text-end font-monospace text-dark">{{ number_format($st->driver_allowance, 2) }}</td>
                                <td class="text-end font-monospace text-dark">{{ number_format($st->out_of_pocket_expenses, 2) }}</td>
                                <td class="text-end font-monospace fw-bold">
                                    @if((float)$st->net_payable > 0)
                                        <span class="text-success">+{{ number_format($st->net_payable, 2) }} (Payable)</span>
                                    @elseif((float)$st->net_recoverable > 0)
                                        <span class="text-danger">-{{ number_format($st->net_recoverable, 2) }} (Recoverable)</span>
                                    @else
                                        <span class="text-muted">0.00 (Balanced)</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($st->status === 'posted')
                                        <span class="badge bg-success-subtle text-success">Posted</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary">Draft</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('print.driver-settlement', $st->id) }}" target="_blank" class="btn btn-outline-secondary" title="Print Settlement">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        @if($st->status === 'draft')
                                            <button wire:click="postSettlement({{ $st->id }})" class="btn btn-outline-primary" title="Post to General Ledger">
                                                <i class="fas fa-check-double"></i> Post
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-4 text-muted">No driver settlements recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $settlements->links() }}
            </div>
        </div>
    </div>

    <!-- Create Settlement Modal -->
    @if($showCreateModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-id-card-alt text-primary me-1"></i> New Driver Settlement</h5>
                        <button type="button" wire:click="$set('showCreateModal', false)" class="btn-close"></button>
                    </div>
                    <form wire:submit.prevent="saveSettlement">
                        <div class="modal-body">
                            <div class="mb-2">
                                <label class="form-label small fw-bold">Driver <span class="text-danger">*</span></label>
                                <select wire:model="selectedDriverId" class="form-select form-select-sm">
                                    <option value="">Select Driver...</option>
                                    @foreach($drivers as $d)
                                        <option value="{{ $d->id }}">{{ $d->name }} ({{ $d->license_number }})</option>
                                    @endforeach
                                </select>
                                @error('selectedDriverId') <span class="text-danger fs--2">{{ $message }}</span> @enderror
                            </div>

                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Linked Trip</label>
                                    <select wire:model="selectedTripId" class="form-select form-select-sm">
                                        <option value="">None / Standalone</option>
                                        @foreach($trips as $t)
                                            <option value="{{ $t->id }}">{{ $t->trip_number }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Settlement Date <span class="text-danger">*</span></label>
                                    <input type="date" wire:model="settlementDate" class="form-control form-control-sm">
                                </div>
                            </div>

                            <div class="row g-2 mb-2">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Trip Advances</label>
                                    <input type="number" step="0.01" wire:model.live="totalAdvances" class="form-control form-control-sm font-monospace" placeholder="0.00">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Trip Allowance <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" wire:model.live="driverAllowance" class="form-control form-control-sm font-monospace" placeholder="0.00">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Reimbursements</label>
                                    <input type="number" step="0.01" wire:model.live="outOfPocketExpenses" class="form-control form-control-sm font-monospace" placeholder="0.00">
                                </div>
                            </div>

                            @php
                                $earnings = (float)$driverAllowance + (float)$outOfPocketExpenses;
                                $adv = (float)$totalAdvances;
                                $net = $earnings - $adv;
                            @endphp
                            <div class="alert {{ $net >= 0 ? 'alert-success' : 'alert-warning' }} py-2 mb-2 small">
                                <strong>Calculated Result:</strong>
                                @if($net >= 0)
                                    Net Payable to Driver: <span class="fw-bold font-monospace">PKR {{ number_format($net, 2) }}</span>
                                @else
                                    Net Recoverable from Driver: <span class="fw-bold font-monospace">PKR {{ number_format(abs($net), 2) }}</span>
                                @endif
                            </div>

                            <div class="mb-2">
                                <label class="form-label small fw-bold">Notes</label>
                                <textarea wire:model="notes" class="form-control form-control-sm" rows="2" placeholder="Settlement details, route notes..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" wire:click="$set('showCreateModal', false)" class="btn btn-secondary btn-sm">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-sm">Create Settlement Draft</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>


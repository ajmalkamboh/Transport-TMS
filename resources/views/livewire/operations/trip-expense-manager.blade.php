<div>
    {{-- Header --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-8">
            <h3 class="mb-0 text-primary fw-bold"><i class="fas fa-money-bill-wave me-2"></i>Trip & Operational Expenses</h3>
            <p class="text-muted fs--1 mb-0">Toll Taxes, Driver Allowances, En-Route Accommodations, Multi-Stage Approval Workflow & Ledger Posting</p>
        </div>
        <div class="col-12 col-md-4 text-md-end">
            <button wire:click="openExpenseModal" class="btn btn-primary">
                <i class="fas fa-plus-circle me-1"></i> Record Trip Expense
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
    @if(session()->has('warning'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-1"></i> {{ session('warning') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-ban me-1"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- KPI Cards --}}
    <div class="row g-3 mb-3">
        <div class="col-4">
            <div class="card h-100 shadow-sm border-start border-4 border-primary">
                <div class="card-body p-3">
                    <div class="fs--2 text-muted text-uppercase fw-bold">TOTAL VOUCHERS AMOUNT</div>
                    <div class="fs-2 fw-bold text-primary">PKR {{ number_format($totalExpenses, 2) }}</div>
                    <div class="fs--2 text-muted mt-1">All recorded voyage expenses</div>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="card h-100 shadow-sm border-start border-4 border-dark">
                <div class="card-body p-3">
                    <div class="fs--2 text-muted text-uppercase fw-bold">POSTED TO LEDGER</div>
                    <div class="fs-2 fw-bold text-dark">PKR {{ number_format($postedExpenses, 2) }}</div>
                    <div class="fs--2 text-muted mt-1">Permanently locked & audited</div>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="card h-100 shadow-sm border-start border-4 border-warning">
                <div class="card-body p-3">
                    <div class="fs--2 text-muted text-uppercase fw-bold">PENDING APPROVAL</div>
                    <div class="fs-2 fw-bold text-warning">{{ $pendingCount }}</div>
                    <div class="fs--2 text-muted mt-1">Submitted vouchers awaiting review</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Toolbar --}}
    <div class="card mb-3">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" wire:model.live.debounce.300ms="search" class="form-control" placeholder="Search by Voucher #, Vehicle Reg, Trip #, Notes...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select wire:model.live="statusFilter" class="form-select">
                        <option value="">All Approval Statuses</option>
                        <option value="draft">Draft</option>
                        <option value="submitted">Submitted</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="posted">Posted (Locked)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <select wire:model.live="categoryFilter" class="form-select">
                        <option value="">All Expense Categories</option>
                        <option value="toll_tax">Toll Tax</option>
                        <option value="driver_allowance">Driver Allowance</option>
                        <option value="food">Food & Meals</option>
                        <option value="accommodation">Accommodation</option>
                        <option value="parking">Parking</option>
                        <option value="loading_unloading">Loading / Unloading</option>
                        <option value="route_expense">Route Expense</option>
                        <option value="emergency_repair">Emergency Repair</option>
                        <option value="fuel_surcharge">Fuel Surcharge</option>
                        <option value="police_fine">Police Fine</option>
                        <option value="weight_bridge">Weight Bridge</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    {{-- Expenses Table Card --}}
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Voucher # & Date</th>
                        <th>Trip & Vehicle</th>
                        <th>Category & Description</th>
                        <th class="text-end">Amount (PKR)</th>
                        <th>Payment Source</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($expenses as $exp)
                        <tr>
                            <td>
                                <a href="javascript:void(0)" wire:click="viewDetails({{ $exp->id }})" class="fw-bold text-primary text-decoration-none">
                                    {{ $exp->voucher_number }}
                                </a>
                                <div class="fs--2 text-muted">{{ $exp->expense_date->format('d M Y') }}</div>
                            </td>
                            <td>
                                @if($exp->trip)
                                    <div class="fw-semi-bold">{{ $exp->trip->trip_number }}</div>
                                    <div class="fs--2 text-muted">{{ $exp->trip->origin_city }} &rarr; {{ $exp->trip->destination_city }}</div>
                                @else
                                    <span class="text-muted fs--2">General Operational</span>
                                @endif
                                @if($exp->vehicle)
                                    <div class="fs--2 text-dark"><i class="fas fa-truck text-muted me-1"></i>{{ $exp->vehicle->registration_number }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-secondary fs--2">{{ strtoupper(str_replace('_', ' ', $exp->expense_category)) }}</span>
                                @if($exp->notes)<div class="fs--2 text-muted mt-1">{{ Str::limit($exp->notes, 40) }}</div>@endif
                            </td>
                            <td class="text-end fw-bold fs-0 text-success">
                                PKR {{ number_format($exp->amount, 2) }}
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border fs--2">{{ strtoupper(str_replace('_', ' ', $exp->payment_method)) }}</span>
                            </td>
                            <td>
                                @if($exp->status === 'draft')
                                    <span class="badge bg-secondary">DRAFT</span>
                                @elseif($exp->status === 'submitted')
                                    <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>SUBMITTED</span>
                                @elseif($exp->status === 'approved')
                                    <span class="badge bg-success"><i class="fas fa-check me-1"></i>APPROVED</span>
                                @elseif($exp->status === 'rejected')
                                    <span class="badge bg-danger">REJECTED</span>
                                @elseif($exp->status === 'posted')
                                    <span class="badge bg-dark"><i class="fas fa-lock me-1"></i>POSTED</span>
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                <div class="d-flex justify-content-end gap-1">
                                    @if($exp->status === 'draft')
                                        <button wire:click="submit({{ $exp->id }})" class="btn btn-sm btn-outline-warning py-0 fs--2" title="Submit for Approval">
                                            Submit
                                        </button>
                                    @elseif($exp->status === 'submitted')
                                        <button wire:click="approve({{ $exp->id }})" class="btn btn-sm btn-outline-success py-0 fs--2" title="Approve">
                                            Approve
                                        </button>
                                        <button wire:click="openRejectModal({{ $exp->id }})" class="btn btn-sm btn-outline-danger py-0 fs--2" title="Reject">
                                            Reject
                                        </button>
                                    @elseif($exp->status === 'approved')
                                        <button wire:click="post({{ $exp->id }})" class="btn btn-sm btn-dark py-0 fs--2" title="Post to Ledger">
                                            <i class="fas fa-lock me-1"></i> Post
                                        </button>
                                    @endif

                                    <a href="{{ route('print.expense-voucher', $exp->id) }}" target="_blank" class="btn btn-sm btn-outline-primary" title="Print Voucher">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    <button wire:click="viewDetails({{ $exp->id }})" class="btn btn-sm btn-outline-secondary" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="fas fa-receipt fa-2x mb-2 d-block"></i>
                                No expense vouchers matching your criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-light p-2">
            {{ $expenses->links() }}
        </div>
    </div>

    {{-- Record Expense Modal --}}
    @if($showExpenseModal)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="fas fa-receipt me-2"></i>Record Trip Expense Voucher</h5>
                        <button type="button" class="btn-close btn-close-white" wire:click="$set('showExpenseModal', false)"></button>
                    </div>
                    <form wire:submit.prevent="saveExpense">
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Linked Trip (Voyage)</label>
                                    <select wire:model.live="trip_id" class="form-select">
                                        <option value="">-- Optional Trip Allocation --</option>
                                        @foreach($trips as $t)
                                            <option value="{{ $t->id }}">{{ $t->trip_number }} &bull; {{ $t->origin_city }} &rarr; {{ $t->destination_city }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Vehicle</label>
                                    <select wire:model="vehicle_id" class="form-select">
                                        <option value="">-- Optional Vehicle --</option>
                                        @foreach($vehicles as $veh)
                                            <option value="{{ $veh->id }}">{{ $veh->registration_number }} ({{ $veh->make_model }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Claimant / Driver</label>
                                    <select wire:model="driver_id" class="form-select">
                                        <option value="">-- Driver / Employee --</option>
                                        @foreach($drivers as $drv)
                                            <option value="{{ $drv->id }}">{{ $drv->name }} ({{ $drv->phone }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Expense Date <span class="text-danger">*</span></label>
                                    <input type="date" wire:model="expense_date" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Expense Category <span class="text-danger">*</span></label>
                                    <select wire:model="expense_category" class="form-select" required>
                                        <option value="toll_tax">Toll Tax (Motorway / N-5)</option>
                                        <option value="driver_allowance">Driver Allowance / Batta</option>
                                        <option value="food">Meals & Food</option>
                                        <option value="accommodation">En-route Accommodation / Hotel</option>
                                        <option value="parking">Parking & Terminal Entry</option>
                                        <option value="loading_unloading">Loading & Unloading Labour</option>
                                        <option value="route_expense">Route Expense</option>
                                        <option value="emergency_repair">Emergency Roadside Repair</option>
                                        <option value="fuel_surcharge">Fuel Surcharge</option>
                                        <option value="police_fine">Traffic Penalty / Challan</option>
                                        <option value="weight_bridge">Weight Bridge (Kanta)</option>
                                        <option value="other">Other Operational</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Disbursement Amount (PKR) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" wire:model="amount" class="form-control" min="1" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Payment Method</label>
                                    <select wire:model="payment_method" class="form-select">
                                        <option value="cash">Cash in Hand (Petty Cash)</option>
                                        <option value="driver_advance">Driver Advance Clearing</option>
                                        <option value="company_card">Company Card</option>
                                        <option value="bank_transfer">Direct Bank Transfer</option>
                                        <option value="credit">Supplier / Vendor Credit</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" wire:model="auto_submit" id="autoSubmitCheck">
                                        <label class="form-check-label fs--1 fw-bold" for="autoSubmitCheck">
                                            Immediately Submit for Approval
                                        </label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold">Justification & Notes</label>
                                    <textarea wire:model="notes" class="form-control" rows="2" placeholder="Toll plaza name, receipt number, breakdown location..."></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="$set('showExpenseModal', false)">Cancel</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-check-circle me-1"></i> Save Expense Voucher</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Reject Modal --}}
    @if($showRejectModal && $rejectingExpense)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title"><i class="fas fa-ban me-2"></i>Reject Expense &bull; {{ $rejectingExpense->voucher_number }}</h5>
                        <button type="button" class="btn-close btn-close-white" wire:click="$set('showRejectModal', false)"></button>
                    </div>
                    <form wire:submit.prevent="confirmReject">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Reason for Rejection <span class="text-danger">*</span></label>
                                <textarea wire:model="rejection_reason" class="form-control" rows="3" placeholder="Receipt missing, unapproved charge, duplicate claim..." required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="$set('showRejectModal', false)">Cancel</button>
                            <button type="submit" class="btn btn-danger"><i class="fas fa-ban me-1"></i> Confirm Rejection</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Details Modal --}}
    @if($showDetailModal && $selectedExpense)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-light">
                        <h5 class="modal-title text-primary"><i class="fas fa-receipt me-2"></i>Voucher Details &bull; {{ $selectedExpense->voucher_number }}</h5>
                        <button type="button" class="btn-close" wire:click="$set('showDetailModal', false)"></button>
                    </div>
                    <div class="modal-body">
                        <div class="p-2 border rounded bg-light mb-3">
                            <div class="fs--1">Category: <strong>{{ strtoupper(str_replace('_', ' ', $selectedExpense->expense_category)) }}</strong></div>
                            <div class="fs--1">Trip: <strong>{{ $selectedExpense->trip?->trip_number ?: 'General Fleet' }}</strong></div>
                            <div class="fs--1">Vehicle: <strong>{{ $selectedExpense->vehicle?->registration_number ?: 'N/A' }}</strong></div>
                            <div class="fs--1">Driver: <strong>{{ $selectedExpense->driver?->name ?: 'N/A' }}</strong></div>
                            <div class="fs--1">Payment: <strong>{{ strtoupper(str_replace('_', ' ', $selectedExpense->payment_method)) }}</strong></div>
                            <div class="fs--1">Status: <span class="badge bg-primary">{{ strtoupper($selectedExpense->status) }}</span></div>
                        </div>
                        <div class="text-center p-3 border rounded mb-3 bg-light">
                            <div class="fs--2 text-muted">VOUCHER DISBURSEMENT AMOUNT</div>
                            <div class="fs-2 fw-bold text-success">PKR {{ number_format($selectedExpense->amount, 2) }}</div>
                        </div>
                        @if($selectedExpense->notes)
                            <div class="fs--1 mb-3">
                                <strong>Notes:</strong> {{ $selectedExpense->notes }}
                            </div>
                        @endif
                        @if($selectedExpense->rejection_reason)
                            <div class="alert alert-danger fs--1 p-2">
                                <strong>Rejection Reason:</strong> {{ $selectedExpense->rejection_reason }}
                            </div>
                        @endif
                        <div class="text-center">
                            <a href="{{ route('print.expense-voucher', $selectedExpense->id) }}" target="_blank" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-print me-1"></i> Print Expense Voucher
                            </a>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="$set('showDetailModal', false)">Close</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>


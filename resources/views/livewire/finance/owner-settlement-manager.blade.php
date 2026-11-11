<div>
    <!-- Notification Alerts -->
    @if (session()->has('success'))
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if (session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
            <div>{{ session('error') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Header & Action Bar -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h4 class="fw-bold mb-1">Attached Vehicle Owner Settlements & Ledgers</h4>
            <p class="text-muted mb-0 small">Manage commercial freight settlements, approved deductions, payment receipts, and running ledgers for third-party truck owners.</p>
        </div>
        <div class="d-flex gap-2">
            <button wire:click="openPaymentModal" class="btn btn-outline-success btn-sm">
                <i class="bi bi-cash-stack me-1"></i> Record Owner Payment
            </button>
            <button wire:click="openCreateModal" class="btn btn-primary btn-sm">
                <i class="bi bi-file-earmark-plus me-1"></i> New Trip Settlement
            </button>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-pills mb-4 border-bottom pb-2">
        <li class="nav-item">
            <button wire:click="setTab('settlements')" class="nav-link {{ $activeTab === 'settlements' ? 'active fw-semibold' : 'text-muted' }}">
                <i class="bi bi-receipt me-1"></i> Trip Settlements
            </button>
        </li>
        <li class="nav-item">
            <button wire:click="setTab('ledger')" class="nav-link {{ $activeTab === 'ledger' ? 'active fw-semibold' : 'text-muted' }}">
                <i class="bi bi-journal-text me-1"></i> Owner Ledgers
            </button>
        </li>
        <li class="nav-item">
            <button wire:click="setTab('payments')" class="nav-link {{ $activeTab === 'payments' ? 'active fw-semibold' : 'text-muted' }}">
                <i class="bi bi-credit-card-2-front me-1"></i> Payment History
            </button>
        </li>
    </ul>

    <!-- TAB 1: TRIP SETTLEMENTS -->
    @if ($activeTab === 'settlements')
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-3">
                <div class="row g-2">
                    <div class="col-md-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input type="text" wire:model.live.debounce.300ms="search" class="form-control" placeholder="Search by settlement # or owner name...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select wire:model.live="statusFilter" class="form-select form-select-sm">
                            <option value="">All Settlement Statuses</option>
                            <option value="draft">Draft</option>
                            <option value="submitted">Submitted</option>
                            <option value="approved">Approved</option>
                            <option value="posted">Posted (Ledger Permanent)</option>
                            <option value="paid">Paid</option>
                            <option value="partially_paid">Partially Paid</option>
                            <option value="reversed">Reversed</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select wire:model.live="ownerFilter" class="form-select form-select-sm">
                            <option value="">All Vehicle Owners</option>
                            @foreach ($owners as $owner)
                                <option value="{{ $owner->id }}">{{ $owner->name }} ({{ $owner->phone }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Settlement #</th>
                            <th>Date</th>
                            <th>Owner & Vehicle</th>
                            <th>Trip Reference</th>
                            <th>Model / Agreed</th>
                            <th>Deductions</th>
                            <th>Net Payable</th>
                            <th>Paid / Balance</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($settlements as $settlement)
                            <tr>
                                <td>
                                    <div class="fw-bold">{{ $settlement->settlement_number }}</div>
                                    <small class="text-muted">{{ $settlement->created_at->format('d M Y') }}</small>
                                </td>
                                <td>{{ $settlement->settlement_date->format('d M Y') }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $settlement->owner?->name }}</div>
                                    <small class="badge bg-secondary">{{ $settlement->vehicle?->registration_number }}</small>
                                </td>
                                <td>
                                    @if ($settlement->trip)
                                        <div class="fw-semibold text-primary">{{ $settlement->trip->trip_number }}</div>
                                        <small class="text-muted">{{ $settlement->trip->origin_city }} &rarr; {{ $settlement->trip->destination_city }}</small>
                                    @else
                                        <span class="text-muted">Direct / Period</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-bold">PKR {{ number_format($settlement->agreed_owner_payable, 2) }}</div>
                                    <small class="text-muted">{{ ucwords(str_replace('_', ' ', $settlement->payment_model)) }}</small>
                                </td>
                                <td>
                                    <span class="text-danger fw-semibold">-PKR {{ number_format($settlement->total_deductions, 2) }}</span>
                                    @if ($settlement->fuel_deduction > 0)
                                        <br><small class="text-muted">Fuel: {{ number_format($settlement->fuel_deduction, 0) }}</small>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-bold text-success fs-6">PKR {{ number_format($settlement->net_payable, 2) }}</div>
                                    @if ($settlement->additional_charges > 0)
                                        <small class="text-muted">+PKR {{ number_format($settlement->additional_charges, 0) }} extra</small>
                                    @endif
                                </td>
                                <td>
                                    <div class="text-muted small">Paid: PKR {{ number_format($settlement->paid_amount, 2) }}</div>
                                    <div class="fw-bold text-dark">Due: PKR {{ number_format($settlement->balance_due, 2) }}</div>
                                </td>
                                <td>
                                    @switch($settlement->status)
                                        @case('draft')
                                            <span class="badge bg-secondary">Draft</span>
                                            @break
                                        @case('submitted')
                                            <span class="badge bg-info text-dark">Submitted</span>
                                            @break
                                        @case('approved')
                                            <span class="badge bg-primary">Approved</span>
                                            @break
                                        @case('posted')
                                            <span class="badge bg-success">Posted</span>
                                            @break
                                        @case('partially_paid')
                                            <span class="badge bg-warning text-dark">Partially Paid</span>
                                            @break
                                        @case('paid')
                                            <span class="badge bg-success">Paid</span>
                                            @break
                                        @case('reversed')
                                            <span class="badge bg-danger">Reversed</span>
                                            @break
                                        @default
                                            <span class="badge bg-light text-dark">{{ $settlement->status }}</span>
                                    @endswitch
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('print.owner-settlement', $settlement->id) }}" target="_blank" class="btn btn-outline-secondary" title="Print Settlement Sheet">
                                            <i class="bi bi-printer"></i>
                                        </a>

                                        @if ($settlement->canSubmit())
                                            <button wire:click="submitSettlement({{ $settlement->id }})" class="btn btn-outline-info" title="Submit for Approval">
                                                <i class="bi bi-send"></i>
                                            </button>
                                        @endif

                                        @if ($settlement->canApprove())
                                            <button wire:click="approveSettlement({{ $settlement->id }})" class="btn btn-outline-primary" title="Approve Settlement">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        @endif

                                        @if ($settlement->canPost())
                                            <button wire:click="postSettlement({{ $settlement->id }})" class="btn btn-outline-success" title="Post to Owner Ledger">
                                                <i class="bi bi-check2-all"></i> Post
                                            </button>
                                        @endif

                                        @if ($settlement->isPosted() && $settlement->balance_due > 0)
                                            <button wire:click="openPaymentModal({{ $settlement->id }})" class="btn btn-success" title="Record Payment">
                                                <i class="bi bi-cash"></i> Pay
                                            </button>
                                        @endif

                                        @if ($settlement->canReverse())
                                            <button wire:click="openReverseModal({{ $settlement->id }})" class="btn btn-outline-danger" title="Reverse Settlement">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                    No owner settlements recorded yet. Click <strong>New Trip Settlement</strong> to create one.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($settlements->hasPages())
                <div class="card-footer bg-white border-0 py-3">
                    {{ $settlements->links() }}
                </div>
            @endif
        </div>
    @endif

    <!-- TAB 2: OWNER LEDGERS -->
    @if ($activeTab === 'ledger')
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-3">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Select Vehicle Owner</label>
                        <select wire:model.live="ledgerOwnerId" class="form-select form-select-sm">
                            <option value="">-- Choose Owner --</option>
                            @foreach ($owners as $owner)
                                <option value="{{ $owner->id }}">{{ $owner->name }} ({{ $owner->phone }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">From Date</label>
                        <input type="date" wire:model.live="ledgerFromDate" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">To Date</label>
                        <input type="date" wire:model.live="ledgerToDate" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-2 text-end">
                        @if ($ledgerOwnerId)
                            <a href="{{ route('print.owner-ledger', ['ownerId' => $ledgerOwnerId, 'from' => $ledgerFromDate, 'to' => $ledgerToDate]) }}" target="_blank" class="btn btn-outline-secondary btn-sm w-100">
                                <i class="bi bi-printer me-1"></i> Print Statement
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if ($ledgerSummary)
            <!-- KPI Summary Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card shadow-sm border-0 bg-light">
                        <div class="card-body p-3">
                            <div class="text-muted small fw-semibold">Opening Balance</div>
                            <h4 class="fw-bold mb-0 text-dark">PKR {{ number_format($ledgerSummary['opening_balance'], 2) }}</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm border-0 bg-light">
                        <div class="card-body p-3">
                            <div class="text-muted small fw-semibold">Total Trip Payables (Credits)</div>
                            <h4 class="fw-bold mb-0 text-primary">+PKR {{ number_format($ledgerSummary['total_trip_payables'], 2) }}</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm border-0 bg-light">
                        <div class="card-body p-3">
                            <div class="text-muted small fw-semibold">Deductions & Advances (Debits)</div>
                            <h4 class="fw-bold mb-0 text-danger">-PKR {{ number_format($ledgerSummary['total_deductions'] + $ledgerSummary['total_advances'], 2) }}</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm border-0 {{ $ledgerSummary['closing_balance'] > 0 ? 'bg-success text-white' : 'bg-secondary text-white' }}">
                        <div class="card-body p-3">
                            <div class="small fw-semibold opacity-75">Closing Outstanding Balance</div>
                            <h4 class="fw-bold mb-0">PKR {{ number_format($ledgerSummary['closing_balance'], 2) }}</h4>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ledger Table -->
            <div class="card shadow-sm border-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Transaction Type</th>
                                <th>Reference</th>
                                <th>Description</th>
                                <th class="text-end">Credit (+Payable)</th>
                                <th class="text-end">Debit (-Deduction/Paid)</th>
                                <th class="text-end">Running Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($ledgerSummary['entries'] as $entry)
                                <tr>
                                    <td>{{ $entry->transaction_date->format('d M Y') }}</td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            {{ ucwords(str_replace('_', ' ', $entry->transaction_type)) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-primary">{{ $entry->reference_number ?: '-' }}</span>
                                    </td>
                                    <td>{{ $entry->description }}</td>
                                    <td class="text-end fw-semibold text-success">
                                        {{ $entry->entry_type === 'credit' ? 'PKR ' . number_format($entry->amount, 2) : '-' }}
                                    </td>
                                    <td class="text-end fw-semibold text-danger">
                                        {{ $entry->entry_type === 'debit' ? 'PKR ' . number_format($entry->amount, 2) : '-' }}
                                    </td>
                                    <td class="text-end fw-bold {{ $entry->running_balance > 0 ? 'text-primary' : 'text-dark' }}">
                                        PKR {{ number_format($entry->running_balance, 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No transactions found for this owner in the selected date range.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="card shadow-sm border-0 text-center py-5">
                <div class="text-muted">
                    <i class="bi bi-person-lines-fill fs-1 d-block mb-2"></i>
                    Please select a vehicle owner above to view their financial ledger.
                </div>
            </div>
        @endif
    @endif

    <!-- TAB 3: PAYMENTS -->
    @if ($activeTab === 'payments')
        <div class="card shadow-sm border-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Receipt / Payment #</th>
                            <th>Date</th>
                            <th>Owner</th>
                            <th>Settlement Ref</th>
                            <th>Payment Method</th>
                            <th>Instrument / Bank Details</th>
                            <th class="text-end">Amount Paid</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payments as $payment)
                            <tr>
                                <td>
                                    <div class="fw-bold text-success">{{ $payment->payment_number }}</div>
                                    <small class="text-muted">{{ $payment->created_at->format('d M Y') }}</small>
                                </td>
                                <td>{{ $payment->payment_date->format('d M Y') }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $payment->owner?->name }}</div>
                                    <small class="text-muted">{{ $payment->owner?->phone }}</small>
                                </td>
                                <td>
                                    @if ($payment->settlement)
                                        <span class="badge bg-light text-dark border">{{ $payment->settlement->settlement_number }}</span>
                                    @else
                                        <span class="text-muted">On Account</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-secondary">{{ strtoupper(str_replace('_', ' ', $payment->payment_method)) }}</span>
                                </td>
                                <td>
                                    @if ($payment->cheque_number)
                                        <small class="d-block fw-semibold">Cheque: {{ $payment->cheque_number }} ({{ $payment->bank_name }})</small>
                                    @elseif ($payment->payment_reference)
                                        <small class="d-block text-muted">Ref: {{ $payment->payment_reference }}</small>
                                    @else
                                        <small class="text-muted">Direct Cash / Transfer</small>
                                    @endif
                                </td>
                                <td class="text-end fw-bold text-success fs-6">
                                    PKR {{ number_format($payment->amount, 2) }}
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('print.owner-payment', $payment->id) }}" target="_blank" class="btn btn-outline-secondary btn-sm" title="Print Payment Voucher">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">No owner payment records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($payments->hasPages())
                <div class="card-footer bg-white border-0 py-3">
                    {{ $payments->links() }}
                </div>
            @endif
        </div>
    @endif

    <!-- CREATE SETTLEMENT MODAL -->
    @if ($showCreateModal)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Prepare Owner Freight Settlement</h5>
                        <button type="button" wire:click="$set('showCreateModal', false)" class="btn-close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Step 1: Select Operationally Completed Trip -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-8">
                                <label class="form-label small fw-bold">Select Operationally Completed Trip</label>
                                <select wire:model.live="selectedTripId" class="form-select">
                                    <option value="">-- Choose Completed Trip --</option>
                                    @foreach ($eligibleTrips as $et)
                                        <option value="{{ $et->id }}">
                                            Trip #{{ $et->trip_number }} ({{ $et->origin_city }} &rarr; {{ $et->destination_city }}, Date: {{ $et->trip_date->format('d M') }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Settlement Date</label>
                                <input type="date" wire:model="settlementDate" class="form-control">
                            </div>
                        </div>

                        @if ($selectedTripId)
                            <div class="p-3 bg-light rounded border mb-3">
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <small class="text-muted d-block">Vehicle Owner</small>
                                        <strong>{{ $owners->find($selectedOwnerId)?->name }}</strong>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted d-block">Attached Vehicle</small>
                                        <strong>{{ $vehicles->find($selectedVehicleId)?->registration_number }}</strong>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted d-block">Customer Freight Total</small>
                                        <strong class="text-primary">PKR {{ number_format($customerFreightTotal, 2) }}</strong>
                                    </div>
                                </div>
                            </div>

                            <!-- Calculation Formula and Rate -->
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Agreed Owner Payable (Gross)</label>
                                    <input type="number" step="0.01" wire:model="agreedOwnerPayable" class="form-control fw-bold fs-6">
                                    <small class="text-muted">{{ $calculationNotes }}</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Or Override With Negotiated Rate</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" wire:model="customNegotiatedRate" class="form-control" placeholder="Special Rate">
                                        <button wire:click="applyNegotiatedRate" class="btn btn-outline-secondary" type="button">Apply</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Deductions Section -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label small fw-bold mb-0">Authorized Deductions (Fuel, Advances, Repairs)</label>
                                    <button wire:click="addManualDeduction" type="button" class="btn btn-outline-danger btn-sm">
                                        <i class="bi bi-plus-circle me-1"></i> Add Deduction / Penalty
                                    </button>
                                </div>

                                @if (count($pendingDeductions) > 0)
                                    <div class="table-responsive border rounded">
                                        <table class="table table-sm table-bordered align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Type</th>
                                                    <th>Description</th>
                                                    <th style="width: 140px;">Amount (PKR)</th>
                                                    <th style="width: 40px;"></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($pendingDeductions as $index => $ded)
                                                    <tr>
                                                        <td>
                                                            <span class="badge bg-secondary">{{ strtoupper($ded['deduction_type']) }}</span>
                                                        </td>
                                                        <td>
                                                            <input type="text" wire:model="pendingDeductions.{{ $index }}.description" class="form-control form-control-sm">
                                                        </td>
                                                        <td>
                                                            <input type="number" step="0.01" wire:model="pendingDeductions.{{ $index }}.amount" class="form-control form-control-sm text-end">
                                                        </td>
                                                        <td class="text-center">
                                                            <button wire:click="removeDeduction({{ $index }})" type="button" class="btn btn-sm text-danger p-0">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="text-muted small p-2 bg-light rounded text-center">
                                        No automatic deductions detected (e.g. no company fuel or advances on this trip).
                                    </div>
                                @endif
                            </div>

                            <!-- Additional Allowances -->
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Additional Charges / Bonus (PKR)</label>
                                    <input type="number" step="0.01" wire:model="additionalCharges" class="form-control" placeholder="0.00">
                                    <small class="text-muted">Demurrage, detention compensation, or extra unloading pay.</small>
                                </div>
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" wire:click="$set('showCreateModal', false)" class="btn btn-secondary">Cancel</button>
                        @if ($selectedTripId)
                            <button type="button" wire:click="saveSettlement" class="btn btn-primary">Create Draft Settlement</button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- REVERSAL MODAL -->
    @if ($showReverseModal)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title fw-bold">Reverse Owner Settlement</h5>
                        <button type="button" wire:click="$set('showReverseModal', false)" class="btn-close btn-close-white"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning small">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                            Reversing this settlement will cancel its status and write compensating debit/credit reversal entries to the owner ledger.
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Reason for Reversal <span class="text-danger">*</span></label>
                            <textarea wire:model="reversalReason" class="form-control" rows="3" placeholder="Explain why this settlement is being reversed (e.g., incorrect trip assignment, dispute in rates)..."></textarea>
                            @error('reversalReason') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" wire:click="$set('showReverseModal', false)" class="btn btn-secondary">Cancel</button>
                        <button type="button" wire:click="reverseSettlement" class="btn btn-danger">Confirm & Post Reversal</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- OWNER PAYMENT MODAL -->
    @if ($showPaymentModal)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Record Owner Payment</h5>
                        <button type="button" wire:click="$set('showPaymentModal', false)" class="btn-close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small fw-bold">Vehicle Owner</label>
                                <select wire:model.live="paymentOwnerId" class="form-select" {{ $paymentSettlementId ? 'disabled' : '' }}>
                                    <option value="">-- Choose Owner --</option>
                                    @foreach ($owners as $owner)
                                        <option value="{{ $owner->id }}">{{ $owner->name }} (Balance: PKR {{ number_format($owner->getCurrentBalance(), 2) }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Payment Amount (PKR)</label>
                                <input type="number" step="0.01" wire:model="paymentAmount" class="form-control fw-bold">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Payment Date</label>
                                <input type="date" wire:model="paymentDate" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Payment Method</label>
                                <select wire:model.live="paymentMethod" class="form-select">
                                    <option value="bank_transfer">Bank Transfer (Online/IBFT)</option>
                                    <option value="cheque">Bank Cheque</option>
                                    <option value="cash">Cash In Hand</option>
                                    <option value="online">Online Payment Gateway</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Reference / Transaction ID</label>
                                <input type="text" wire:model="paymentReference" class="form-control" placeholder="e.g. FT260012998">
                            </div>

                            @if ($paymentMethod === 'cheque')
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Cheque Number</label>
                                    <input type="text" wire:model="chequeNumber" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Cheque Date</label>
                                    <input type="date" wire:model="chequeDate" class="form-control">
                                </div>
                            @endif

                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Bank Name</label>
                                <input type="text" wire:model="bankName" class="form-control" placeholder="e.g. Habib Bank Limited">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Bank Account / IBAN</label>
                                <input type="text" wire:model="bankAccountNumber" class="form-control">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Notes</label>
                                <textarea wire:model="paymentNotes" class="form-control" rows="2" placeholder="Optional payment remarks..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" wire:click="$set('showPaymentModal', false)" class="btn btn-secondary">Cancel</button>
                        <button type="button" wire:click="savePayment" class="btn btn-success">Record & Post Payment</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>


<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 text-primary fw-bold"><i class="fas fa-university me-2"></i>Bank & Cash Management</h4>
            <p class="text-muted small mb-0">Commercial bank accounts, cash registers, inter-account contra transfers, and bank reconciliations</p>
        </div>
        <div class="d-flex gap-2">
            <button wire:click="openTransferModal" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-exchange-alt me-1"></i> Inter-Account Transfer (CV)
            </button>
            <button wire:click="openCreateAccountModal" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-1"></i> Add Bank / Cash Account
            </button>
        </div>
    </div>

    @if (session()->has('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Accounts Cards Grid -->
    <div class="row g-3 mb-4">
        @forelse($accounts as $acc)
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100 {{ (float)$acc->current_balance < 0 ? 'border-start border-danger border-4' : 'border-start border-primary border-4' }}">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <span class="badge {{ match($acc->account_type) {
                                    'bank' => 'bg-primary-subtle text-primary',
                                    'cash_in_hand' => 'bg-success-subtle text-success',
                                    default => 'bg-info-subtle text-info'
                                } }} text-uppercase">
                                    {{ str_replace('_', ' ', $acc->account_type) }}
                                </span>
                                <h6 class="fw-bold mt-1 mb-0">{{ $acc->account_name }}</h6>
                                @if($acc->bank_name)
                                    <div class="text-muted small">{{ $acc->bank_name }} - {{ $acc->branch_name }}</div>
                                @endif
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-link text-secondary p-0" data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end fs--1">
                                    <li><a class="dropdown-item" href="#" wire:click.prevent="openReconcileModal({{ $acc->id }})"><i class="fas fa-check-circle me-1 text-success"></i> Reconcile</a></li>
                                </ul>
                            </div>
                        </div>

                        <div class="mt-3">
                            <span class="text-muted fs--2 text-uppercase fw-semibold">Current Book Balance</span>
                            <h4 class="fw-bold mb-0 font-monospace {{ (float)$acc->current_balance < 0 ? 'text-danger' : 'text-dark' }}">
                                PKR {{ number_format($acc->current_balance, 2) }}
                            </h4>
                        </div>

                        @if($acc->account_number)
                            <div class="mt-2 text-muted fs--2 font-monospace">
                                A/C: {{ $acc->account_number }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card border-0 shadow-sm text-center py-4 text-muted">
                    <i class="fas fa-university fs-3 mb-2"></i>
                    <p class="mb-0">No bank or cash accounts configured. Click "Add Bank / Cash Account" to set up your treasury accounts.</p>
                </div>
            </div>
        @endforelse
    </div>

    <!-- Reconciliations History Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-light py-2">
            <h6 class="mb-0 fw-bold"><i class="fas fa-history me-1"></i> Bank Reconciliation History</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-uppercase fs--1">
                        <tr>
                            <th>Account</th>
                            <th>Statement Date</th>
                            <th class="text-end">Ending Statement Balance</th>
                            <th class="text-end">Book Balance</th>
                            <th class="text-end">Difference</th>
                            <th class="text-center">Status</th>
                            <th>Reconciled By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reconciliations as $rec)
                            <tr>
                                <td>
                                    <span class="fw-semibold text-dark">{{ $rec->bankAccount?->account_name }}</span>
                                </td>
                                <td>{{ $rec->statement_date->format('d M Y') }}</td>
                                <td class="text-end font-monospace">{{ number_format($rec->statement_ending_balance, 2) }}</td>
                                <td class="text-end font-monospace">{{ number_format($rec->book_balance, 2) }}</td>
                                <td class="text-end font-monospace fw-bold {{ abs($rec->difference) > 0.01 ? 'text-danger' : 'text-success' }}">
                                    {{ number_format($rec->difference, 2) }}
                                </td>
                                <td class="text-center">
                                    @if($rec->status === 'reconciled')
                                        <span class="badge bg-success-subtle text-success">Reconciled</span>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning">Draft</span>
                                    @endif
                                </td>
                                <td class="small text-muted">{{ $rec->reconciledByUser?->name ?? 'System' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-3 text-muted">No reconciliation sessions recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3">
                {{ $reconciliations->links() }}
            </div>
        </div>
    </div>

    <!-- Create Account Modal -->
    @if($showCreateAccountModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-university text-primary me-1"></i> Add Bank / Cash Account</h5>
                        <button type="button" wire:click="$set('showCreateAccountModal', false)" class="btn-close"></button>
                    </div>
                    <form wire:submit.prevent="saveAccount">
                        <div class="modal-body">
                            <div class="row g-2 mb-2">
                                <div class="col-md-7">
                                    <label class="form-label small fw-bold">Account Name <span class="text-danger">*</span></label>
                                    <input type="text" wire:model="account_name" class="form-control form-control-sm" placeholder="e.g. HBL Central Corporate">
                                    @error('account_name') <span class="text-danger fs--2">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small fw-bold">Account Type <span class="text-danger">*</span></label>
                                    <select wire:model.live="account_type" class="form-select form-select-sm">
                                        <option value="bank">Bank Checking / Deposit</option>
                                        <option value="cash_in_hand">Cash in Hand</option>
                                        <option value="petty_cash">Petty Cash Terminal</option>
                                    </select>
                                </div>
                            </div>

                            @if($account_type === 'bank')
                                <div class="row g-2 mb-2">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Bank Name</label>
                                        <input type="text" wire:model="bank_name" class="form-control form-control-sm" placeholder="e.g. Habib Bank Limited">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Branch Name / Code</label>
                                        <input type="text" wire:model="branch_name" class="form-control form-control-sm" placeholder="e.g. Circular Road Branch">
                                    </div>
                                </div>

                                <div class="row g-2 mb-2">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Account Number</label>
                                        <input type="text" wire:model="account_number" class="form-control form-control-sm" placeholder="e.g. 0102-9482910-01">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">IBAN</label>
                                        <input type="text" wire:model="iban" class="form-control form-control-sm" placeholder="PK36HABB00000...">
                                    </div>
                                </div>
                            @endif

                            <div class="mb-2">
                                <label class="form-label small fw-bold">Opening Balance (PKR)</label>
                                <input type="number" step="0.01" wire:model="opening_balance" class="form-control form-control-sm font-monospace" placeholder="0.00">
                            </div>

                            <div class="mb-2">
                                <label class="form-label small fw-bold">Notes</label>
                                <textarea wire:model="notes" class="form-control form-control-sm" rows="2" placeholder="Account purpose and authorized signatories..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" wire:click="$set('showCreateAccountModal', false)" class="btn btn-secondary btn-sm">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-sm">Save Account</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Inter-Account Transfer Modal (Contra Voucher) -->
    @if($showTransferModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-exchange-alt text-primary me-1"></i> Inter-Account Transfer (Contra Voucher)</h5>
                        <button type="button" wire:click="$set('showTransferModal', false)" class="btn-close"></button>
                    </div>
                    <form wire:submit.prevent="confirmTransfer">
                        <div class="modal-body">
                            <p class="small text-muted mb-3">
                                Executes an inter-treasury transfer (e.g. Cash-to-Bank, Bank-to-Cash, or Bank-to-Bank) via an immutable balanced Contra Voucher (CV).
                            </p>

                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">From Source Account <span class="text-danger">*</span></label>
                                    <select wire:model="fromAccountId" class="form-select form-select-sm">
                                        <option value="">Select Account...</option>
                                        @foreach($accounts as $acc)
                                            <option value="{{ $acc->id }}">{{ $acc->account_name }} ({{ number_format($acc->current_balance, 2) }})</option>
                                        @endforeach
                                    </select>
                                    @error('fromAccountId') <span class="text-danger fs--2">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">To Destination Account <span class="text-danger">*</span></label>
                                    <select wire:model="toAccountId" class="form-select form-select-sm">
                                        <option value="">Select Account...</option>
                                        @foreach($accounts as $acc)
                                            <option value="{{ $acc->id }}">{{ $acc->account_name }} ({{ number_format($acc->current_balance, 2) }})</option>
                                        @endforeach
                                    </select>
                                    @error('toAccountId') <span class="text-danger fs--2">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Transfer Amount (PKR) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" wire:model="transferAmount" class="form-control form-control-sm font-monospace" placeholder="0.00">
                                    @error('transferAmount') <span class="text-danger fs--2">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Transfer Date <span class="text-danger">*</span></label>
                                    <input type="date" wire:model="transferDate" class="form-control form-control-sm">
                                </div>
                            </div>

                            <div class="mb-2">
                                <label class="form-label small fw-bold">Reference / Slip #</label>
                                <input type="text" wire:model="transferReference" class="form-control form-control-sm" placeholder="e.g. DEP-SLIP-49102">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" wire:click="$set('showTransferModal', false)" class="btn btn-secondary btn-sm">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-sm">Execute Transfer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Bank Reconciliation Modal -->
    @if($showReconcileModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-check-circle text-success me-1"></i> Bank Account Reconciliation</h5>
                        <button type="button" wire:click="$set('showReconcileModal', false)" class="btn-close"></button>
                    </div>
                    <form wire:submit.prevent="confirmReconciliation">
                        <div class="modal-body">
                            <div class="row g-2 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Statement Date <span class="text-danger">*</span></label>
                                    <input type="date" wire:model="statementDate" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Starting Statement Balance</label>
                                    <input type="number" step="0.01" wire:model="statementStartingBalance" class="form-control form-control-sm font-monospace">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Ending Statement Balance <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" wire:model="statementEndingBalance" class="form-control form-control-sm font-monospace">
                                </div>
                            </div>

                            <h6 class="small fw-bold text-uppercase mb-2">Unreconciled Voucher Entries</h6>
                            <div class="table-responsive border rounded mb-3" style="max-height: 220px;">
                                <table class="table table-sm table-hover align-middle mb-0 fs--1">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 5%;">Clear</th>
                                            <th>Date</th>
                                            <th>Voucher #</th>
                                            <th>Memo</th>
                                            <th class="text-end">Debit</th>
                                            <th class="text-end">Credit</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($unreconciledItems as $item)
                                            <tr>
                                                <td>
                                                    <input type="checkbox" wire:model="clearedItemIds" value="{{ $item->id }}" class="form-check-input">
                                                </td>
                                                <td>{{ $item->voucher?->voucher_date?->format('d M') }}</td>
                                                <td class="font-monospace">{{ $item->voucher?->voucher_number }}</td>
                                                <td>{{ $item->description }}</td>
                                                <td class="text-end font-monospace">{{ number_format($item->debit, 2) }}</td>
                                                <td class="text-end font-monospace">{{ number_format($item->credit, 2) }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center py-2 text-muted">No unreconciled entries found.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" wire:click="$set('showReconcileModal', false)" class="btn btn-secondary btn-sm">Cancel</button>
                            <button type="submit" class="btn btn-success btn-sm">Complete Reconciliation</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>

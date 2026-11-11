<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 text-primary fw-bold"><i class="fas fa-book me-2"></i>General Ledger & Vouchers</h4>
            <p class="text-muted small mb-0">Double-entry audit log of all financial transactions, operational postings, and reversals</p>
        </div>
        <div class="d-flex gap-2">
            <button wire:click="openCreateModal" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-1"></i> New Journal Voucher
            </button>
        </div>
    </div>

    @if (session()->has('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
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
                        <input type="text" wire:model.live.debounce.300ms="search" class="form-control" placeholder="Search by voucher #, reference, narration...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select wire:model.live="typeFilter" class="form-select form-select-sm">
                        <option value="">All Voucher Types</option>
                        <option value="journal">General Journal (JV)</option>
                        <option value="cash_receipt">Cash Receipt (CR)</option>
                        <option value="cash_payment">Cash Payment (CP)</option>
                        <option value="bank_receipt">Bank Receipt (BR)</option>
                        <option value="bank_payment">Bank Payment (BP)</option>
                        <option value="contra">Contra Voucher (CV)</option>
                        <option value="adjustment">Adjustment Voucher (ADJ)</option>
                        <option value="opening">Opening Balance (OB)</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select wire:model.live="statusFilter" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="posted">Posted</option>
                        <option value="reversed">Reversed</option>
                    </select>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-uppercase fs--1">
                        <tr>
                            <th>Voucher #</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Narration / Entries</th>
                            <th class="text-end">Total Debit</th>
                            <th class="text-end">Total Credit</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($vouchers as $v)
                            <tr>
                                <td>
                                    <span class="badge bg-light text-dark font-monospace fw-bold">{{ $v->voucher_number }}</span>
                                    @if($v->reference_number)
                                        <div class="text-muted fs--2">Ref: {{ $v->reference_number }}</div>
                                    @endif
                                </td>
                                <td class="text-nowrap fs--1">
                                    {{ $v->voucher_date->format('d M Y') }}
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary text-uppercase">{{ str_replace('_', ' ', $v->voucher_type) }}</span>
                                </td>
                                <td style="max-width: 320px;">
                                    <div class="text-truncate fw-semibold text-dark fs--1">{{ $v->narration }}</div>
                                    <div class="fs--2 text-muted mt-1">
                                        @foreach($v->items->take(2) as $it)
                                            <span class="badge bg-light text-dark">{{ $it->account?->account_code }} {{ $it->entry_type == 'debit' ? 'Dr' : 'Cr' }} {{ number_format($it->debit > 0 ? $it->debit : $it->credit, 2) }}</span>
                                        @endforeach
                                        @if($v->items->count() > 2)
                                            <span class="badge bg-light text-muted">+{{ $v->items->count() - 2 }} more</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-end font-monospace fw-bold text-dark">
                                    {{ number_format($v->total_debit, 2) }}
                                </td>
                                <td class="text-end font-monospace fw-bold text-dark">
                                    {{ number_format($v->total_credit, 2) }}
                                </td>
                                <td class="text-center">
                                    @if($v->isPosted())
                                        <span class="badge bg-success-subtle text-success">Posted</span>
                                    @elseif($v->isReversed())
                                        <span class="badge bg-danger-subtle text-danger" title="Reversed: {{ $v->reversal_reason }}">Reversed</span>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning">{{ ucfirst($v->status) }}</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('print.journal-voucher', $v->id) }}" target="_blank" class="btn btn-outline-secondary" title="Print Voucher">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        @if($v->isPosted())
                                            <button wire:click="openReverseModal({{ $v->id }})" class="btn btn-outline-danger" title="Reverse Voucher">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="fas fa-receipt me-1"></i> No vouchers recorded yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $vouchers->links() }}
            </div>
        </div>
    </div>

    <!-- Create Journal Voucher Modal -->
    @if($showCreateModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-plus-circle me-1 text-primary"></i> Post New Journal Voucher</h5>
                        <button type="button" wire:click="$set('showCreateModal', false)" class="btn-close"></button>
                    </div>
                    <form wire:submit.prevent="saveVoucher">
                        <div class="modal-body">
                            <div class="row g-2 mb-3">
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Voucher Date <span class="text-danger">*</span></label>
                                    <input type="date" wire:model="voucher_date" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Voucher Type <span class="text-danger">*</span></label>
                                    <select wire:model="voucher_type" class="form-select form-select-sm">
                                        <option value="journal">General Journal (JV)</option>
                                        <option value="cash_receipt">Cash Receipt (CR)</option>
                                        <option value="cash_payment">Cash Payment (CP)</option>
                                        <option value="bank_receipt">Bank Receipt (BR)</option>
                                        <option value="bank_payment">Bank Payment (BP)</option>
                                        <option value="contra">Contra Voucher (CV)</option>
                                        <option value="adjustment">Adjustment Voucher (ADJ)</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Branch</label>
                                    <select wire:model="branch_id" class="form-select form-select-sm">
                                        <option value="">Head Office / All</option>
                                        @foreach($branches as $b)
                                            <option value="{{ $b->id }}">{{ $b->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Ref / Cheque #</label>
                                    <input type="text" wire:model="reference_number" class="form-control form-control-sm" placeholder="e.g. CHQ-9912">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold">Narration / Description <span class="text-danger">*</span></label>
                                <input type="text" wire:model="narration" class="form-control form-control-sm" placeholder="Detailed purpose of transaction...">
                            </div>

                            <!-- Line Entries -->
                            <div class="card border border-light-subtle mb-3">
                                <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                                    <span class="small fw-bold text-uppercase">Double-Entry Lines</span>
                                    <button type="button" wire:click="addLine" class="btn btn-xs btn-outline-primary">
                                        <i class="fas fa-plus me-1"></i> Add Line
                                    </button>
                                </div>
                                <div class="card-body p-2">
                                    @error('lines') <div class="alert alert-danger py-1 small mb-2">{{ $message }}</div> @enderror
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead>
                                                <tr class="fs--2 text-muted text-uppercase">
                                                    <th style="width: 35%;">Ledger Account</th>
                                                    <th style="width: 25%;">Line Memo</th>
                                                    <th style="width: 18%;" class="text-end">Debit</th>
                                                    <th style="width: 18%;" class="text-end">Credit</th>
                                                    <th style="width: 4%;"></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @php $sumDebit = 0; $sumCredit = 0; @endphp
                                                @foreach($lines as $idx => $line)
                                                    @php
                                                        $sumDebit += (float) ($line['debit'] ?? 0);
                                                        $sumCredit += (float) ($line['credit'] ?? 0);
                                                    @endphp
                                                    <tr>
                                                        <td>
                                                            <select wire:model="lines.{{ $idx }}.chart_of_account_id" class="form-select form-select-sm fs--1">
                                                                <option value="">Select Account...</option>
                                                                @foreach($accounts as $acc)
                                                                    <option value="{{ $acc->id }}">[{{ $acc->account_code }}] {{ $acc->account_name }}</option>
                                                                @endforeach
                                                            </select>
                                                        </td>
                                                        <td>
                                                            <input type="text" wire:model="lines.{{ $idx }}.description" class="form-control form-control-sm fs--1" placeholder="Memo">
                                                        </td>
                                                        <td>
                                                            <input type="number" step="0.01" wire:model.live="lines.{{ $idx }}.debit" class="form-control form-control-sm font-monospace text-end" placeholder="0.00">
                                                        </td>
                                                        <td>
                                                            <input type="number" step="0.01" wire:model.live="lines.{{ $idx }}.credit" class="form-control form-control-sm font-monospace text-end" placeholder="0.00">
                                                        </td>
                                                        <td class="text-center">
                                                            @if(count($lines) > 2)
                                                                <button type="button" wire:click="removeLine({{ $idx }})" class="btn btn-link text-danger p-0 fs--1">
                                                                    <i class="fas fa-times"></i>
                                                                </button>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                            <tfoot class="border-top fw-bold fs--1">
                                                <tr>
                                                    <td colspan="2" class="text-end">Totals:</td>
                                                    <td class="text-end font-monospace text-primary">{{ number_format($sumDebit, 2) }}</td>
                                                    <td class="text-end font-monospace text-primary">{{ number_format($sumCredit, 2) }}</td>
                                                    <td></td>
                                                </tr>
                                                <tr>
                                                    <td colspan="2" class="text-end">Balance Check:</td>
                                                    <td colspan="2" class="text-end font-monospace {{ abs($sumDebit - $sumCredit) < 0.01 && $sumDebit > 0 ? 'text-success' : 'text-danger' }}">
                                                        @if(abs($sumDebit - $sumCredit) < 0.01 && $sumDebit > 0)
                                                            <i class="fas fa-check-circle me-1"></i> Balanced (Diff: 0.00)
                                                        @else
                                                            <i class="fas fa-exclamation-triangle me-1"></i> Unbalanced (Diff: {{ number_format(abs($sumDebit - $sumCredit), 2) }})
                                                        @endif
                                                    </td>
                                                    <td></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" wire:click="$set('showCreateModal', false)" class="btn btn-secondary btn-sm">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-sm" {{ abs($sumDebit - $sumCredit) >= 0.01 || $sumDebit <= 0 ? 'disabled' : '' }}>
                                <i class="fas fa-check me-1"></i> Post to Ledger
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Reversal Modal -->
    @if($showReverseModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger"><i class="fas fa-undo me-1"></i> Reverse Journal Voucher</h5>
                        <button type="button" wire:click="$set('showReverseModal', false)" class="btn-close"></button>
                    </div>
                    <form wire:submit.prevent="confirmReverse">
                        <div class="modal-body">
                            <p class="small text-muted mb-3">
                                This will generate an offsetting Adjustment Voucher swapping debits and credits, atomically reverting the ledger balances. The original voucher record will remain permanently preserved for audit compliance.
                            </p>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Mandatory Reversal Reason <span class="text-danger">*</span></label>
                                <textarea wire:model="reversalReason" class="form-control form-control-sm" rows="3" placeholder="Provide audit reason for reversal..."></textarea>
                                @error('reversalReason') <span class="text-danger fs--2">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" wire:click="$set('showReverseModal', false)" class="btn btn-secondary btn-sm">Cancel</button>
                            <button type="submit" class="btn btn-danger btn-sm">Confirm Reversal</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>

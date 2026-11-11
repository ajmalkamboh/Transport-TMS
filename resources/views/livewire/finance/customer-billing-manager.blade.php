<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 text-primary fw-bold"><i class="fas fa-file-invoice-dollar me-2"></i>Customer Invoicing & Billing</h4>
            <p class="text-muted small mb-0">Freight receivable billing, customer advances, payment receipts, and reconciliation</p>
        </div>
        <div class="d-flex gap-2">
            <button wire:click="openPaymentModal" class="btn btn-outline-success btn-sm">
                <i class="fas fa-receipt me-1"></i> Receive Payment / Advance
            </button>
            <button wire:click="openCreateInvoiceModal" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-1"></i> New Freight Invoice
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

    <!-- Nav Tabs -->
    <ul class="nav nav-pills mb-3" role="tablist">
        <li class="nav-item">
            <button wire:click="$set('activeTab', 'invoices')" class="nav-link py-1 px-3 {{ $activeTab === 'invoices' ? 'active' : '' }}">
                <i class="fas fa-file-invoice me-1"></i> Freight Invoices
            </button>
        </li>
        <li class="nav-item">
            <button wire:click="$set('activeTab', 'receipts')" class="nav-link py-1 px-3 {{ $activeTab === 'receipts' ? 'active' : '' }}">
                <i class="fas fa-money-check-alt me-1"></i> Collections & Advances
            </button>
        </li>
    </ul>

    <!-- Filter Bar -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <div class="row g-2">
                <div class="col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" wire:model.live.debounce.300ms="search" class="form-control" placeholder="Search by number, customer...">
                    </div>
                </div>
                <div class="col-md-4">
                    <select wire:model.live="customerFilter" class="form-select form-select-sm">
                        <option value="">All Commercial Customers</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                @if($activeTab === 'invoices')
                    <div class="col-md-3">
                        <select wire:model.live="statusFilter" class="form-select form-select-sm">
                            <option value="">All Statuses</option>
                            <option value="draft">Draft</option>
                            <option value="posted">Posted (Unpaid)</option>
                            <option value="partially_paid">Partially Paid</option>
                            <option value="paid">Fully Paid</option>
                        </select>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if($activeTab === 'invoices')
        <!-- Invoices Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-uppercase fs--1">
                            <tr>
                                <th>Invoice #</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th class="text-end">Total Amount</th>
                                <th class="text-end">Paid Amount</th>
                                <th class="text-end">Balance Due</th>
                                <th class="text-center">Posting Status</th>
                                <th class="text-center">Payment Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($invoices as $inv)
                                <tr>
                                    <td>
                                        <span class="badge bg-light text-dark font-monospace fw-bold">{{ $inv->invoice_number }}</span>
                                        @if($inv->trip)
                                            <div class="text-muted fs--2">Trip: {{ $inv->trip->trip_number }}</div>
                                        @endif
                                    </td>
                                    <td class="text-nowrap fs--1">
                                        {{ $inv->invoice_date->format('d M Y') }}
                                        <div class="text-muted fs--2">Due: {{ $inv->due_date->format('d M') }}</div>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-dark">{{ $inv->customer?->name }}</span>
                                    </td>
                                    <td class="text-end font-monospace fw-bold text-dark">
                                        {{ number_format($inv->total_amount, 2) }}
                                    </td>
                                    <td class="text-end font-monospace text-success">
                                        {{ number_format($inv->paid_amount, 2) }}
                                    </td>
                                    <td class="text-end font-monospace fw-bold {{ (float)$inv->balance_due > 0 ? 'text-danger' : 'text-muted' }}">
                                        {{ number_format($inv->balance_due, 2) }}
                                    </td>
                                    <td class="text-center">
                                        @if($inv->isPosted())
                                            <span class="badge bg-success-subtle text-success">Posted</span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary">Draft</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($inv->payment_status === 'paid')
                                            <span class="badge bg-success text-white">Paid</span>
                                        @elseif($inv->payment_status === 'partially_paid')
                                            <span class="badge bg-warning text-dark">Partially Paid</span>
                                        @else
                                            <span class="badge bg-danger-subtle text-danger">Unpaid</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route('print.customer-invoice', $inv->id) }}" target="_blank" class="btn btn-outline-secondary" title="Print Invoice">
                                                <i class="fas fa-print"></i>
                                            </a>
                                            @if(!$inv->isPosted())
                                                <button wire:click="postInvoice({{ $inv->id }})" class="btn btn-outline-primary" title="Post to General Ledger">
                                                    <i class="fas fa-check-double"></i> Post
                                                </button>
                                            @elseif((float)$inv->balance_due > 0)
                                                <button wire:click="openPaymentModal({{ $inv->id }}, {{ $inv->customer_id }})" class="btn btn-outline-success" title="Receive Payment">
                                                    <i class="fas fa-dollar-sign"></i> Pay
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">
                                        <i class="fas fa-file-invoice me-1"></i> No invoices found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-3">
                    {{ $invoices->links() }}
                </div>
            </div>
        </div>
    @else
        <!-- Receipts / Advances Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-uppercase fs--1">
                            <tr>
                                <th>Receipt #</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Type</th>
                                <th>Payment Method</th>
                                <th class="text-end">Total Received</th>
                                <th class="text-end">Unapplied Advance</th>
                                <th class="text-center">Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($receipts as $rec)
                                <tr>
                                    <td>
                                        <span class="badge bg-light text-dark font-monospace fw-bold">{{ $rec->receipt_number }}</span>
                                        @if($rec->reference_number)
                                            <div class="text-muted fs--2">Ref: {{ $rec->reference_number }}</div>
                                        @endif
                                    </td>
                                    <td class="text-nowrap fs--1">
                                        {{ $rec->receipt_date->format('d M Y') }}
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-dark">{{ $rec->customer?->name }}</span>
                                    </td>
                                    <td>
                                        @if($rec->isAdvance())
                                            <span class="badge bg-warning-subtle text-warning">Customer Advance</span>
                                        @else
                                            <span class="badge bg-info-subtle text-info">Invoice Payment</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-muted text-uppercase">{{ str_replace('_', ' ', $rec->payment_method) }}</span>
                                        @if($rec->bankAccount)
                                            <div class="text-muted fs--2">{{ $rec->bankAccount->account_name }}</div>
                                        @endif
                                    </td>
                                    <td class="text-end font-monospace fw-bold text-success">
                                        {{ number_format($rec->amount, 2) }}
                                    </td>
                                    <td class="text-end font-monospace fw-bold {{ (float)$rec->unapplied_amount > 0 ? 'text-warning' : 'text-muted' }}">
                                        {{ number_format($rec->unapplied_amount, 2) }}
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-success-subtle text-success">Posted</span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route('print.customer-receipt', $rec->id) }}" target="_blank" class="btn btn-outline-secondary" title="Print Receipt">
                                                <i class="fas fa-print"></i>
                                            </a>
                                            @if($rec->isAdvance() && (float)$rec->unapplied_amount > 0)
                                                <button wire:click="openApplyAdvanceModal({{ $rec->id }})" class="btn btn-outline-primary" title="Apply Advance to Invoice">
                                                    <i class="fas fa-link"></i> Apply
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">
                                        <i class="fas fa-receipt me-1"></i> No payment receipts recorded yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-3">
                    {{ $receipts->links() }}
                </div>
            </div>
        </div>
    @endif

    <!-- Create Invoice Modal -->
    @if($showCreateInvoiceModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-file-invoice text-primary me-1"></i> Create Freight Invoice</h5>
                        <button type="button" wire:click="$set('showCreateInvoiceModal', false)" class="btn-close"></button>
                    </div>
                    <form wire:submit.prevent="saveInvoice">
                        <div class="modal-body">
                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Customer <span class="text-danger">*</span></label>
                                    <select wire:model="selectedCustomerId" class="form-select form-select-sm">
                                        <option value="">Select Customer...</option>
                                        @foreach($customers as $c)
                                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('selectedCustomerId') <span class="text-danger fs--2">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Linked Trip (Optional)</label>
                                    <select wire:model="selectedTripId" class="form-select form-select-sm">
                                        <option value="">None / Standalone Billing</option>
                                        @foreach($trips as $t)
                                            <option value="{{ $t->id }}">{{ $t->trip_number }} ({{ $t->originBranch?->name }} -> {{ $t->destinationBranch?->name }})</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="row g-2 mb-2">
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Invoice Date <span class="text-danger">*</span></label>
                                    <input type="date" wire:model="invoiceDate" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Due Date <span class="text-danger">*</span></label>
                                    <input type="date" wire:model="dueDate" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Tax Rate %</label>
                                    <input type="number" step="0.01" wire:model.live="taxRatePercent" class="form-control form-control-sm" placeholder="0.00">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Discount Amount</label>
                                    <input type="number" step="0.01" wire:model.live="discountAmount" class="form-control form-control-sm" placeholder="0.00">
                                </div>
                            </div>

                            <!-- Invoice Items -->
                            <div class="card border border-light-subtle mb-2">
                                <div class="card-header bg-light py-1 d-flex justify-content-between align-items-center">
                                    <span class="small fw-bold">Line Items</span>
                                    <button type="button" wire:click="addInvoiceItem" class="btn btn-xs btn-outline-primary">
                                        <i class="fas fa-plus me-1"></i> Add Item
                                    </button>
                                </div>
                                <div class="card-body p-2">
                                    @foreach($invoiceItems as $i => $item)
                                        <div class="row g-2 mb-1 align-items-center">
                                            <div class="col-md-5">
                                                <input type="text" wire:model="invoiceItems.{{ $i }}.description" class="form-control form-control-sm" placeholder="Description of service">
                                            </div>
                                            <div class="col-md-2">
                                                <input type="number" step="0.01" wire:model="invoiceItems.{{ $i }}.quantity" class="form-control form-control-sm text-end" placeholder="Qty">
                                            </div>
                                            <div class="col-md-2">
                                                <input type="number" step="0.01" wire:model="invoiceItems.{{ $i }}.unit_rate" class="form-control form-control-sm text-end" placeholder="Rate">
                                            </div>
                                            <div class="col-md-2">
                                                <input type="number" step="0.01" wire:model="invoiceItems.{{ $i }}.amount" class="form-control form-control-sm text-end font-monospace" placeholder="Amount">
                                            </div>
                                            <div class="col-md-1 text-center">
                                                @if(count($invoiceItems) > 1)
                                                    <button type="button" wire:click="removeInvoiceItem({{ $i }})" class="btn btn-link text-danger p-0">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="mb-2">
                                <label class="form-label small fw-bold">Billing Notes</label>
                                <textarea wire:model="notes" class="form-control form-control-sm" rows="2" placeholder="Payment terms, bank details, delivery notes..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" wire:click="$set('showCreateInvoiceModal', false)" class="btn btn-secondary btn-sm">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-sm">Save Invoice Draft</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Payment / Advance Receipt Modal -->
    @if($showPaymentModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-receipt text-success me-1"></i> Record Customer Receipt</h5>
                        <button type="button" wire:click="$set('showPaymentModal', false)" class="btn-close"></button>
                    </div>
                    <form wire:submit.prevent="savePayment">
                        <div class="modal-body">
                            <div class="mb-2">
                                <label class="form-label small fw-bold">Customer <span class="text-danger">*</span></label>
                                <select wire:model="paymentCustomerId" class="form-select form-select-sm" {{ $paymentInvoiceId ? 'disabled' : '' }}>
                                    <option value="">Select Customer...</option>
                                    @foreach($customers as $c)
                                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Payment Category <span class="text-danger">*</span></label>
                                    <select wire:model.live="paymentType" class="form-select form-select-sm" {{ $paymentInvoiceId ? 'disabled' : '' }}>
                                        <option value="invoice_payment">Payment Against Invoice</option>
                                        <option value="customer_advance">Advance Deposit (Held as Liability)</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Receipt Date <span class="text-danger">*</span></label>
                                    <input type="date" wire:model="paymentDate" class="form-control form-control-sm">
                                </div>
                            </div>

                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Amount (PKR) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" wire:model="paymentAmount" class="form-control form-control-sm font-monospace">
                                    @error('paymentAmount') <span class="text-danger fs--2">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Payment Method</label>
                                    <select wire:model="paymentMethod" class="form-select form-select-sm">
                                        <option value="cash">Cash</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="cheque">Cheque</option>
                                        <option value="online">Online Banking</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-2">
                                <label class="form-label small fw-bold">Receiving Bank/Cash Account</label>
                                <select wire:model="paymentBankAccountId" class="form-select form-select-sm">
                                    <option value="">Default Central Account</option>
                                    @foreach($bankAccounts as $ba)
                                        <option value="{{ $ba->id }}">{{ $ba->account_name }} ({{ $ba->bank_name ?? 'Cash' }})</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Reference / Transaction ID</label>
                                    <input type="text" wire:model="referenceNumber" class="form-control form-control-sm" placeholder="e.g. FT-284920">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Cheque # (If applicable)</label>
                                    <input type="text" wire:model="chequeNumber" class="form-control form-control-sm" placeholder="e.g. CHQ-10492">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" wire:click="$set('showPaymentModal', false)" class="btn btn-secondary btn-sm">Cancel</button>
                            <button type="submit" class="btn btn-success btn-sm">Record Receipt</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Apply Advance Modal -->
    @if($showApplyAdvanceModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-link text-primary me-1"></i> Apply Customer Advance to Invoice</h5>
                        <button type="button" wire:click="$set('showApplyAdvanceModal', false)" class="btn-close"></button>
                    </div>
                    <form wire:submit.prevent="confirmApplyAdvance">
                        <div class="modal-body">
                            <p class="small text-muted mb-2">
                                Reclassifies liability from <span class="badge bg-secondary">2200-CUSTOMER-ADVANCES</span> to settle outstanding Accounts Receivable on the chosen invoice.
                            </p>

                            <div class="mb-2">
                                <label class="form-label small fw-bold">Target Invoice <span class="text-danger">*</span></label>
                                <select wire:model="targetInvoiceId" class="form-select form-select-sm">
                                    <option value="">Select Unpaid Invoice...</option>
                                    @foreach($openInvoicesForAdvance as $openInv)
                                        <option value="{{ $openInv->id }}">
                                            {{ $openInv->invoice_number }} - Due: {{ number_format($openInv->balance_due, 2) }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('targetInvoiceId') <span class="text-danger fs--2">{{ $message }}</span> @enderror
                            </div>

                            <div class="mb-2">
                                <label class="form-label small fw-bold">Allocation Amount <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" wire:model="allocationAmount" class="form-control form-control-sm font-monospace">
                                @error('allocationAmount') <span class="text-danger fs--2">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" wire:click="$set('showApplyAdvanceModal', false)" class="btn btn-secondary btn-sm">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-sm">Confirm Allocation</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>


<div>
    <!-- Summary KPI Header -->
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card bg-soft-success border-success h-100">
                <div class="card-body p-3">
                    <div class="fs--1 text-success fw-bold text-uppercase">Total Verified Revenue</div>
                    <h3 class="fw-bolder text-success mb-0 mt-1">PKR {{ number_format($totalCollected, 2) }}</h3>
                    <div class="fs--2 text-700 mt-1">Cleared subscription payments</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-soft-danger border-danger h-100">
                <div class="card-body p-3">
                    <div class="fs--1 text-danger fw-bold text-uppercase">Total Outstanding Invoices</div>
                    <h3 class="fw-bolder text-danger mb-0 mt-1">PKR {{ number_format($totalOutstanding, 2) }}</h3>
                    <div class="fs--2 text-700 mt-1">Awaiting tenant settlement</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-soft-warning border-warning h-100">
                <div class="card-body p-3">
                    <div class="fs--1 text-warning fw-bold text-uppercase">Pending Verifications</div>
                    <h3 class="fw-bolder text-warning mb-0 mt-1">{{ $pendingVerificationCount }}</h3>
                    <div class="fs--2 text-700 mt-1">Manual bank receipts requiring review</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Bar -->
    <div class="card shadow-sm border mb-3">
        <div class="card-header bg-light p-2">
            <ul class="nav nav-pills">
                <li class="nav-item">
                    <a class="nav-link fs--1 py-1 px-3 {{ $activeTab === 'invoices' ? 'active' : '' }}" href="#" wire:click.prevent="$set('activeTab', 'invoices')">
                        <i class="fas fa-file-invoice me-1"></i> Subscription Invoices
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fs--1 py-1 px-3 {{ $activeTab === 'payments' ? 'active' : '' }}" href="#" wire:click.prevent="$set('activeTab', 'payments')">
                        <i class="fas fa-receipt me-1"></i> Payment Receipts
                        @if($pendingVerificationCount > 0)
                            <span class="badge bg-danger rounded-pill ms-1">{{ $pendingVerificationCount }}</span>
                        @endif
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Tab 1: Subscription Invoices -->
    @if($activeTab === 'invoices')
    <div class="card shadow-sm border">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold">Platform Invoices</h6>
            <div class="w-25">
                <select class="form-select form-select-sm" wire:model.live="invoiceStatusFilter">
                    <option value="">All Invoice Statuses</option>
                    <option value="unpaid">Unpaid</option>
                    <option value="paid">Paid</option>
                    <option value="partially_paid">Partially Paid</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped fs--1 mb-0">
                    <thead class="bg-200">
                        <tr>
                            <th>Invoice #</th>
                            <th>Company</th>
                            <th>Cycle</th>
                            <th>Issued / Due</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($invoices as $inv)
                        <tr>
                            <td class="fw-bold">{{ $inv->invoice_number }}</td>
                            <td class="text-primary">{{ $inv->company?->name }}</td>
                            <td><span class="badge bg-200 text-dark text-capitalize">{{ $inv->billing_cycle }}</span></td>
                            <td>
                                <div>{{ $inv->invoice_date->format('d M Y') }}</div>
                                <div class="fs--2 text-muted">Due: {{ $inv->due_date->format('d M Y') }}</div>
                            </td>
                            <td class="fw-bold">PKR {{ number_format($inv->total_amount, 2) }}</td>
                            <td class="text-success">PKR {{ number_format($inv->paid_amount, 2) }}</td>
                            <td class="fw-bold {{ $inv->balance_amount > 0 ? 'text-danger' : 'text-success' }}">
                                PKR {{ number_format($inv->balance_amount, 2) }}
                            </td>
                            <td>
                                @if($inv->status === 'paid')
                                    <span class="badge bg-soft-success text-success">Paid</span>
                                @elseif($inv->status === 'partially_paid')
                                    <span class="badge bg-soft-warning text-warning">Partial</span>
                                @else
                                    <span class="badge bg-soft-danger text-danger">Unpaid</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($inv->balance_amount > 0)
                                    <button class="btn btn-outline-success btn-sm py-0 px-2" wire:click="openRecordPaymentModal({{ $inv->id }})">
                                        <i class="fas fa-money-bill-wave me-1"></i> Log Payment
                                    </button>
                                @else
                                    <span class="badge bg-soft-secondary text-secondary">Settled</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center py-4 text-500">No subscription invoices found.</td>
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
    @endif

    <!-- Tab 2: Subscription Payments -->
    @if($activeTab === 'payments')
    <div class="card shadow-sm border">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold">Payment Receipts</h6>
            <div class="w-25">
                <select class="form-select form-select-sm" wire:model.live="paymentStatusFilter">
                    <option value="">All Payment Statuses</option>
                    <option value="pending_verification">Pending Verification</option>
                    <option value="verified">Verified</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped fs--1 mb-0">
                    <thead class="bg-200">
                        <tr>
                            <th>Payment #</th>
                            <th>Company</th>
                            <th>Invoice #</th>
                            <th>Payment Date</th>
                            <th>Method & Ref</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th class="text-end">Verification</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payments as $pay)
                        <tr>
                            <td class="fw-bold">{{ $pay->payment_number }}</td>
                            <td class="text-primary">{{ $pay->company?->name }}</td>
                            <td><code>{{ $pay->invoice?->invoice_number }}</code></td>
                            <td>{{ $pay->payment_date->format('d M Y') }}</td>
                            <td>
                                <span class="badge bg-200 text-dark text-capitalize">{{ str_replace('_', ' ', $pay->payment_method) }}</span>
                                @if($pay->payment_reference)
                                    <div class="fs--2 text-muted">Ref: {{ $pay->payment_reference }}</div>
                                @endif
                            </td>
                            <td class="fw-bold text-success">PKR {{ number_format($pay->amount, 2) }}</td>
                            <td>
                                @if($pay->status === 'verified')
                                    <span class="badge bg-soft-success text-success">Verified</span>
                                    <div class="fs--2 text-muted">By: {{ $pay->verifier?->name ?? 'Admin' }}</div>
                                @elseif($pay->status === 'pending_verification')
                                    <span class="badge bg-soft-warning text-warning">Pending Review</span>
                                @else
                                    <span class="badge bg-soft-danger text-danger">Rejected</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($pay->status === 'pending_verification')
                                    <button class="btn btn-success btn-sm py-0 px-2" wire:click="verifyPayment({{ $pay->id }})">
                                        <i class="fas fa-check me-1"></i> Verify
                                    </button>
                                    <button class="btn btn-outline-danger btn-sm py-0 px-2 ms-1" wire:click="rejectPayment({{ $pay->id }})">
                                        <i class="fas fa-times me-1"></i> Reject
                                    </button>
                                @else
                                    <span class="text-muted fs--2">Processed</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-500">No payment records found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3">
                {{ $payments->links() }}
            </div>
        </div>
    </div>
    @endif

    <!-- Record Payment Modal -->
    @if($showPaymentModal && $selectedInvoice)
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.6);">
        <div class="modal-dialog">
            <div class="modal-content shadow-lg border-0">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-money-bill-wave me-2 text-success"></i> Record Subscription Payment
                    </h5>
                    <button type="button" class="btn-close" wire:click="$set('showPaymentModal', false)"></button>
                </div>
                <form wire:submit.prevent="savePayment">
                    <div class="modal-body">
                        <div class="bg-100 p-2 rounded mb-3">
                            <div class="fs--1">Company: <strong class="text-primary">{{ $selectedInvoice->company?->name }}</strong></div>
                            <div class="fs--1">Invoice: <strong>{{ $selectedInvoice->invoice_number }}</strong></div>
                            <div class="fs--1">Total Amount: <strong>PKR {{ number_format($selectedInvoice->total_amount, 2) }}</strong></div>
                            <div class="fs--1">Current Balance: <strong class="text-danger">PKR {{ number_format($selectedInvoice->balance_amount, 2) }}</strong></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fs--1">Payment Amount (PKR) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control form-control-sm @error('paymentAmount') is-invalid @enderror" wire:model="paymentAmount">
                            @error('paymentAmount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label fs--1">Payment Method <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" wire:model="paymentMethod">
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="cheque">Cheque</option>
                                <option value="cash">Cash</option>
                                <option value="online">Online Payment Gateway</option>
                                <option value="credit_card">Credit Card</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fs--1">Transaction / Reference Number</label>
                            <input type="text" class="form-control form-control-sm" wire:model="paymentReference" placeholder="e.g. Bank Deposit Slip # or Transaction ID">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fs--1">Notes</label>
                            <textarea class="form-control form-control-sm" wire:model="paymentNotes" rows="2" placeholder="Optional audit memo"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="$set('showPaymentModal', false)">Cancel</button>
                        <button type="submit" class="btn btn-success btn-sm px-4">
                            <i class="fas fa-check-circle me-1"></i> Record & Clear Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>


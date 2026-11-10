<div>
    @if(session()->has('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fas fa-exclamation-triangle me-1"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Action Bar & Filters -->
    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <div class="row g-2 align-items-center justify-content-between">
                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="fas fa-search text-400"></i></span>
                        <input type="text" class="form-control" placeholder="Search booking #, route, destination..." wire:model.live.debounce.300ms="search">
                    </div>
                </div>
                <div class="col-md-auto d-flex gap-2 align-items-center">
                    <select class="form-select form-select-sm" wire:model.live="statusFilter" style="width: 150px;">
                        <option value="">All Statuses</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="partially_dispatched">Partially Dispatched</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>

                    <button type="button" class="btn btn-primary btn-sm shadow-sm" wire:click="openCreateBookingModal">
                        <i class="fas fa-plus me-1"></i> New Cargo Booking
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bookings Table -->
    <div class="card shadow-sm mb-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped fs--1 mb-0 align-middle">
                    <thead class="bg-200 text-900">
                        <tr>
                            <th>Booking # & Date</th>
                            <th>Customer Account</th>
                            <th>Route (Corridor)</th>
                            <th>Cargo & Volume</th>
                            <th>Net Freight</th>
                            <th>Advance Collected</th>
                            <th>Balance Due</th>
                            <th>Consignments</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($bookings as $bk)
                            <tr>
                                <td>
                                    <div class="fw-bold text-primary">{{ $bk->booking_number }}</div>
                                    <div class="fs--2 text-muted">{{ $bk->booking_date->format('d M Y') }}</div>
                                    @if($bk->quotation)
                                        <div class="fs--2 text-info"><i class="fas fa-quote-left me-1"></i> {{ $bk->quotation->quotation_number }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semi-bold">{{ $bk->customer->name }}</div>
                                    <span class="badge bg-soft-{{ $bk->payment_terms === 'paid' ? 'success' : 'warning text-dark' }} fs--2">
                                        {{ str_replace('_', ' ', strtoupper($bk->payment_terms)) }}
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-semi-bold">
                                        <i class="fas fa-map-marker-alt text-danger me-1"></i> {{ $bk->origin_city }}
                                        <i class="fas fa-arrow-right text-muted mx-1"></i>
                                        <i class="fas fa-map-marker-alt text-success me-1"></i> {{ $bk->destination_city }}
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semi-bold">{{ $bk->total_packages_count }} Packages</div>
                                    <div class="fs--2 text-muted">{{ $bk->total_weight_tons }} Tons</div>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">PKR {{ number_format($bk->net_amount, 2) }}</div>
                                </td>
                                <td>
                                    @if($bk->advance_received > 0)
                                        <span class="badge bg-soft-success text-success fw-bold">
                                            PKR {{ number_format($bk->advance_received, 2) }}
                                        </span>
                                    @else
                                        <span class="text-muted fs--2">No Advance</span>
                                    @endif
                                </td>
                                <td>
                                    @if($bk->balance_amount > 0)
                                        <span class="badge bg-soft-danger text-danger fw-bold">
                                            PKR {{ number_format($bk->balance_amount, 2) }}
                                        </span>
                                    @else
                                        <span class="badge bg-soft-success text-success">Settled</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-soft-primary text-primary fw-bold">
                                        {{ $bk->consignments->count() }} {{ $bk->consignments->count() === 1 ? 'Bilty' : 'Bilties' }}
                                    </span>
                                </td>
                                <td>
                                    @switch($bk->status)
                                        @case('confirmed')
                                            <span class="badge bg-soft-primary text-primary"><i class="fas fa-check-circle me-1"></i> Confirmed</span>
                                            @break
                                        @case('partially_dispatched')
                                            <span class="badge bg-soft-warning text-dark"><i class="fas fa-truck-loading me-1"></i> Dispatched</span>
                                            @break
                                        @case('completed')
                                            <span class="badge bg-soft-success text-success"><i class="fas fa-flag-checkered me-1"></i> Completed</span>
                                            @break
                                        @case('cancelled')
                                            <span class="badge bg-soft-danger text-danger"><i class="fas fa-ban me-1"></i> Cancelled</span>
                                            @break
                                    @endswitch
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-falcon-default" wire:click="viewBooking({{ $bk->id }})" title="View Booking Dossier">
                                            <i class="fas fa-eye text-primary"></i>
                                        </button>
                                        <a href="{{ route('print.booking', $bk->id) }}" target="_blank" class="btn btn-falcon-default" title="Print Booking Confirmation">
                                            <i class="fas fa-print text-secondary"></i>
                                        </a>
                                        <button type="button" class="btn btn-falcon-default" wire:click="openRecordAdvanceModal({{ $bk->id }})" title="Record Customer Advance">
                                            <i class="fas fa-hand-holding-usd text-success"></i>
                                        </button>
                                        @if($bk->canBeCancelled())
                                            <button type="button" class="btn btn-falcon-default" wire:click="openCancelModal({{ $bk->id }})" title="Cancel Booking">
                                                <i class="fas fa-times text-danger"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-4 text-muted">
                                    <i class="fas fa-calendar-check fa-2x mb-2 d-block text-300"></i>
                                    No cargo bookings found matching your criteria.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($bookings->hasPages())
            <div class="card-footer py-2">
                {{ $bookings->links() }}
            </div>
        @endif
    </div>

    <!-- CREATE BOOKING MODAL -->
    @if($showCreateModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);" aria-modal="true" role="dialog">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-light py-2">
                        <h6 class="modal-title text-primary fw-bold">
                            <i class="fas fa-calendar-plus me-1"></i> Create Direct Cargo Booking
                        </h6>
                        <button type="button" class="btn-close" wire:click="$set('showCreateModal', false)"></button>
                    </div>
                    <form wire:submit.prevent="saveBooking">
                        <div class="modal-body p-3">
                            <h6 class="fw-bold fs--1 text-uppercase text-700 border-bottom pb-1 mb-2">1. Client, Corridor & Freight Schedule</h6>
                            <div class="row g-2 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-semi-bold">Commercial Client <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm @error('customer_id') is-invalid @enderror" wire:model.live="customer_id">
                                        <option value="">Select Customer Account</option>
                                        @foreach($customers as $c)
                                            <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->customer_code }})</option>
                                        @endforeach
                                    </select>
                                    @error('customer_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-semi-bold">Contract Agreement (Optional)</label>
                                    <select class="form-select form-select-sm" wire:model.live="customer_contract_id">
                                        <option value="">None (Spot Order)</option>
                                        @foreach($contracts as $cnt)
                                            <option value="{{ $cnt->id }}">{{ $cnt->contract_title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-semi-bold">Payment Terms <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm" wire:model="payment_terms">
                                        <option value="to_pay">To-Pay (Cash on Delivery)</option>
                                        <option value="paid">Paid (Cash at Booking)</option>
                                        <option value="credit">Credit (Billed to Account)</option>
                                        <option value="bill_to_company">Bill to Consignor Company</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs--1 fw-semi-bold">Origin City <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm @error('origin_city') is-invalid @enderror" wire:model="origin_city">
                                    @error('origin_city') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs--1 fw-semi-bold">Destination City <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm @error('destination_city') is-invalid @enderror" wire:model="destination_city">
                                    @error('destination_city') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs--1 fw-semi-bold">Expected Dispatch</label>
                                    <input type="date" class="form-control form-control-sm" wire:model="expected_dispatch_date">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs--1 fw-semi-bold">Expected Delivery</label>
                                    <input type="date" class="form-control form-control-sm" wire:model="expected_delivery_date">
                                </div>
                            </div>

                            <h6 class="fw-bold fs--1 text-uppercase text-700 border-bottom pb-1 mb-2">2. Cargo Specs & Consignee (Receiver)</h6>
                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fs--1 fw-semi-bold">Cargo Description <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm @error('cargo_description') is-invalid @enderror" wire:model="cargo_description" placeholder="e.g. Rice Bags / Industrial Parts">
                                    @error('cargo_description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs--1 fw-semi-bold">Packaging Type</label>
                                    <select class="form-select form-select-sm" wire:model="packaging_type">
                                        <option value="bags">Bags</option>
                                        <option value="cartons">Cartons</option>
                                        <option value="pallets">Pallets</option>
                                        <option value="drums">Drums</option>
                                        <option value="wooden_crates">Wooden Crates</option>
                                        <option value="loose">Loose Cargo</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs--1 fw-semi-bold">Cargo Category</label>
                                    <select class="form-select form-select-sm" wire:model="cargo_category">
                                        <option value="GENERAL_CARGO">General Cargo</option>
                                        <option value="FMCG">FMCG</option>
                                        <option value="PERISHABLE">Perishable</option>
                                        <option value="HAZMAT_CHEMICALS">Hazardous</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs--1 fw-semi-bold">Weight (Tons) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" class="form-control form-control-sm @error('weight_tons') is-invalid @enderror" wire:model="weight_tons">
                                    @error('weight_tons') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs--1 fw-semi-bold">Package Quantity <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control form-control-sm @error('package_count') is-invalid @enderror" wire:model="package_count">
                                    @error('package_count') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs--1 fw-semi-bold">Consignee Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm @error('consignee_name') is-invalid @enderror" wire:model="consignee_name">
                                    @error('consignee_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs--1 fw-semi-bold">Consignee Phone <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm @error('consignee_phone') is-invalid @enderror" wire:model="consignee_phone">
                                    @error('consignee_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <h6 class="fw-bold fs--1 text-uppercase text-700 border-bottom pb-1 mb-2">3. Commercial Financials & Advance Collection</h6>
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <label class="form-label fs--1 fw-semi-bold">Total Freight (PKR) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" class="form-control form-control-sm @error('total_freight') is-invalid @enderror" wire:model="total_freight">
                                    @error('total_freight') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs--1 fw-semi-bold">Additional Surcharges (PKR)</label>
                                    <input type="number" step="0.01" class="form-control form-control-sm" wire:model="additional_charges">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs--1 fw-semi-bold">Advance Collected (PKR)</label>
                                    <input type="number" step="0.01" class="form-control form-control-sm" wire:model="advance_received">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs--1 fw-semi-bold">Advance Payment Method</label>
                                    <select class="form-select form-select-sm" wire:model="advance_payment_method">
                                        <option value="cash">Cash in Hand</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="cheque">Cheque</option>
                                        <option value="jazzcash">JazzCash</option>
                                        <option value="easypaisa">EasyPaisa</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light py-2">
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="$set('showCreateModal', false)">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-check-circle me-1"></i> Register Booking & Issue Bilty
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- RECORD ADVANCE MODAL -->
    @if($showAdvanceModal && $advancingBooking)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);" aria-modal="true" role="dialog">
            <div class="modal-dialog modal-md">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-success text-white py-2">
                        <h6 class="modal-title fw-bold">
                            <i class="fas fa-hand-holding-usd me-1"></i> Collect Customer Advance Payment
                        </h6>
                        <button type="button" class="btn-close btn-close-white" wire:click="$set('showAdvanceModal', false)"></button>
                    </div>
                    <form wire:submit.prevent="recordAdvance">
                        <div class="modal-body p-3">
                            <div class="alert alert-soft-success py-2 mb-3 fs--1">
                                <div>Booking: <strong>{{ $advancingBooking->booking_number }}</strong></div>
                                <div>Client: <strong>{{ $advancingBooking->customer->name }}</strong></div>
                                <div class="mt-1">Net Freight: <strong>PKR {{ number_format($advancingBooking->net_amount, 2) }}</strong> | Outstanding Balance: <strong class="text-danger">PKR {{ number_format($advancingBooking->balance_amount, 2) }}</strong></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fs--1 fw-semi-bold">Advance Amount (PKR) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control form-control-sm @error('new_advance_amount') is-invalid @enderror" wire:model="new_advance_amount">
                                @error('new_advance_amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fs--1 fw-semi-bold">Payment Method <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm" wire:model="new_advance_method">
                                        <option value="cash">Petty Cash</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="cheque">Cheque</option>
                                        <option value="jazzcash">JazzCash</option>
                                        <option value="easypaisa">EasyPaisa</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fs--1 fw-semi-bold">Reference / Txn #</label>
                                    <input type="text" class="form-control form-control-sm" wire:model="new_advance_ref" placeholder="e.g. TXN-998811">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light py-2">
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="$set('showAdvanceModal', false)">Cancel</button>
                            <button type="submit" class="btn btn-success btn-sm text-white">Record Advance Receipt</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- CANCEL BOOKING MODAL -->
    @if($showCancelModal && $cancellingBooking)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);" aria-modal="true" role="dialog">
            <div class="modal-dialog modal-md">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-danger text-white py-2">
                        <h6 class="modal-title fw-bold">
                            <i class="fas fa-exclamation-triangle me-1"></i> Cancel Booking Order
                        </h6>
                        <button type="button" class="btn-close btn-close-white" wire:click="$set('showCancelModal', false)"></button>
                    </div>
                    <form wire:submit.prevent="cancelBooking">
                        <div class="modal-body p-3">
                            <p class="fs--1 text-danger">Are you sure you want to cancel Booking <strong>{{ $cancellingBooking->booking_number }}</strong>? This action will cancel all associated un-dispatched bilties.</p>
                            <div class="mb-3">
                                <label class="form-label fs--1 fw-semi-bold">Cancellation Reason <span class="text-danger">*</span></label>
                                <textarea class="form-control form-control-sm @error('cancellation_reason') is-invalid @enderror" rows="2" wire:model="cancellation_reason"></textarea>
                                @error('cancellation_reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="modal-footer bg-light py-2">
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="$set('showCancelModal', false)">Keep Active</button>
                            <button type="submit" class="btn btn-danger btn-sm">Confirm Cancellation</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- BOOKING DOSSIER MODAL -->
    @if($showDetailModal && $viewingBooking)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);" aria-modal="true" role="dialog">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-light py-2">
                        <h6 class="modal-title text-primary fw-bold">
                            <i class="fas fa-calendar-check me-1"></i> Booking Dossier: {{ $viewingBooking->booking_number }}
                        </h6>
                        <button type="button" class="btn-close" wire:click="$set('showDetailModal', false)"></button>
                    </div>
                    <div class="modal-body p-3">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <div class="card h-100 bg-light border-0">
                                    <div class="card-body p-3">
                                        <h6 class="fw-bold fs--1 text-uppercase text-700 mb-2">Booking Identification</h6>
                                        <div class="fs--1 mb-1"><strong>Client:</strong> {{ $viewingBooking->customer->name }}</div>
                                        <div class="fs--1 mb-1"><strong>Corridor:</strong> {{ $viewingBooking->origin_city }} &rarr; {{ $viewingBooking->destination_city }}</div>
                                        <div class="fs--1 mb-1"><strong>Booking Date:</strong> {{ $viewingBooking->booking_date->format('d M Y') }}</div>
                                        <div class="fs--1 mb-1"><strong>Payment Terms:</strong> {{ str_replace('_', ' ', strtoupper($viewingBooking->payment_terms)) }}</div>
                                        <div class="fs--1"><strong>Status:</strong> <span class="badge bg-soft-primary text-primary">{{ ucfirst($viewingBooking->status) }}</span></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100 bg-light border-0">
                                    <div class="card-body p-3">
                                        <h6 class="fw-bold fs--1 text-uppercase text-700 mb-2">Financial Accounting</h6>
                                        <div class="fs--1 mb-1"><strong>Total Net Freight:</strong> PKR {{ number_format($viewingBooking->net_amount, 2) }}</div>
                                        <div class="fs--1 mb-1"><strong>Advance Collected:</strong> <span class="text-success fw-bold">PKR {{ number_format($viewingBooking->advance_received, 2) }}</span></div>
                                        <div class="fs--1 mb-1"><strong>Advance Txn Ref:</strong> {{ $viewingBooking->advance_receipt_reference ?: 'N/A' }}</div>
                                        <div class="fs--1"><strong>Outstanding Due:</strong> <span class="text-danger fw-bold">PKR {{ number_format($viewingBooking->balance_amount, 2) }}</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Associated Consignments (Bilties) -->
                        <h6 class="fw-bold fs--1 text-uppercase text-700 mb-2">Associated Consignment Bilties</h6>
                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-bordered fs--2 mb-0">
                                <thead class="bg-200">
                                    <tr>
                                        <th>Bilty #</th>
                                        <th>Consignee</th>
                                        <th>Cargo & Packaging</th>
                                        <th>Total Packages</th>
                                        <th>Dispatched</th>
                                        <th>Remaining</th>
                                        <th>Freight Charges</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($viewingBooking->consignments as $cn)
                                        <tr>
                                            <td class="fw-bold text-primary">{{ $cn->consignment_number }}</td>
                                            <td>{{ $cn->consignee_name }} ({{ $cn->consignee_city }})</td>
                                            <td>{{ $cn->cargo_description }} ({{ $cn->packaging_type }})</td>
                                            <td class="text-center fw-semi-bold">{{ $cn->total_quantity }}</td>
                                            <td class="text-center text-success fw-bold">{{ $cn->dispatched_quantity }}</td>
                                            <td class="text-center text-warning fw-bold">{{ $cn->remaining_quantity }}</td>
                                            <td class="text-end">PKR {{ number_format($cn->total_charges, 2) }}</td>
                                            <td class="text-center">
                                                <span class="badge bg-soft-info text-info">{{ ucfirst(str_replace('_', ' ', $cn->status)) }}</span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center py-2 text-muted">No consignments linked.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <a href="{{ route('print.booking', $viewingBooking->id) }}" target="_blank" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-print me-1"></i> Print Confirmation (A4)
                        </a>
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="$set('showDetailModal', false)">Close</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>


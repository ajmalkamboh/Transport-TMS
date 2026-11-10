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
                        <input type="text" class="form-control" placeholder="Search bilty #, consignee, city, cargo..." wire:model.live.debounce.300ms="search">
                    </div>
                </div>
                <div class="col-md-auto d-flex gap-2 align-items-center">
                    <select class="form-select form-select-sm" wire:model.live="statusFilter" style="width: 160px;">
                        <option value="">All Statuses</option>
                        <option value="booked">Booked (At Origin)</option>
                        <option value="dispatched_in_transit">Dispatched / Transit</option>
                        <option value="at_destination_hub">At Destination Hub</option>
                        <option value="out_for_delivery">Out for Delivery</option>
                        <option value="delivered">Delivered</option>
                        <option value="cancelled">Cancelled</option>
                    </select>

                    <button type="button" class="btn btn-primary btn-sm shadow-sm" wire:click="openCreateModal">
                        <i class="fas fa-plus me-1"></i> Issue Consignment (Bilty)
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Consignments Table -->
    <div class="card shadow-sm mb-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped fs--1 mb-0 align-middle">
                    <thead class="bg-200 text-900">
                        <tr>
                            <th>Bilty # & Date</th>
                            <th>Consignor (Sender)</th>
                            <th>Consignee (Receiver)</th>
                            <th>Cargo & Packaging</th>
                            <th>Split Quantities (Total / Disp / Rem)</th>
                            <th>Charges & Mode</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($consignments as $cn)
                            <tr>
                                <td>
                                    <div class="fw-bold text-primary">{{ $cn->consignment_number }}</div>
                                    <div class="fs--2 text-muted">{{ $cn->consignment_date->format('d M Y') }}</div>
                                    <div class="fs--2 text-info">
                                        <i class="fas fa-calendar-check me-1"></i> {{ $cn->booking->booking_number }}
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semi-bold">{{ $cn->customer->name }}</div>
                                    <div class="fs--2 text-muted">{{ $cn->pickup_point }}</div>
                                </td>
                                <td>
                                    <div class="fw-semi-bold">{{ $cn->consignee_name }}</div>
                                    <div class="fs--2 text-muted"><i class="fas fa-map-marker-alt text-success me-1"></i> {{ $cn->consignee_city }}</div>
                                    <div class="fs--2 text-muted"><i class="fas fa-phone-alt me-1"></i> {{ $cn->consignee_phone }}</div>
                                </td>
                                <td>
                                    <div class="fw-semi-bold">{{ $cn->cargo_description }}</div>
                                    <div class="fs--2 text-muted">
                                        <span class="badge bg-soft-secondary text-dark">{{ ucfirst($cn->packaging_type) }}</span>
                                        {{ number_format($cn->chargeable_weight_kg / 1000, 2) }} Tons
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-1">
                                        <span class="badge bg-soft-primary text-primary" title="Total Booked">{{ $cn->total_quantity }} Total</span>
                                        <span class="badge bg-soft-success text-success" title="Dispatched">{{ $cn->dispatched_quantity }} Disp</span>
                                        <span class="badge bg-soft-{{ $cn->remaining_quantity > 0 ? 'warning text-dark' : 'secondary' }}" title="Remaining">
                                            {{ $cn->remaining_quantity }} Rem
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">PKR {{ number_format($cn->total_charges, 2) }}</div>
                                    <span class="badge bg-soft-{{ $cn->payment_mode === 'paid' ? 'success' : 'warning text-dark' }} fs--2">
                                        {{ strtoupper(str_replace('_', ' ', $cn->payment_mode)) }}
                                    </span>
                                </td>
                                <td>
                                    @switch($cn->status)
                                        @case('booked')
                                            <span class="badge bg-soft-primary text-primary">Booked</span>
                                            @break
                                        @case('dispatched_in_transit')
                                            <span class="badge bg-soft-warning text-dark"><i class="fas fa-truck-moving me-1"></i> In Transit</span>
                                            @break
                                        @case('at_destination_hub')
                                            <span class="badge bg-soft-info text-info"><i class="fas fa-warehouse me-1"></i> At Dest Hub</span>
                                            @break
                                        @case('delivered')
                                            <span class="badge bg-soft-success text-success"><i class="fas fa-check-double me-1"></i> Delivered</span>
                                            @break
                                        @case('cancelled')
                                            <span class="badge bg-soft-danger text-danger">Cancelled</span>
                                            @break
                                        @default
                                            <span class="badge bg-soft-secondary text-secondary">{{ ucfirst(str_replace('_', ' ', $cn->status)) }}</span>
                                    @endswitch
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-falcon-default" wire:click="viewConsignment({{ $cn->id }})" title="View Bilty Details">
                                            <i class="fas fa-eye text-primary"></i>
                                        </button>
                                        <a href="{{ route('print.consignment', $cn->id) }}" target="_blank" class="btn btn-falcon-default" title="Print Bilty (LR) A4">
                                            <i class="fas fa-print text-secondary"></i>
                                        </a>
                                        <a href="{{ route('print.cargo-receipt', $cn->id) }}" target="_blank" class="btn btn-falcon-default" title="Print Cargo Receipt">
                                            <i class="fas fa-receipt text-info"></i>
                                        </a>
                                        @if($cn->remaining_quantity > 0 && $cn->status !== 'cancelled')
                                            <button type="button" class="btn btn-falcon-default" wire:click="openSplitModal({{ $cn->id }})" title="Authorized Split Dispatch">
                                                <i class="fas fa-truck-loading text-warning"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="fas fa-receipt fa-2x mb-2 d-block text-300"></i>
                                    No consignment notes (bilties) found matching your criteria.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($consignments->hasPages())
            <div class="card-footer py-2">
                {{ $consignments->links() }}
            </div>
        @endif
    </div>

    <!-- CREATE CONSIGNMENT MODAL -->
    @if($showCreateModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);" aria-modal="true" role="dialog">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-light py-2">
                        <h6 class="modal-title text-primary fw-bold">
                            <i class="fas fa-receipt me-1"></i> Issue Consignment Note (Bilty / Lorry Receipt)
                        </h6>
                        <button type="button" class="btn-close" wire:click="$set('showCreateModal', false)"></button>
                    </div>
                    <form wire:submit.prevent="saveConsignment">
                        <div class="modal-body p-3">
                            <div class="mb-3">
                                <label class="form-label fs--1 fw-semi-bold">Parent Cargo Booking <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm @error('booking_id') is-invalid @enderror" wire:model.live="booking_id">
                                    <option value="">Select Confirmed Booking Order</option>
                                    @foreach($bookings as $b)
                                        <option value="{{ $b->id }}">{{ $b->booking_number }} - {{ $b->customer->name }} ({{ $b->origin_city }} &rarr; {{ $b->destination_city }})</option>
                                    @endforeach
                                </select>
                                @error('booking_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <h6 class="fw-bold fs--1 text-uppercase text-700 border-bottom pb-1 mb-2">1. Consignee (Receiver) Details</h6>
                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fs--1 fw-semi-bold">Consignee Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm @error('consignee_name') is-invalid @enderror" wire:model="consignee_name" placeholder="Receiver full name or firm">
                                    @error('consignee_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs--1 fw-semi-bold">Consignee Phone <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm @error('consignee_phone') is-invalid @enderror" wire:model="consignee_phone" placeholder="0300-1234567">
                                    @error('consignee_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs--1 fw-semi-bold">CNIC / NTN</label>
                                    <input type="text" class="form-control form-control-sm" wire:model="consignee_cnic_or_ntn" placeholder="37405-1234567-1">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label fs--1 fw-semi-bold">Consignee Delivery Address</label>
                                    <input type="text" class="form-control form-control-sm" wire:model="consignee_address" placeholder="Premises / Street address">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-semi-bold">Destination City <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm @error('consignee_city') is-invalid @enderror" wire:model="consignee_city">
                                    @error('consignee_city') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <h6 class="fw-bold fs--1 text-uppercase text-700 border-bottom pb-1 mb-2">2. Cargo Specs & Package Quantity</h6>
                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fs--1 fw-semi-bold">Cargo Description <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm @error('cargo_description') is-invalid @enderror" wire:model="cargo_description">
                                    @error('cargo_description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs--1 fw-semi-bold">Packaging Type <span class="text-danger">*</span></label>
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
                                    <label class="form-label fs--1 fw-semi-bold">Total Quantity (Units) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control form-control-sm @error('total_quantity') is-invalid @enderror" wire:model="total_quantity">
                                    @error('total_quantity') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-semi-bold">Gross Weight (KG)</label>
                                    <input type="number" step="0.01" class="form-control form-control-sm" wire:model="gross_weight_kg">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-semi-bold">Chargeable Weight (KG)</label>
                                    <input type="number" step="0.01" class="form-control form-control-sm" wire:model="chargeable_weight_kg">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-semi-bold">Declared Cargo Value (PKR)</label>
                                    <input type="number" step="0.01" class="form-control form-control-sm" wire:model="declared_value">
                                </div>
                            </div>

                            <h6 class="fw-bold fs--1 text-uppercase text-700 border-bottom pb-1 mb-2">3. Freight & Charges Breakdown</h6>
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <label class="form-label fs--1 fw-semi-bold">Freight Charge (PKR)</label>
                                    <input type="number" step="0.01" class="form-control form-control-sm" wire:model="freight_charge">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs--1 fw-semi-bold">Labour & Handling (PKR)</label>
                                    <input type="number" step="0.01" class="form-control form-control-sm" wire:model="handling_charge">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs--1 fw-semi-bold">Transit Insurance (PKR)</label>
                                    <input type="number" step="0.01" class="form-control form-control-sm" wire:model="insurance_charge">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs--1 fw-semi-bold">Payment Mode</label>
                                    <select class="form-select form-select-sm" wire:model="payment_mode">
                                        <option value="to_pay">To-Pay (COD)</option>
                                        <option value="paid">Paid</option>
                                        <option value="credit">Credit</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light py-2">
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="$set('showCreateModal', false)">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-sm">Issue Bilty (Consignment Note)</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- SPLIT DISPATCH MODAL -->
    @if($showSplitModal && $splittingConsignment)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);" aria-modal="true" role="dialog">
            <div class="modal-dialog modal-md">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-warning text-dark py-2">
                        <h6 class="modal-title fw-bold">
                            <i class="fas fa-truck-loading me-1"></i> Authorized Split Dispatch
                        </h6>
                        <button type="button" class="btn-close" wire:click="$set('showSplitModal', false)"></button>
                    </div>
                    <form wire:submit.prevent="splitAndDispatch">
                        <div class="modal-body p-3">
                            <div class="alert alert-soft-warning py-2 mb-3 fs--1">
                                <div>Bilty #: <strong>{{ $splittingConsignment->consignment_number }}</strong></div>
                                <div>Cargo: <strong>{{ $splittingConsignment->cargo_description }}</strong></div>
                                <div class="mt-1">
                                    Total Booked: <strong>{{ $splittingConsignment->total_quantity }}</strong> |
                                    Already Dispatched: <strong class="text-success">{{ $splittingConsignment->dispatched_quantity }}</strong> |
                                    Remaining Available: <strong class="text-danger">{{ $splittingConsignment->remaining_quantity }}</strong>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fs--1 fw-semi-bold">Dispatch Quantity (Packages) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control form-control-sm @error('dispatch_quantity') is-invalid @enderror" wire:model="dispatch_quantity" min="1" max="{{ $splittingConsignment->remaining_quantity }}">
                                @error('dispatch_quantity') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="fs--2 text-muted mt-1">Rule: Quantity must not exceed remaining {{ $splittingConsignment->remaining_quantity }} packages.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fs--1 fw-semi-bold">Assigned Vehicle / Driver Ref</label>
                                <input type="text" class="form-control form-control-sm" wire:model="dispatch_vehicle_reference" placeholder="e.g. LES-18-9922 / Driver Sultan">
                            </div>

                            <div class="mb-2">
                                <label class="form-label fs--1 fw-semi-bold">Dispatch Manifest Notes</label>
                                <textarea class="form-control form-control-sm" rows="2" wire:model="dispatch_notes" placeholder="Trip sheet or dispatch instructions..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer bg-light py-2">
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="$set('showSplitModal', false)">Cancel</button>
                            <button type="submit" class="btn btn-warning btn-sm fw-bold">Confirm Split Dispatch</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- CONSIGNMENT DOSSIER VIEW MODAL -->
    @if($showDetailModal && $viewingConsignment)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);" aria-modal="true" role="dialog">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-light py-2">
                        <h6 class="modal-title text-primary fw-bold">
                            <i class="fas fa-receipt me-1"></i> Consignment Bilty: {{ $viewingConsignment->consignment_number }}
                        </h6>
                        <button type="button" class="btn-close" wire:click="$set('showDetailModal', false)"></button>
                    </div>
                    <div class="modal-body p-3">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <div class="card h-100 bg-light border-0">
                                    <div class="card-body p-3">
                                        <h6 class="fw-bold fs--1 text-uppercase text-700 mb-2">Consignor & Booking</h6>
                                        <div class="fs--1 mb-1"><strong>Shipper:</strong> {{ $viewingConsignment->customer->name }}</div>
                                        <div class="fs--1 mb-1"><strong>Booking Ref:</strong> {{ $viewingConsignment->booking->booking_number }}</div>
                                        <div class="fs--1 mb-1"><strong>Date:</strong> {{ $viewingConsignment->consignment_date->format('d M Y') }}</div>
                                        <div class="fs--1"><strong>Pickup Point:</strong> {{ $viewingConsignment->pickup_point }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100 bg-light border-0">
                                    <div class="card-body p-3">
                                        <h6 class="fw-bold fs--1 text-uppercase text-700 mb-2">Consignee & Delivery Destination</h6>
                                        <div class="fs--1 mb-1"><strong>Receiver:</strong> {{ $viewingConsignment->consignee_name }}</div>
                                        <div class="fs--1 mb-1"><strong>Phone:</strong> {{ $viewingConsignment->consignee_phone }}</div>
                                        <div class="fs--1 mb-1"><strong>Destination:</strong> {{ $viewingConsignment->delivery_point }} ({{ $viewingConsignment->consignee_city }})</div>
                                        <div class="fs--1"><strong>Address:</strong> {{ $viewingConsignment->consignee_address ?: 'Hub Delivery' }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Split Status Summary -->
                        <div class="card bg-soft-primary border-0 mb-3">
                            <div class="card-body p-3">
                                <div class="row g-2 text-center">
                                    <div class="col-md-3">
                                        <div class="fs--2 text-muted">Total Booked Packages</div>
                                        <div class="fw-bold fs-0 text-primary">{{ $viewingConsignment->total_quantity }}</div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="fs--2 text-muted">Dispatched Units</div>
                                        <div class="fw-bold fs-0 text-success">{{ $viewingConsignment->dispatched_quantity }}</div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="fs--2 text-muted">Delivered Units</div>
                                        <div class="fw-bold fs-0 text-info">{{ $viewingConsignment->delivered_quantity }}</div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="fs--2 text-muted">Remaining Undelivered</div>
                                        <div class="fw-bold fs-0 text-warning">{{ $viewingConsignment->remaining_quantity }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card bg-light border-0">
                            <div class="card-body p-3">
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <div class="fs--2 text-muted">Cargo Description</div>
                                        <div class="fw-semi-bold">{{ $viewingConsignment->cargo_description }} ({{ $viewingConsignment->packaging_type }})</div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="fs--2 text-muted">Chargeable Weight</div>
                                        <div class="fw-semi-bold">{{ number_format($viewingConsignment->chargeable_weight_kg, 2) }} KG</div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="fs--2 text-muted">Total Charges / Payment Mode</div>
                                        <div class="fw-bold text-dark">PKR {{ number_format($viewingConsignment->total_charges, 2) }} ({{ strtoupper($viewingConsignment->payment_mode) }})</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <a href="{{ route('print.consignment', $viewingConsignment->id) }}" target="_blank" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-print me-1"></i> Print Bilty (LR)
                        </a>
                        <a href="{{ route('print.cargo-receipt', $viewingConsignment->id) }}" target="_blank" class="btn btn-outline-info btn-sm">
                            <i class="fas fa-receipt me-1"></i> Print Cargo Receipt
                        </a>
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="$set('showDetailModal', false)">Close</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

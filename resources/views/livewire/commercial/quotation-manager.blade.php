<div>
    @if(session()->has('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
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
                        <input type="text" class="form-control" placeholder="Search quotation #, route, cargo..." wire:model.live.debounce.300ms="search">
                    </div>
                </div>
                <div class="col-md-auto d-flex gap-2 align-items-center">
                    <select class="form-select form-select-sm" wire:model.live="statusFilter" style="width: 140px;">
                        <option value="">All Statuses</option>
                        <option value="draft">Draft</option>
                        <option value="sent">Sent</option>
                        <option value="approved">Approved</option>
                        <option value="converted">Converted</option>
                        <option value="rejected">Rejected</option>
                        <option value="expired">Expired</option>
                    </select>

                    <button type="button" class="btn btn-primary btn-sm shadow-sm" wire:click="openCreateModal">
                        <i class="fas fa-plus me-1"></i> Generate Quotation
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Quotations Table -->
    <div class="card shadow-sm mb-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped fs--1 mb-0 align-middle">
                    <thead class="bg-200 text-900">
                        <tr>
                            <th>Quotation # & Date</th>
                            <th>Customer Account</th>
                            <th>Route (Corridor)</th>
                            <th>Cargo & Vehicle</th>
                            <th>Rate Model</th>
                            <th>Net Amount</th>
                            <th>Validity</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($quotations as $qt)
                            <tr>
                                <td>
                                    <div class="fw-bold text-primary">{{ $qt->quotation_number }}</div>
                                    <div class="fs--2 text-muted">{{ $qt->quotation_date->format('d M Y') }}</div>
                                </td>
                                <td>
                                    <div class="fw-semi-bold">{{ $qt->customer->name }}</div>
                                    @if($qt->customer->company_name)
                                        <div class="fs--2 text-muted">{{ $qt->customer->company_name }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semi-bold">
                                        <i class="fas fa-map-marker-alt text-danger me-1"></i> {{ $qt->origin_city }}
                                        <i class="fas fa-arrow-right text-muted mx-1"></i>
                                        <i class="fas fa-map-marker-alt text-success me-1"></i> {{ $qt->destination_city }}
                                    </div>
                                    @if($qt->distance_km)
                                        <div class="fs--2 text-muted">{{ number_format($qt->distance_km) }} km</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semi-bold">{{ $qt->cargo_description }}</div>
                                    <div class="fs--2 text-muted">
                                        {{ $qt->weight_tons }} Tons ({{ $qt->package_count }} pkgs) | {{ $qt->required_vehicle_type }}
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-soft-info text-dark">
                                        {{ ucfirst(str_replace('_', ' ', $qt->rate_type)) }}
                                    </span>
                                    <div class="fs--2 text-muted mt-1">Base: PKR {{ number_format($qt->base_rate, 2) }}</div>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark fs-0">PKR {{ number_format($qt->net_total_amount, 2) }}</div>
                                    @if($qt->tax_amount > 0)
                                        <div class="fs--2 text-muted">Incl. Tax PKR {{ number_format($qt->tax_amount, 2) }}</div>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $valid = \Carbon\Carbon::parse($qt->valid_until);
                                        $isPast = $valid->isPast() && $qt->status !== 'converted';
                                    @endphp
                                    @if($isPast)
                                        <span class="text-danger fw-bold fs--2"><i class="fas fa-clock me-1"></i> Expired ({{ $valid->format('d M') }})</span>
                                    @else
                                        <span class="text-muted fs--2">{{ $valid->format('d M Y') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @switch($qt->status)
                                        @case('draft')
                                            <span class="badge bg-soft-secondary text-secondary">Draft</span>
                                            @break
                                        @case('sent')
                                            <span class="badge bg-soft-primary text-primary">Sent</span>
                                            @break
                                        @case('approved')
                                            <span class="badge bg-soft-success text-success"><i class="fas fa-check-circle me-1"></i> Approved</span>
                                            @break
                                        @case('converted')
                                            <span class="badge bg-soft-info text-info"><i class="fas fa-exchange-alt me-1"></i> Converted</span>
                                            @if($qt->convertedBooking)
                                                <div class="fs--2 text-muted">{{ $qt->convertedBooking->booking_number }}</div>
                                            @endif
                                            @break
                                        @case('rejected')
                                            <span class="badge bg-soft-danger text-danger">Rejected</span>
                                            @break
                                        @case('expired')
                                            <span class="badge bg-soft-warning text-dark">Expired</span>
                                            @break
                                    @endswitch
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-falcon-default" wire:click="viewQuotation({{ $qt->id }})" title="View Details">
                                            <i class="fas fa-eye text-primary"></i>
                                        </button>
                                        <a href="{{ route('print.quotation', $qt->id) }}" target="_blank" class="btn btn-falcon-default" title="Print Quotation">
                                            <i class="fas fa-print text-secondary"></i>
                                        </a>
                                        @if($qt->status === 'draft' || $qt->status === 'sent')
                                            <button type="button" class="btn btn-falcon-default" wire:click="approveQuotation({{ $qt->id }})" title="Approve Quotation">
                                                <i class="fas fa-check text-success"></i>
                                            </button>
                                        @endif
                                        @if($qt->isConvertible())
                                            <button type="button" class="btn btn-falcon-default" wire:click="convertToBooking({{ $qt->id }})" title="1-Click Convert to Confirmed Booking">
                                                <i class="fas fa-calendar-plus text-warning"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">
                                    <i class="fas fa-file-invoice fa-2x mb-2 d-block text-300"></i>
                                    No freight quotations found matching your criteria.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($quotations->hasPages())
            <div class="card-footer py-2">
                {{ $quotations->links() }}
            </div>
        @endif
    </div>

    <!-- CREATE QUOTATION MODAL -->
    @if($showModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);" aria-modal="true" role="dialog">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-light py-2">
                        <h6 class="modal-title text-primary fw-bold">
                            <i class="fas fa-file-invoice me-1"></i> Generate Freight Quotation
                        </h6>
                        <button type="button" class="btn-close" wire:click="$set('showModal', false)"></button>
                    </div>
                    <form wire:submit.prevent="saveQuotation">
                        <div class="modal-body p-3">
                            <div class="row g-3">
                                <div class="col-lg-8">
                                    <h6 class="fw-bold fs--1 text-uppercase text-700 border-bottom pb-1 mb-2">1. Client, Route & Cargo Identification</h6>
                                    <div class="row g-2 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label fs--1 fw-semi-bold">Customer Account <span class="text-danger">*</span></label>
                                            <select class="form-select form-select-sm @error('customer_id') is-invalid @enderror" wire:model.live="customer_id">
                                                <option value="">Select Commercial Client</option>
                                                @foreach($customers as $c)
                                                    <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->customer_code }})</option>
                                                @endforeach
                                            </select>
                                            @error('customer_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fs--1 fw-semi-bold">Contract Tariff Agreement (Optional)</label>
                                            <select class="form-select form-select-sm" wire:model.live="customer_contract_id">
                                                <option value="">None (Standard / Spot Tariff)</option>
                                                @foreach($contracts as $cnt)
                                                    <option value="{{ $cnt->id }}">{{ $cnt->contract_title }} ({{ $cnt->contract_number }})</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fs--1 fw-semi-bold">Origin City / Hub <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-sm @error('origin_city') is-invalid @enderror" wire:model.live="origin_city" placeholder="e.g. Karachi">
                                            @error('origin_city') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fs--1 fw-semi-bold">Destination City / Hub <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-sm @error('destination_city') is-invalid @enderror" wire:model.live="destination_city" placeholder="e.g. Lahore">
                                            @error('destination_city') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fs--1 fw-semi-bold">Pickup Address</label>
                                            <input type="text" class="form-control form-control-sm" wire:model="origin_address" placeholder="Port Qasim / Warehouses">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fs--1 fw-semi-bold">Delivery Address</label>
                                            <input type="text" class="form-control form-control-sm" wire:model="destination_address" placeholder="Industrial Estate / Consignee Hub">
                                        </div>
                                    </div>

                                    <h6 class="fw-bold fs--1 text-uppercase text-700 border-bottom pb-1 mb-2">2. Cargo Specs & Vehicle Requirements</h6>
                                    <div class="row g-2 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label fs--1 fw-semi-bold">Cargo Description <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-sm @error('cargo_description') is-invalid @enderror" wire:model="cargo_description" placeholder="e.g. Chemical Drums in Pallets / FMCG Goods">
                                            @error('cargo_description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fs--1 fw-semi-bold">Cargo Category</label>
                                            <select class="form-select form-select-sm" wire:model="cargo_category">
                                                <option value="GENERAL_CARGO">General Cargo</option>
                                                <option value="FMCG">FMCG</option>
                                                <option value="PERISHABLE">Perishable Goods</option>
                                                <option value="HAZMAT_CHEMICALS">Hazardous / Chemicals</option>
                                                <option value="HEAVY_EQUIPMENT">Heavy Equipment</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fs--1 fw-semi-bold">Required Vehicle</label>
                                            <select class="form-select form-select-sm" wire:model="required_vehicle_type">
                                                <option value="TRAILER_22W">22-Wheeler Trailer</option>
                                                <option value="TRUCK_10W">10-Wheeler Truck</option>
                                                <option value="TRUCK_6W">6-Wheeler Bedford</option>
                                                <option value="CONTAINER_40FT">40ft Container HQ</option>
                                                <option value="CONTAINER_20FT">20ft Container</option>
                                                <option value="SHEHZORE">Shehzore / LCV</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fs--1 fw-semi-bold">Weight (Tons) <span class="text-danger">*</span></label>
                                            <input type="number" step="0.01" class="form-control form-control-sm @error('weight_tons') is-invalid @enderror" wire:model.live="weight_tons">
                                            @error('weight_tons') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fs--1 fw-semi-bold">Package Count <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control form-control-sm @error('package_count') is-invalid @enderror" wire:model.live="package_count">
                                            @error('package_count') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fs--1 fw-semi-bold">Volume (CBM)</label>
                                            <input type="number" step="0.01" class="form-control form-control-sm" wire:model="volume_cbm">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fs--1 fw-semi-bold">Estimated Distance (KM)</label>
                                            <input type="number" step="0.01" class="form-control form-control-sm" wire:model.live="distance_km">
                                        </div>
                                    </div>

                                    <h6 class="fw-bold fs--1 text-uppercase text-700 border-bottom pb-1 mb-2">3. Freight Pricing Engine & Surcharges</h6>
                                    <div class="row g-2 mb-3">
                                        <div class="col-md-4">
                                            <label class="form-label fs--1 fw-semi-bold">Rate Calculation Method <span class="text-danger">*</span></label>
                                            <select class="form-select form-select-sm" wire:model.live="rate_type">
                                                <option value="fixed">Fixed Lump-Sum Freight</option>
                                                <option value="route_based">Route-Based Corridor Tariff</option>
                                                <option value="per_ton">Per Ton Freight Rate</option>
                                                <option value="per_km">Per Kilometre Freight Rate</option>
                                                <option value="per_quantity">Per Package / Bag Tariff</option>
                                                <option value="per_vehicle">Per Vehicle Flat Rate</option>
                                                <option value="negotiated">Negotiated Spot Freight</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fs--1 fw-semi-bold">Base Tariff Rate (PKR) <span class="text-danger">*</span></label>
                                            <input type="number" step="0.01" class="form-control form-control-sm @error('base_rate') is-invalid @enderror" wire:model.live="base_rate">
                                            @error('base_rate') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fs--1 fw-semi-bold">Quotation Validity Until <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control form-control-sm @error('valid_until') is-invalid @enderror" wire:model="valid_until">
                                            @error('valid_until') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fs--1 fw-semi-bold">Loading Charges (PKR)</label>
                                            <input type="number" step="0.01" class="form-control form-control-sm" wire:model.live="loading_charges">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fs--1 fw-semi-bold">Unloading Charges (PKR)</label>
                                            <input type="number" step="0.01" class="form-control form-control-sm" wire:model.live="unloading_charges">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fs--1 fw-semi-bold">Toll Taxes (PKR)</label>
                                            <input type="number" step="0.01" class="form-control form-control-sm" wire:model.live="toll_charges">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fs--1 fw-semi-bold">Detention / Day (PKR)</label>
                                            <input type="number" step="0.01" class="form-control form-control-sm" wire:model.live="detention_charges">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fs--1 fw-semi-bold">Discount Percentage (%)</label>
                                            <input type="number" step="0.01" class="form-control form-control-sm" wire:model.live="discount_percentage">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fs--1 fw-semi-bold">Sales Tax (%)</label>
                                            <input type="number" step="0.01" class="form-control form-control-sm" wire:model.live="tax_percentage">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fs--1 fw-semi-bold">Quotation Date</label>
                                            <input type="date" class="form-control form-control-sm" wire:model="quotation_date">
                                        </div>
                                    </div>
                                </div>

                                <!-- Real-Time Calculation Preview Sidebar -->
                                <div class="col-lg-4">
                                    <div class="card bg-soft-primary border-primary border h-100 shadow-sm">
                                        <div class="card-header bg-primary text-white py-2">
                                            <h6 class="mb-0 text-white fw-bold"><i class="fas fa-calculator me-1"></i> Freight Calculation Breakdown</h6>
                                        </div>
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between mb-2 fs--1">
                                                <span>Base Freight ({{ ucfirst(str_replace('_', ' ', $previewCalc['rate_type'])) }}):</span>
                                                <strong class="text-dark">PKR {{ number_format($previewCalc['freight_amount'], 2) }}</strong>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2 fs--1">
                                                <span>Loading & Labour:</span>
                                                <span>PKR {{ number_format($previewCalc['loading_charges'], 2) }}</span>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2 fs--1">
                                                <span>Unloading & Labour:</span>
                                                <span>PKR {{ number_format($previewCalc['unloading_charges'], 2) }}</span>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2 fs--1">
                                                <span>Toll Taxes & Highway:</span>
                                                <span>PKR {{ number_format($previewCalc['toll_charges'], 2) }}</span>
                                            </div>
                                            <hr class="my-2">
                                            <div class="d-flex justify-content-between mb-2 fs--1">
                                                <span>Subtotal:</span>
                                                <strong>PKR {{ number_format($previewCalc['subtotal'], 2) }}</strong>
                                            </div>
                                            @if($previewCalc['discount_amount'] > 0)
                                                <div class="d-flex justify-content-between mb-2 fs--1 text-danger">
                                                    <span>Discount ({{ $previewCalc['discount_percentage'] }}%):</span>
                                                    <span>- PKR {{ number_format($previewCalc['discount_amount'], 2) }}</span>
                                                </div>
                                            @endif
                                            @if($previewCalc['tax_amount'] > 0)
                                                <div class="d-flex justify-content-between mb-2 fs--1 text-info">
                                                    <span>Provincial Sales Tax ({{ $previewCalc['tax_percentage'] }}%):</span>
                                                    <span>+ PKR {{ number_format($previewCalc['tax_amount'], 2) }}</span>
                                                </div>
                                            @endif
                                            <hr class="my-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="fs-0 fw-bold text-primary">Net Total:</span>
                                                <span class="fs-1 fw-bolder text-primary">PKR {{ number_format($previewCalc['net_total_amount'], 2) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light py-2">
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="$set('showModal', false)">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-file-invoice me-1"></i> Issue Quotation
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- QUOTATION DOSSIER VIEW MODAL -->
    @if($showDetailModal && $viewingQuotation)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);" aria-modal="true" role="dialog">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-light py-2">
                        <h6 class="modal-title text-primary fw-bold">
                            <i class="fas fa-file-invoice me-1"></i> Freight Quotation # {{ $viewingQuotation->quotation_number }}
                        </h6>
                        <button type="button" class="btn-close" wire:click="$set('showDetailModal', false)"></button>
                    </div>
                    <div class="modal-body p-3">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <div class="card h-100 bg-light border-0">
                                    <div class="card-body p-3">
                                        <h6 class="fw-bold fs--1 text-uppercase text-700 mb-2">Customer & Route Details</h6>
                                        <div class="fs--1 mb-1"><strong>Client:</strong> {{ $viewingQuotation->customer->name }} ({{ $viewingQuotation->customer->customer_code }})</div>
                                        <div class="fs--1 mb-1"><strong>Phone:</strong> {{ $viewingQuotation->customer->phone }}</div>
                                        <div class="fs--1 mb-1"><strong>Origin:</strong> {{ $viewingQuotation->origin_city }} ({{ $viewingQuotation->origin_address ?: 'Hub Depot' }})</div>
                                        <div class="fs--1 mb-1"><strong>Destination:</strong> {{ $viewingQuotation->destination_city }} ({{ $viewingQuotation->destination_address ?: 'Consignee Premises' }})</div>
                                        <div class="fs--1"><strong>Distance:</strong> {{ $viewingQuotation->distance_km ? number_format($viewingQuotation->distance_km) . ' km' : 'N/A' }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100 bg-light border-0">
                                    <div class="card-body p-3">
                                        <h6 class="fw-bold fs--1 text-uppercase text-700 mb-2">Cargo & Requirements</h6>
                                        <div class="fs--1 mb-1"><strong>Cargo:</strong> {{ $viewingQuotation->cargo_description }}</div>
                                        <div class="fs--1 mb-1"><strong>Category:</strong> {{ str_replace('_', ' ', $viewingQuotation->cargo_category) }}</div>
                                        <div class="fs--1 mb-1"><strong>Specs:</strong> {{ $viewingQuotation->weight_tons }} Tons | {{ $viewingQuotation->package_count }} Packages</div>
                                        <div class="fs--1 mb-1"><strong>Vehicle:</strong> {{ $viewingQuotation->required_vehicle_type }} ({{ $viewingQuotation->required_vehicle_category }})</div>
                                        <div class="fs--1"><strong>Validity:</strong> {{ $viewingQuotation->valid_until->format('d M Y') }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Price Breakdown Card -->
                        <div class="card bg-soft-primary border-0 mb-3">
                            <div class="card-body p-3">
                                <div class="row g-2 text-center">
                                    <div class="col-md-3">
                                        <div class="fs--2 text-muted">Base Freight</div>
                                        <div class="fw-bold fs-0 text-primary">PKR {{ number_format($viewingQuotation->freight_amount, 2) }}</div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="fs--2 text-muted">Surcharges & Tolls</div>
                                        <div class="fw-bold fs-0 text-info">PKR {{ number_format($viewingQuotation->loading_charges + $viewingQuotation->unloading_charges + $viewingQuotation->toll_charges + $viewingQuotation->detention_charges + $viewingQuotation->other_charges, 2) }}</div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="fs--2 text-muted">Sales Tax</div>
                                        <div class="fw-bold fs-0 text-secondary">PKR {{ number_format($viewingQuotation->tax_amount, 2) }}</div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="fs--2 text-muted">Net Total</div>
                                        <div class="fw-bold fs-1 text-success">PKR {{ number_format($viewingQuotation->net_total_amount, 2) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if($viewingQuotation->convertedBooking)
                            <div class="alert alert-soft-info py-2 fs--1 mb-0">
                                <i class="fas fa-check-circle me-1"></i> Converted to Confirmed Booking: <strong>{{ $viewingQuotation->convertedBooking->booking_number }}</strong>
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <a href="{{ route('print.quotation', $viewingQuotation->id) }}" target="_blank" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-print me-1"></i> Print Formal Quotation (A4)
                        </a>
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="$set('showDetailModal', false)">Close</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>


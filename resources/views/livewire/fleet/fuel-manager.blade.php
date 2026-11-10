<div>
    {{-- Page Header --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6">
            <h3 class="mb-0 text-primary fw-bold"><i class="fas fa-gas-pump me-2"></i>Fuel Management & Efficiency Tracking</h3>
            <p class="text-muted fs--1 mb-0">Diesel Dispensing, Corporate Fuel Cards, Odometer Audits & Kilometre-per-Liter Efficiency</p>
        </div>
        <div class="col-12 col-md-6 text-md-end d-flex gap-2 justify-content-md-end">
            <button wire:click="openRefuelModal" class="btn btn-primary">
                <i class="fas fa-plus-circle me-1"></i> Log Fuel Issue
            </button>
            <button wire:click="$set('showSupplierModal', true)" class="btn btn-outline-secondary">
                <i class="fas fa-building me-1"></i> Add Supplier
            </button>
            <button wire:click="$set('showCardModal', true)" class="btn btn-outline-secondary">
                <i class="fas fa-credit-card me-1"></i> Add Card
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

    {{-- KPI Cards --}}
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="card h-100 shadow-sm border-start border-4 border-primary">
                <div class="card-body p-3">
                    <div class="fs--2 text-muted text-uppercase fw-bold">TOTAL FUEL ISSUED</div>
                    <div class="fs-2 fw-bold text-primary">{{ number_format($totalLiters, 1) }} <span class="fs--1 text-muted">L</span></div>
                    <div class="fs--2 text-muted mt-1">Fleet diesel & gas</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card h-100 shadow-sm border-start border-4 border-success">
                <div class="card-body p-3">
                    <div class="fs--2 text-muted text-uppercase fw-bold">TOTAL FUEL EXPENSE</div>
                    <div class="fs-2 fw-bold text-success">PKR {{ number_format($totalCost, 2) }}</div>
                    <div class="fs--2 text-muted mt-1">Bunkers & pump settlements</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card h-100 shadow-sm border-start border-4 border-info">
                <div class="card-body p-3">
                    <div class="fs--2 text-muted text-uppercase fw-bold">AVG FUEL ECONOMY</div>
                    <div class="fs-2 fw-bold text-info">{{ $avgEconomy }} <span class="fs--1 text-muted">KM/L</span></div>
                    <div class="fs--2 text-muted mt-1">Fleet overall consumption</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card h-100 shadow-sm border-start border-4 {{ $flaggedCount > 0 ? 'border-danger' : 'border-secondary' }}">
                <div class="card-body p-3">
                    <div class="fs--2 text-muted text-uppercase fw-bold">AUDIT ANOMALIES</div>
                    <div class="fs-2 fw-bold {{ $flaggedCount > 0 ? 'text-danger' : 'text-secondary' }}">{{ $flaggedCount }}</div>
                    <div class="fs--2 text-muted mt-1">Odometer / economy flags</div>
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
                        <input type="text" wire:model.live.debounce.300ms="search" class="form-control" placeholder="Search by Slip #, Vehicle Reg, Driver Name, Invoice Ref...">
                    </div>
                </div>
                <div class="col-md-4">
                    <select wire:model.live="vehicleFilter" class="form-select">
                        <option value="">All Vehicles</option>
                        @foreach($vehicles as $veh)
                            <option value="{{ $veh->id }}">{{ $veh->registration_number }} ({{ $veh->make_model }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <div class="form-check mt-1">
                        <input class="form-check-input" type="checkbox" wire:model.live="suspiciousOnly" id="suspiciousCheck">
                        <label class="form-check-label fs--1 fw-bold text-danger" for="suspiciousCheck">
                            <i class="fas fa-flag me-1"></i> Flagged Anomalies Only
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Fuel Entries Table Card --}}
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Slip # & Date</th>
                        <th>Vehicle & Driver</th>
                        <th>Supplier / Card</th>
                        <th class="text-center">Quantity & Rate</th>
                        <th class="text-end">Total (PKR)</th>
                        <th class="text-center">Odometer & Distance</th>
                        <th class="text-center">KM/L & Cost/KM</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $entry)
                        <tr class="{{ $entry->is_suspicious ? 'table-warning' : '' }}">
                            <td>
                                <a href="javascript:void(0)" wire:click="viewDetails({{ $entry->id }})" class="fw-bold text-primary text-decoration-none">
                                    {{ $entry->entry_number }}
                                </a>
                                <div class="fs--2 text-muted">{{ $entry->entry_date->format('d M Y') }}</div>
                                @if($entry->trip)
                                    <div class="fs--2 text-muted">Trip: <strong>{{ $entry->trip->trip_number }}</strong></div>
                                @endif
                            </td>
                            <td>
                                <div><i class="fas fa-truck text-primary me-1"></i><strong>{{ $entry->vehicle?->registration_number }}</strong></div>
                                <div class="fs--2 text-muted">{{ $entry->driver?->name ?: 'Duty Driver' }}</div>
                            </td>
                            <td>
                                <div class="fw-semi-bold">{{ $entry->supplier?->name ?: 'Direct Pump' }}</div>
                                @if($entry->card)
                                    <div class="fs--2 text-muted"><i class="fas fa-credit-card me-1 text-info"></i>{{ $entry->card->card_number }}</div>
                                @else
                                    <div class="fs--2 text-muted">{{ strtoupper(str_replace('_', ' ', $entry->payment_method)) }}</div>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="fw-bold text-primary fs-0">{{ number_format($entry->quantity_liters, 1) }} L</div>
                                <div class="fs--2 text-muted">@ PKR {{ number_format($entry->rate_per_liter, 2) }}</div>
                            </td>
                            <td class="text-end fw-bold text-success fs-0">
                                PKR {{ number_format($entry->total_amount, 2) }}
                            </td>
                            <td class="text-center">
                                <div class="fw-bold">{{ number_format($entry->odometer_km) }} KM</div>
                                <div class="fs--2 text-muted">&Delta; {{ number_format($entry->distance_km) }} KM</div>
                            </td>
                            <td class="text-center">
                                @if($entry->fuel_economy_km_per_liter)
                                    <div class="fw-bold fs-0 {{ $entry->fuel_economy_km_per_liter < 1.5 ? 'text-danger' : 'text-success' }}">
                                        {{ $entry->fuel_economy_km_per_liter }} KM/L
                                    </div>
                                    <div class="fs--2 text-muted">PKR {{ $entry->cost_per_km }}/KM</div>
                                @else
                                    <span class="text-muted fs--2">-</span>
                                @endif
                                @if($entry->is_suspicious)
                                    <span class="badge bg-danger fs--2 mt-1" title="{{ $entry->anomaly_reason }}">
                                        <i class="fas fa-exclamation-triangle me-1"></i>ANOMALY
                                    </span>
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                <div class="d-flex justify-content-end gap-1">
                                    <a href="{{ route('print.fuel-slip', $entry->id) }}" target="_blank" class="btn btn-sm btn-outline-primary" title="Print Slip">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    <button wire:click="viewDetails({{ $entry->id }})" class="btn btn-sm btn-outline-secondary" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                <i class="fas fa-gas-pump fa-2x mb-2 d-block"></i>
                                No fuel records matching your filter criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-light p-2">
            {{ $entries->links() }}
        </div>
    </div>

    {{-- Log Refuel Modal --}}
    @if($showRefuelModal)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="fas fa-gas-pump me-2"></i>Log Fuel Issue / Refuel Entry</h5>
                        <button type="button" class="btn-close btn-close-white" wire:click="$set('showRefuelModal', false)"></button>
                    </div>
                    <form wire:submit.prevent="saveFuelEntry">
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Vehicle <span class="text-danger">*</span></label>
                                    <select wire:model.live="vehicle_id" class="form-select" required>
                                        <option value="">-- Select Vehicle --</option>
                                        @foreach($vehicles as $veh)
                                            <option value="{{ $veh->id }}">{{ $veh->registration_number }} &bull; {{ $veh->make_model }} (Current: {{ number_format($veh->current_odometer) }} KM)</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Commercial Driver</label>
                                    <select wire:model="driver_id" class="form-select">
                                        <option value="">-- Assigned Driver --</option>
                                        @foreach($drivers as $drv)
                                            <option value="{{ $drv->id }}">{{ $drv->name }} ({{ $drv->phone }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Refuel Date <span class="text-danger">*</span></label>
                                    <input type="date" wire:model="entry_date" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Current Odometer (KM) <span class="text-danger">*</span></label>
                                    <input type="number" wire:model="odometer_km" class="form-control" min="0" required>
                                    <div class="fs--2 text-muted">Dashboard odometer reading at the fuel pump.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Fuel Type <span class="text-danger">*</span></label>
                                    <select wire:model="fuel_type" class="form-select" required>
                                        <option value="diesel">High Speed Diesel (HSD)</option>
                                        <option value="petrol">Motor Gasoline (Petrol)</option>
                                        <option value="cng">CNG</option>
                                        <option value="adblue">AdBlue / DEF</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Quantity (Liters) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" wire:model.live="quantity_liters" class="form-control" min="1" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Rate / Liter (PKR) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" wire:model.live="rate_per_liter" class="form-control" min="1" required>
                                </div>
                                <div class="col-12 p-2 border rounded bg-light">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-bold">Calculated Total Fuel Cost:</span>
                                        <span class="fs-1 fw-bold text-success">PKR {{ number_format($quantity_liters * $rate_per_liter, 2) }}</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Fuel Supplier</label>
                                    <select wire:model="fuel_supplier_id" class="form-select">
                                        <option value="">-- Choose Fuel Supplier --</option>
                                        @foreach($suppliers as $sup)
                                            <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Fuel Card (if used)</label>
                                    <select wire:model="fuel_card_id" class="form-select">
                                        <option value="">-- Select Fleet Fuel Card --</option>
                                        @foreach($cards as $c)
                                            <option value="{{ $c->id }}">{{ $c->card_number }} (Bal: PKR {{ number_format($c->current_balance) }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Payment Method</label>
                                    <select wire:model="payment_method" class="form-select">
                                        <option value="fuel_card">Corporate Fuel Card</option>
                                        <option value="cash">Cash / Terminal Petty Cash</option>
                                        <option value="credit">Supplier Credit Account</option>
                                        <option value="driver_advance">Driver Advance Settlement</option>
                                        <option value="company_account">Direct Bank Transfer</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Linked Trip (Voyage Reference)</label>
                                    <select wire:model="trip_id" class="form-select">
                                        <option value="">-- Optional Trip Allocation --</option>
                                        @foreach($trips as $t)
                                            <option value="{{ $t->id }}">{{ $t->trip_number }} ({{ $t->origin_city }} &rarr; {{ $t->destination_city }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label fw-bold">Pump Receipt / Invoice Reference #</label>
                                    <input type="text" wire:model="invoice_reference" class="form-control" placeholder="e.g. PSO-INV-99214">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="$set('showRefuelModal', false)">Cancel</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-check-circle me-1"></i> Save & Audit Refuel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Add Supplier Modal --}}
    @if($showSupplierModal)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title"><i class="fas fa-building me-2"></i>Add Fuel Supplier</h5>
                        <button type="button" class="btn-close btn-close-white" wire:click="$set('showSupplierModal', false)"></button>
                    </div>
                    <form wire:submit.prevent="saveSupplier">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Supplier Name <span class="text-danger">*</span></label>
                                <input type="text" wire:model="supplier_name" class="form-control" placeholder="e.g. Pakistan State Oil (PSO) / Shell" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Contact Person</label>
                                <input type="text" wire:model="supplier_contact" class="form-control" placeholder="Key Account Manager">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Phone Number</label>
                                <input type="text" wire:model="supplier_phone" class="form-control" placeholder="021-111-111-PSO">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Credit Limit (PKR)</label>
                                <input type="number" wire:model="supplier_credit_limit" class="form-control" min="0">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="$set('showSupplierModal', false)">Cancel</button>
                            <button type="submit" class="btn btn-dark"><i class="fas fa-save me-1"></i> Save Supplier</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Add Card Modal --}}
    @if($showCardModal)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title"><i class="fas fa-credit-card me-2"></i>Register Fleet Fuel Card</h5>
                        <button type="button" class="btn-close btn-close-white" wire:click="$set('showCardModal', false)"></button>
                    </div>
                    <form wire:submit.prevent="saveCard">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Card Number <span class="text-danger">*</span></label>
                                <input type="text" wire:model="card_number" class="form-control" placeholder="7002-XXXX-XXXX-1234" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Issuing Fuel Supplier</label>
                                <select wire:model="card_supplier_id" class="form-select">
                                    <option value="">-- Choose Supplier --</option>
                                    @foreach($suppliers as $sup)
                                        <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Assigned Vehicle</label>
                                <select wire:model="card_vehicle_id" class="form-select">
                                    <option value="">-- Optional Vehicle Binding --</option>
                                    @foreach($vehicles as $veh)
                                        <option value="{{ $veh->id }}">{{ $veh->registration_number }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Monthly Spend Limit (PKR)</label>
                                <input type="number" wire:model="card_monthly_limit" class="form-control" min="0">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="$set('showCardModal', false)">Cancel</button>
                            <button type="submit" class="btn btn-info"><i class="fas fa-save me-1"></i> Register Card</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Detail Modal --}}
    @if($showDetailModal && $selectedEntry)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-light">
                        <h5 class="modal-title text-primary"><i class="fas fa-gas-pump me-2"></i>Fuel Slip &bull; {{ $selectedEntry->entry_number }}</h5>
                        <button type="button" class="btn-close" wire:click="$set('showDetailModal', false)"></button>
                    </div>
                    <div class="modal-body">
                        <div class="p-2 border rounded bg-light mb-3">
                            <div class="fs--1">Vehicle: <strong>{{ $selectedEntry->vehicle?->registration_number }}</strong> ({{ $selectedEntry->vehicle?->make_model }})</div>
                            <div class="fs--1">Driver: <strong>{{ $selectedEntry->driver?->name ?: 'N/A' }}</strong></div>
                            <div class="fs--1">Date: <strong>{{ $selectedEntry->entry_date->format('d M Y') }}</strong></div>
                            <div class="fs--1">Supplier: <strong>{{ $selectedEntry->supplier?->name ?: 'Direct' }}</strong></div>
                        </div>
                        <div class="row g-2 text-center mb-3">
                            <div class="col-6">
                                <div class="p-2 border rounded">
                                    <div class="fs--2 text-muted">LITERS DISPENSED</div>
                                    <div class="fw-bold fs-1 text-primary">{{ number_format($selectedEntry->quantity_liters, 2) }} L</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 border rounded">
                                    <div class="fs--2 text-muted">TOTAL COST</div>
                                    <div class="fw-bold fs-1 text-success">PKR {{ number_format($selectedEntry->total_amount, 2) }}</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 border rounded">
                                    <div class="fs--2 text-muted">DISTANCE TRAVELLED</div>
                                    <div class="fw-bold fs-0">{{ number_format($selectedEntry->distance_km) }} KM</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 border rounded">
                                    <div class="fs--2 text-muted">FUEL ECONOMY</div>
                                    <div class="fw-bold fs-0 text-info">{{ $selectedEntry->fuel_economy_km_per_liter ? $selectedEntry->fuel_economy_km_per_liter . ' KM/L' : 'N/A' }}</div>
                                </div>
                            </div>
                        </div>
                        @if($selectedEntry->is_suspicious)
                            <div class="alert alert-warning fs--1 p-2">
                                <strong>Audit Warning:</strong> {{ $selectedEntry->anomaly_reason }}
                            </div>
                        @endif
                        <div class="text-center">
                            <a href="{{ route('print.fuel-slip', $selectedEntry->id) }}" target="_blank" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-print me-1"></i> Print Formal Fuel Slip
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

<div>
    {{-- Header --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-8">
            <h3 class="mb-0 text-primary fw-bold"><i class="fas fa-clipboard-check me-2"></i>Deliveries & Electronic Proof of Delivery (EPOD)</h3>
            <p class="text-muted fs--1 mb-0">Consignee Handover Verification, Shortage/Damage Recording, GPS Geolocation & Digital Delivery Receipts</p>
        </div>
    </div>

    {{-- Flash Notifications --}}
    @if(session()->has('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-1"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Filter Card --}}
    <div class="card mb-3">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" wire:model.live.debounce.300ms="search" class="form-control" placeholder="Search by Consignment #, Consignee Name, City, Trip #...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select wire:model.live="deliveryStatusFilter" class="form-select">
                        <option value="">All Delivery Statuses</option>
                        <option value="pending">Pending Delivery</option>
                        <option value="delivered_full">Delivered in Full</option>
                        <option value="delivered_partial">Partially Delivered</option>
                        <option value="failed">Failed Delivery</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select wire:model.live="tripFilter" class="form-select">
                        <option value="">All Active Trips</option>
                        @foreach($trips as $t)
                            <option value="{{ $t->id }}">{{ $t->trip_number }} ({{ $t->origin_city }} &rarr; {{ $t->destination_city }})</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    {{-- Shipments Table Card --}}
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Trip #</th>
                        <th>Consignment (Bilty)</th>
                        <th>Consignee & Destination</th>
                        <th class="text-center">Assigned</th>
                        <th class="text-center">Delivered</th>
                        <th class="text-center">Dmg / Short</th>
                        <th>Delivery Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($allocations as $alloc)
                        <tr>
                            <td>
                                <span class="fw-bold text-dark">{{ $alloc->trip?->trip_number }}</span>
                                <div class="fs--2 text-muted">{{ $alloc->trip?->origin_city }} &rarr; {{ $alloc->trip?->destination_city }}</div>
                            </td>
                            <td>
                                <a href="{{ route('print.consignment', $alloc->consignment_id) }}" target="_blank" class="fw-bold text-primary text-decoration-none">
                                    {{ $alloc->consignment?->consignment_number }}
                                </a>
                                <div class="fs--2 text-muted">Sender: {{ $alloc->consignment?->customer?->name }}</div>
                            </td>
                            <td>
                                <div class="fw-semi-bold">{{ $alloc->consignment?->consignee_name }}</div>
                                <div class="fs--2 text-muted"><i class="fas fa-map-marker-alt me-1 text-danger"></i>{{ $alloc->consignment?->consignee_city }} &bull; {{ $alloc->consignment?->consignee_phone }}</div>
                            </td>
                            <td class="text-center fw-bold">{{ $alloc->assigned_quantity }}</td>
                            <td class="text-center fw-bold text-success">{{ $alloc->delivered_quantity }}</td>
                            <td class="text-center">
                                @if($alloc->damaged_quantity > 0 || $alloc->short_quantity > 0)
                                    <span class="badge bg-danger fs--2">{{ $alloc->damaged_quantity }} dmg</span>
                                    <span class="badge bg-warning text-dark fs--2">{{ $alloc->short_quantity }} short</span>
                                @else
                                    <span class="text-muted fs--2">0</span>
                                @endif
                            </td>
                            <td>
                                @if($alloc->delivery_status === 'delivered_full')
                                    <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>FULL DELIVERY</span>
                                @elseif($alloc->delivery_status === 'delivered_partial')
                                    <span class="badge bg-warning text-dark"><i class="fas fa-adjust me-1"></i>PARTIAL</span>
                                @elseif($alloc->delivery_status === 'failed')
                                    <span class="badge bg-danger">FAILED</span>
                                @else
                                    <span class="badge bg-secondary">PENDING</span>
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                @if($alloc->delivery_status === 'pending' || $alloc->delivery_status === 'delivered_partial')
                                    <button wire:click="openDeliveryModal({{ $alloc->id }})" class="btn btn-sm btn-primary">
                                        <i class="fas fa-clipboard-check me-1"></i> Record POD
                                    </button>
                                @endif

                                @php
                                    $latestPod = $alloc->proofOfDeliveries->first();
                                @endphp
                                @if($latestPod)
                                    <a href="{{ route('print.pod-receipt', $latestPod->id) }}" target="_blank" class="btn btn-sm btn-outline-success">
                                        <i class="fas fa-print me-1"></i> POD Receipt
                                    </a>
                                    <button wire:click="viewPod({{ $latestPod->id }})" class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                <i class="fas fa-boxes fa-2x mb-2 d-block"></i>
                                No shipment deliveries matching your criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-light p-2">
            {{ $allocations->links() }}
        </div>
    </div>

    {{-- Record Delivery & Proof of Delivery Modal --}}
    @if($showDeliveryModal && $selectedAllocation)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="fas fa-clipboard-check me-2"></i>Record Physical Delivery & Generate POD</h5>
                        <button type="button" class="btn-close btn-close-white" wire:click="$set('showDeliveryModal', false)"></button>
                    </div>
                    <form wire:submit.prevent="submitDelivery">
                        <div class="modal-body">
                            <div class="row g-2 p-2 border rounded bg-light mb-3">
                                <div class="col-4">
                                    <div class="fs--2 text-muted">TRIP NUMBER:</div>
                                    <div class="fw-bold">{{ $selectedAllocation->trip?->trip_number }}</div>
                                </div>
                                <div class="col-4">
                                    <div class="fs--2 text-muted">CONSIGNMENT (BILTY):</div>
                                    <div class="fw-bold">{{ $selectedAllocation->consignment?->consignment_number }}</div>
                                </div>
                                <div class="col-4">
                                    <div class="fs--2 text-muted">ASSIGNED PACKAGES:</div>
                                    <div class="fw-bold fs-0 text-primary">{{ $selectedAllocation->assigned_quantity }} Pkgs</div>
                                </div>
                            </div>

                            <h6 class="fw-bold mb-2">1. Delivery Quantity Verification</h6>
                            <div class="row g-2 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-bold text-success">Delivered in Sound Condition <span class="text-danger">*</span></label>
                                    <input type="number" wire:model="delivered_quantity" class="form-control" min="0" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-bold text-danger">Damaged Packages</label>
                                    <input type="number" wire:model="damaged_quantity" class="form-control" min="0">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-bold text-warning">Short / Missing Packages</label>
                                    <input type="number" wire:model="short_quantity" class="form-control" min="0">
                                </div>
                            </div>

                            <h6 class="fw-bold mb-2">2. Consignee Receiving Party Identification</h6>
                            <div class="row g-2 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-bold">Receiver Full Name <span class="text-danger">*</span></label>
                                    <input type="text" wire:model="receiver_name" class="form-control" placeholder="Recipient's Name" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-bold">Receiver Contact Phone</label>
                                    <input type="text" wire:model="receiver_phone" class="form-control" placeholder="0300-1234567">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-bold">Receiver CNIC / National ID</label>
                                    <input type="text" wire:model="receiver_cnic" class="form-control" placeholder="42101-1234567-1">
                                </div>
                            </div>

                            <h6 class="fw-bold mb-2">3. Timestamp & Geolocation Evidence</h6>
                            <div class="row g-2 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-bold">Delivery Timestamp <span class="text-danger">*</span></label>
                                    <input type="datetime-local" wire:model="delivery_datetime" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-bold">GPS Latitude</label>
                                    <input type="number" step="0.0000001" wire:model="gps_latitude" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-bold">GPS Longitude</label>
                                    <input type="number" step="0.0000001" wire:model="gps_longitude" class="form-control">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fs--1 fw-bold">Delivery Remarks & Exception Notes</label>
                                <textarea wire:model="exception_notes" class="form-control" rows="2" placeholder="Note any seal tampering, package dents, delivery time variance, etc..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="$set('showDeliveryModal', false)">Cancel</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-check-circle me-1"></i> Verify Delivery & Save POD</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- View POD Detail Modal --}}
    @if($showPodDetailModal && $selectedPod)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-light">
                        <h5 class="modal-title text-primary"><i class="fas fa-receipt me-2"></i>POD Details &bull; POD-{{ str_pad($selectedPod->id, 6, '0', STR_PAD_LEFT) }}</h5>
                        <button type="button" class="btn-close" wire:click="$set('showPodDetailModal', false)"></button>
                    </div>
                    <div class="modal-body">
                        <div class="p-2 border rounded bg-light mb-3">
                            <div class="fs--1">Consignment: <strong>{{ $selectedPod->consignment?->consignment_number }}</strong></div>
                            <div class="fs--1">Trip: <strong>{{ $selectedPod->trip?->trip_number }}</strong></div>
                            <div class="fs--1">Received By: <strong>{{ $selectedPod->receiver_name }}</strong> (CNIC: {{ $selectedPod->receiver_cnic ?: 'N/A' }})</div>
                            <div class="fs--1">Contact: <strong>{{ $selectedPod->receiver_phone ?: 'N/A' }}</strong></div>
                            <div class="fs--1">Delivery Time: <strong>{{ $selectedPod->delivery_datetime->format('d M Y H:i') }}</strong></div>
                        </div>
                        <div class="row g-2 text-center mb-3">
                            <div class="col-4">
                                <div class="p-2 border rounded">
                                    <div class="fs--2 text-muted">DELIVERED</div>
                                    <div class="fw-bold fs-1 text-success">{{ $selectedPod->delivered_quantity }}</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 border rounded">
                                    <div class="fs--2 text-muted">DAMAGED</div>
                                    <div class="fw-bold fs-1 text-danger">{{ $selectedPod->damaged_quantity }}</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 border rounded">
                                    <div class="fs--2 text-muted">SHORT</div>
                                    <div class="fw-bold fs-1 text-warning">{{ $selectedPod->short_quantity }}</div>
                                </div>
                            </div>
                        </div>
                        @if($selectedPod->exception_notes)
                            <div class="alert alert-warning fs--1 p-2">
                                <strong>Exception Notes:</strong> {{ $selectedPod->exception_notes }}
                            </div>
                        @endif
                        <div class="text-center">
                            <a href="{{ route('print.pod-receipt', $selectedPod->id) }}" target="_blank" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-print me-1"></i> Print Formal POD Certificate
                            </a>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="$set('showPodDetailModal', false)">Close</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

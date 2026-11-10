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
                        <input type="text" class="form-control" placeholder="Search customer, company, NTN, phone..." wire:model.live.debounce.300ms="search">
                    </div>
                </div>
                <div class="col-md-auto d-flex gap-2 align-items-center">
                    <select class="form-select form-select-sm" wire:model.live="typeFilter" style="width: 140px;">
                        <option value="">All Types</option>
                        <option value="corporate">Corporate</option>
                        <option value="individual">Individual</option>
                    </select>

                    <select class="form-select form-select-sm" wire:model.live="statusFilter" style="width: 130px;">
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="blacklisted">Blacklisted</option>
                    </select>

                    <button type="button" class="btn btn-primary btn-sm shadow-sm" wire:click="openCreateCustomerModal">
                        <i class="fas fa-user-plus me-1"></i> Register Customer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Customers Table -->
    <div class="card shadow-sm mb-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped fs--1 mb-0 align-middle">
                    <thead class="bg-200 text-900">
                        <tr>
                            <th>Customer & Account</th>
                            <th>Contact Person</th>
                            <th>Tax NTN / STRN</th>
                            <th>Credit Terms & Limit</th>
                            <th>Commercial Contract</th>
                            <th>Billing City</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($customers as $cust)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar avatar-m me-2">
                                            <div class="avatar-name rounded-circle bg-soft-primary text-primary fw-bold">
                                                {{ strtoupper(substr($cust->name, 0, 2)) }}
                                            </div>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-primary">{{ $cust->name }}</div>
                                            @if($cust->company_name)
                                                <div class="fs--2 text-muted">{{ $cust->company_name }}</div>
                                            @endif
                                            <span class="badge bg-soft-{{ $cust->isCorporate() ? 'info' : 'secondary' }} text-dark fs--2">
                                                {{ ucfirst($cust->customer_type) }} ({{ $cust->customer_code }})
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semi-bold">{{ $cust->contact_person ?: $cust->name }}</div>
                                    <div class="text-muted fs--2"><i class="fas fa-phone-alt me-1"></i> {{ $cust->phone }}</div>
                                    @if($cust->email)
                                        <div class="text-muted fs--2"><i class="fas fa-envelope me-1"></i> {{ $cust->email }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if($cust->tax_ntn)
                                        <span class="fw-semi-bold">NTN: {{ $cust->tax_ntn }}</span>
                                    @else
                                        <span class="text-muted fs--2">Non-NTN</span>
                                    @endif
                                    @if($cust->is_tax_exempt)
                                        <div><span class="badge bg-soft-warning text-dark fs--2">Tax Exempt</span></div>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semi-bold text-dark">PKR {{ number_format($cust->credit_limit, 2) }}</div>
                                    <div class="fs--2 text-muted">{{ $cust->credit_days }} Days | {{ str_replace('_', ' ', $cust->payment_terms) }}</div>
                                </td>
                                <td>
                                    @if($cust->activeContract)
                                        <span class="badge bg-soft-success text-success fw-bold" title="{{ $cust->activeContract->contract_title }}">
                                            <i class="fas fa-file-contract me-1"></i> {{ $cust->activeContract->contract_number }}
                                        </span>
                                        <div class="fs--2 text-muted">{{ ucfirst(str_replace('_', ' ', $cust->activeContract->rate_type)) }}</div>
                                    @else
                                        <span class="badge bg-soft-secondary text-secondary">No Contract (Spot)</span>
                                    @endif
                                </td>
                                <td>{{ $cust->billing_city ?: 'N/A' }}</td>
                                <td>
                                    @switch($cust->status)
                                        @case('active')
                                            <span class="badge bg-soft-success text-success"><i class="fas fa-check-circle me-1"></i> Active</span>
                                            @break
                                        @case('inactive')
                                            <span class="badge bg-soft-warning text-warning">Inactive</span>
                                            @break
                                        @case('blacklisted')
                                            <span class="badge bg-soft-danger text-danger"><i class="fas fa-ban me-1"></i> Blacklisted</span>
                                            @break
                                    @endswitch
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-falcon-default" wire:click="viewCustomer({{ $cust->id }})" title="View Customer Dossier">
                                            <i class="fas fa-eye text-primary"></i>
                                        </button>
                                        <button type="button" class="btn btn-falcon-default" wire:click="openAddressModal({{ $cust->id }})" title="Add Delivery Shipping Point">
                                            <i class="fas fa-map-marker-alt text-info"></i>
                                        </button>
                                        <button type="button" class="btn btn-falcon-default" wire:click="openContractModal({{ $cust->id }})" title="Establish Commercial Contract">
                                            <i class="fas fa-file-contract text-success"></i>
                                        </button>
                                        <button type="button" class="btn btn-falcon-default" wire:click="editCustomer({{ $cust->id }})" title="Edit Profile">
                                            <i class="fas fa-edit text-warning"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="fas fa-users fa-2x mb-2 d-block text-300"></i>
                                    No commercial customers found matching your criteria.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($customers->hasPages())
            <div class="card-footer py-2">
                {{ $customers->links() }}
            </div>
        @endif
    </div>

    <!-- CREATE / EDIT CUSTOMER MODAL -->
    @if($showCustomerModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);" aria-modal="true" role="dialog">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-light py-2">
                        <h6 class="modal-title text-primary fw-bold">
                            <i class="fas fa-user-plus me-1"></i> {{ $isEditingCustomer ? 'Edit Customer Profile' : 'Register New Commercial Customer' }}
                        </h6>
                        <button type="button" class="btn-close" wire:click="$set('showCustomerModal', false)"></button>
                    </div>
                    <form wire:submit.prevent="saveCustomer">
                        <div class="modal-body p-3">
                            <h6 class="fw-bold fs--1 text-uppercase text-700 border-bottom pb-1 mb-2">1. Organization & Contact Identification</h6>
                            <div class="row g-2 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-semi-bold">Customer Type <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm @error('customer_type') is-invalid @enderror" wire:model.live="customer_type">
                                        <option value="corporate">Corporate Organization</option>
                                        <option value="individual">Individual Shipper / Trader</option>
                                    </select>
                                    @error('customer_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label fs--1 fw-semi-bold">Customer / Trading Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm @error('name') is-invalid @enderror" wire:model="name" placeholder="e.g. Fauji Fertilizer Company / Haji Bashir Traders">
                                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fs--1 fw-semi-bold">Registered Legal Entity Name</label>
                                    <input type="text" class="form-control form-control-sm @error('company_name') is-invalid @enderror" wire:model="company_name" placeholder="e.g. FFC Logistics Division (Pvt) Ltd">
                                    @error('company_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs--1 fw-semi-bold">Contact Person</label>
                                    <input type="text" class="form-control form-control-sm @error('contact_person') is-invalid @enderror" wire:model="contact_person" placeholder="e.g. Tariq Mehmood">
                                    @error('contact_person') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs--1 fw-semi-bold">Designation</label>
                                    <input type="text" class="form-control form-control-sm @error('designation') is-invalid @enderror" wire:model="designation" placeholder="Supply Chain Manager">
                                    @error('designation') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-semi-bold">Primary Phone <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm @error('phone') is-invalid @enderror" wire:model="phone" placeholder="042-35889900 / 0300-1234567">
                                    @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-semi-bold">Mobile Number</label>
                                    <input type="text" class="form-control form-control-sm @error('mobile') is-invalid @enderror" wire:model="mobile" placeholder="0321-7654321">
                                    @error('mobile') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-semi-bold">Billing Email</label>
                                    <input type="email" class="form-control form-control-sm @error('email') is-invalid @enderror" wire:model="email" placeholder="billing@client.com">
                                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <h6 class="fw-bold fs--1 text-uppercase text-700 border-bottom pb-1 mb-2">2. Tax Credentials & Invoicing Address</h6>
                            <div class="row g-2 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-semi-bold">FBR NTN Number</label>
                                    <input type="text" class="form-control form-control-sm @error('tax_ntn') is-invalid @enderror" wire:model="tax_ntn" placeholder="1234567-8">
                                    @error('tax_ntn') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-semi-bold">Provincial Sales Tax / STRN</label>
                                    <input type="text" class="form-control form-control-sm @error('tax_strn') is-invalid @enderror" wire:model="tax_strn" placeholder="PRA / SRB / KPRA #">
                                    @error('tax_strn') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4 d-flex align-items-center mt-3">
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" id="isTaxExempt" wire:model="is_tax_exempt">
                                        <label class="form-check-label fs--1 fw-semi-bold" for="isTaxExempt">Tax Exempt Customer</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fs--1 fw-semi-bold">Billing Address</label>
                                    <input type="text" class="form-control form-control-sm @error('billing_address') is-invalid @enderror" wire:model="billing_address" placeholder="Plot # 45, Industrial Estate, Multan Road">
                                    @error('billing_address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-semi-bold">Billing City</label>
                                    <input type="text" class="form-control form-control-sm @error('billing_city') is-invalid @enderror" wire:model="billing_city" placeholder="Lahore">
                                    @error('billing_city') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-semi-bold">State / Province</label>
                                    <input type="text" class="form-control form-control-sm @error('billing_state') is-invalid @enderror" wire:model="billing_state" placeholder="Punjab">
                                    @error('billing_state') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-semi-bold">Servicing Branch</label>
                                    <select class="form-select form-select-sm @error('branch_id') is-invalid @enderror" wire:model="branch_id">
                                        <option value="">Headquarters / All Branches</option>
                                        @foreach($branches as $b)
                                            <option value="{{ $b->id }}">{{ $b->name }} ({{ $b->code }})</option>
                                        @endforeach
                                    </select>
                                    @error('branch_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <h6 class="fw-bold fs--1 text-uppercase text-700 border-bottom pb-1 mb-2">3. Credit Governance & Payment Terms</h6>
                            <div class="row g-2 mb-3">
                                <div class="col-md-3">
                                    <label class="form-label fs--1 fw-semi-bold">Credit Limit (PKR) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" class="form-control form-control-sm @error('credit_limit') is-invalid @enderror" wire:model="credit_limit">
                                    @error('credit_limit') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs--1 fw-semi-bold">Credit Days <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control form-control-sm @error('credit_days') is-invalid @enderror" wire:model="credit_days">
                                    @error('credit_days') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs--1 fw-semi-bold">Payment Terms <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm @error('payment_terms') is-invalid @enderror" wire:model="payment_terms">
                                        <option value="to_pay">To-Pay (Consignee Cash on Delivery)</option>
                                        <option value="paid">Paid (Cash at Booking)</option>
                                        <option value="credit_15_days">15 Days Credit</option>
                                        <option value="credit_30_days">30 Days Credit</option>
                                        <option value="credit_60_days">60 Days Credit</option>
                                        <option value="bill_to_customer">Monthly Billed Account</option>
                                    </select>
                                    @error('payment_terms') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs--1 fw-semi-bold">Opening Ledger Balance</label>
                                    <input type="number" step="0.01" class="form-control form-control-sm" wire:model="opening_balance" {{ $isEditingCustomer ? 'disabled' : '' }}>
                                </div>
                            </div>

                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-semi-bold">Account Status <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm @error('status') is-invalid @enderror" wire:model="status">
                                        <option value="active">Active (Good Standing)</option>
                                        <option value="inactive">Inactive</option>
                                        <option value="blacklisted">Blacklisted (Do Not Dispatch)</option>
                                    </select>
                                    @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label fs--1 fw-semi-bold">Internal Operational Notes</label>
                                    <input type="text" class="form-control form-control-sm" wire:model="notes" placeholder="Special handling or credit approval notes...">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light py-2">
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="$set('showCustomerModal', false)">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-save me-1"></i> {{ $isEditingCustomer ? 'Save Changes' : 'Register Customer' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- ADD DELIVERY ADDRESS MODAL -->
    @if($showAddressModal && $addressCustomer)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);" aria-modal="true" role="dialog">
            <div class="modal-dialog modal-md">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-info text-white py-2">
                        <h6 class="modal-title fw-bold">
                            <i class="fas fa-map-marker-alt me-1"></i> Add Delivery / Shipping Point
                        </h6>
                        <button type="button" class="btn-close btn-close-white" wire:click="$set('showAddressModal', false)"></button>
                    </div>
                    <form wire:submit.prevent="saveDeliveryAddress">
                        <div class="modal-body p-3">
                            <div class="alert alert-soft-info py-2 mb-3 fs--1">
                                Customer: <strong>{{ $addressCustomer->name }}</strong> ({{ $addressCustomer->customer_code }})
                            </div>
                            <div class="mb-2">
                                <label class="form-label fs--1 fw-semi-bold">Location Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm @error('location_title') is-invalid @enderror" wire:model="location_title" placeholder="e.g. Faisalabad Textile Hub Warehouse # 2">
                                @error('location_title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <label class="form-label fs--1 fw-semi-bold">Contact Person</label>
                                    <input type="text" class="form-control form-control-sm" wire:model="address_contact_person" placeholder="Warehouse Supervisor">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fs--1 fw-semi-bold">Contact Phone</label>
                                    <input type="text" class="form-control form-control-sm" wire:model="address_contact_phone" placeholder="0300-1122334">
                                </div>
                            </div>
                            <div class="mb-2">
                                <label class="form-label fs--1 fw-semi-bold">Full Destination Address <span class="text-danger">*</span></label>
                                <textarea class="form-control form-control-sm @error('address_text') is-invalid @enderror" rows="2" wire:model="address_text" placeholder="Plot / Street / Area coordinates..."></textarea>
                                @error('address_text') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="row g-2">
                                <div class="col-md-8">
                                    <label class="form-label fs--1 fw-semi-bold">Destination City <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm @error('address_city') is-invalid @enderror" wire:model="address_city" placeholder="e.g. Faisalabad">
                                    @error('address_city') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4 d-flex align-items-center mt-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="addrDefault" wire:model="address_is_default">
                                        <label class="form-check-label fs--1" for="addrDefault">Set Default</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light py-2">
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="$set('showAddressModal', false)">Cancel</button>
                            <button type="submit" class="btn btn-info btn-sm text-white">Save Delivery Address</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- ESTABLISH CONTRACT MODAL -->
    @if($showContractModal && $contractCustomer)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);" aria-modal="true" role="dialog">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-success text-white py-2">
                        <h6 class="modal-title fw-bold">
                            <i class="fas fa-file-contract me-1"></i> Establish Commercial Contract Agreement
                        </h6>
                        <button type="button" class="btn-close btn-close-white" wire:click="$set('showContractModal', false)"></button>
                    </div>
                    <form wire:submit.prevent="saveContract">
                        <div class="modal-body p-3">
                            <div class="alert alert-soft-success py-2 mb-3 fs--1">
                                Customer: <strong>{{ $contractCustomer->name }}</strong> ({{ $contractCustomer->customer_code }})
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-semi-bold">Contract Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm @error('contract_number') is-invalid @enderror" wire:model="contract_number">
                                    @error('contract_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label fs--1 fw-semi-bold">Contract Agreement Title <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm @error('contract_title') is-invalid @enderror" wire:model="contract_title">
                                    @error('contract_title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-semi-bold">Rate Calculation Method <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm @error('contract_rate_type') is-invalid @enderror" wire:model="contract_rate_type">
                                        <option value="route_based">Route-Based Corridor Tariff</option>
                                        <option value="per_ton">Per Ton Freight Rate</option>
                                        <option value="per_km">Per Kilometre Freight Rate</option>
                                        <option value="per_vehicle">Per Vehicle Flat Rate</option>
                                        <option value="per_quantity">Per Package / Bag Tariff</option>
                                        <option value="fixed">Fixed Lump-Sum Freight</option>
                                    </select>
                                    @error('contract_rate_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-semi-bold">Base Tariff Rate (PKR) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" class="form-control form-control-sm @error('contract_base_rate') is-invalid @enderror" wire:model="contract_base_rate">
                                    @error('contract_base_rate') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fs--1 fw-semi-bold">Contract Credit Limit (PKR)</label>
                                    <input type="number" step="0.01" class="form-control form-control-sm" wire:model="contract_credit_limit">
                                </div>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-md-3">
                                    <label class="form-label fs--1 fw-semi-bold">Loading Charges (PKR)</label>
                                    <input type="number" step="0.01" class="form-control form-control-sm" wire:model="contract_loading_charges">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs--1 fw-semi-bold">Unloading Charges (PKR)</label>
                                    <input type="number" step="0.01" class="form-control form-control-sm" wire:model="contract_unloading_charges">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs--1 fw-semi-bold">Detention / Day (PKR)</label>
                                    <input type="number" step="0.01" class="form-control form-control-sm" wire:model="contract_detention_rate">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs--1 fw-semi-bold">Contract Discount (%)</label>
                                    <input type="number" step="0.01" class="form-control form-control-sm" wire:model="contract_discount_percentage">
                                </div>
                            </div>

                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label fs--1 fw-semi-bold">Contract Start Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control form-control-sm @error('contract_start_date') is-invalid @enderror" wire:model="contract_start_date">
                                    @error('contract_start_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fs--1 fw-semi-bold">Contract Expiry Date</label>
                                    <input type="date" class="form-control form-control-sm" wire:model="contract_end_date">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light py-2">
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="$set('showContractModal', false)">Cancel</button>
                            <button type="submit" class="btn btn-success btn-sm text-white">Establish Agreement</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- CUSTOMER DOSSIER MODAL -->
    @if($showDetailModal && $viewingCustomer)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);" aria-modal="true" role="dialog">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-light py-2">
                        <h6 class="modal-title text-primary fw-bold">
                            <i class="fas fa-user-circle me-1"></i> Customer Account Dossier: {{ $viewingCustomer->name }}
                        </h6>
                        <button type="button" class="btn-close" wire:click="$set('showDetailModal', false)"></button>
                    </div>
                    <div class="modal-body p-3">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <div class="card h-100 bg-light border-0">
                                    <div class="card-body p-3">
                                        <h6 class="fw-bold fs--1 text-uppercase text-700 mb-2">Corporate Profile</h6>
                                        <div class="fs--1 mb-1"><strong>Account Code:</strong> {{ $viewingCustomer->customer_code }}</div>
                                        <div class="fs--1 mb-1"><strong>Legal Name:</strong> {{ $viewingCustomer->company_name ?: $viewingCustomer->name }}</div>
                                        <div class="fs--1 mb-1"><strong>Type:</strong> <span class="badge bg-soft-info text-info">{{ ucfirst($viewingCustomer->customer_type) }}</span></div>
                                        <div class="fs--1 mb-1"><strong>Contact Person:</strong> {{ $viewingCustomer->contact_person }} ({{ $viewingCustomer->designation }})</div>
                                        <div class="fs--1 mb-1"><strong>Phone:</strong> {{ $viewingCustomer->phone }}</div>
                                        <div class="fs--1 mb-1"><strong>Email:</strong> {{ $viewingCustomer->email ?: 'N/A' }}</div>
                                        <div class="fs--1"><strong>Billing Address:</strong> {{ $viewingCustomer->billing_address }}, {{ $viewingCustomer->billing_city }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100 bg-light border-0">
                                    <div class="card-body p-3">
                                        <h6 class="fw-bold fs--1 text-uppercase text-700 mb-2">Commercial Credit Governance</h6>
                                        <div class="fs--1 mb-1"><strong>FBR NTN:</strong> {{ $viewingCustomer->tax_ntn ?: 'Non-NTN' }}</div>
                                        <div class="fs--1 mb-1"><strong>Tax Status:</strong> {{ $viewingCustomer->is_tax_exempt ? 'Tax Exempt' : 'Taxable' }}</div>
                                        <div class="fs--1 mb-1"><strong>Credit Limit:</strong> PKR {{ number_format($viewingCustomer->credit_limit, 2) }}</div>
                                        <div class="fs--1 mb-1"><strong>Credit Days:</strong> {{ $viewingCustomer->credit_days }} Days</div>
                                        <div class="fs--1 mb-1"><strong>Current Balance:</strong> <span class="fw-bold text-danger">PKR {{ number_format($viewingCustomer->current_balance, 2) }}</span></div>
                                        <div class="fs--1"><strong>Status:</strong> <span class="badge bg-soft-success text-success">{{ ucfirst($viewingCustomer->status) }}</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Active Contracts Table -->
                        <h6 class="fw-bold fs--1 text-uppercase text-700 mb-2">Agreed Contracts & Rate Tariffs</h6>
                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-bordered fs--2 mb-0">
                                <thead class="bg-200">
                                    <tr>
                                        <th>Contract #</th>
                                        <th>Title</th>
                                        <th>Rate Type</th>
                                        <th>Base Rate</th>
                                        <th>Validity</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($viewingCustomer->contracts as $cnt)
                                        <tr>
                                            <td class="fw-bold text-primary">{{ $cnt->contract_number }}</td>
                                            <td>{{ $cnt->contract_title }}</td>
                                            <td>{{ ucfirst(str_replace('_', ' ', $cnt->rate_type)) }}</td>
                                            <td class="text-end fw-semi-bold">PKR {{ number_format($cnt->base_rate, 2) }}</td>
                                            <td>{{ $cnt->start_date->format('d M Y') }} to {{ $cnt->end_date?->format('d M Y') ?: 'Open' }}</td>
                                            <td><span class="badge bg-soft-success text-success">{{ ucfirst($cnt->status) }}</span></td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-2 text-muted">No contracts established for this customer.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Delivery Shipping Points Table -->
                        <h6 class="fw-bold fs--1 text-uppercase text-700 mb-2">Registered Delivery / Shipping Points</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered fs--2 mb-0">
                                <thead class="bg-200">
                                    <tr>
                                        <th>Location Title</th>
                                        <th>City</th>
                                        <th>Full Address</th>
                                        <th>Contact Person & Phone</th>
                                        <th>Default</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($viewingCustomer->deliveryAddresses as $addr)
                                        <tr>
                                            <td class="fw-bold">{{ $addr->location_title }}</td>
                                            <td>{{ $addr->city }}</td>
                                            <td>{{ $addr->address }}</td>
                                            <td>{{ $addr->contact_person }} ({{ $addr->contact_phone }})</td>
                                            <td class="text-center">
                                                @if($addr->is_default)
                                                    <span class="badge bg-success">Default</span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center py-2 text-muted">No delivery addresses registered.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="$set('showDetailModal', false)">Close</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>


<div>
    <!-- Progress Header -->
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body p-4">
            <div class="row align-items-center mb-3">
                <div class="col">
                    <span class="badge bg-soft-primary text-primary px-3 py-1 fs--1 mb-2">Transport Enterprise Onboarding</span>
                    <h3 class="fw-bold mb-0 text-900">Welcome to TMS, {{ $company->name }}!</h3>
                    <p class="text-600 fs--1 mb-0">Follow our quick 6-step guided wizard to configure your organizational foundation, branches, and operational standards.</p>
                </div>
                <div class="col-auto text-end">
                    <div class="fs-2 fw-bolder text-primary">{{ $progress }}%</div>
                    <div class="fs--2 text-muted text-uppercase fw-bold">Completed</div>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="progress" style="height: 10px;">
                <div class="progress-bar bg-primary progress-bar-striped progress-bar-animated" role="progressbar" style="width: {{ $progress }}%;" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100"></div>
            </div>

            <!-- Step Pills -->
            <div class="row g-2 mt-3 text-center fs--1">
                @php
                    $stepLabels = [
                        1 => 'Company Profile',
                        2 => 'Branch Network',
                        3 => 'Fiscal Year',
                        4 => 'Master Lookups',
                        5 => 'Key Staff',
                        6 => 'Initial Fleet',
                    ];
                @endphp

                @foreach($stepLabels as $num => $lbl)
                    <div class="col-4 col-md-2">
                        <div class="p-2 rounded {{ $currentStep === $num ? 'bg-soft-primary text-primary fw-bold border border-primary' : (in_array($num, $completedSteps) ? 'bg-soft-success text-success fw-semi-bold' : 'bg-light text-500') }}">
                            <div class="fs--2">Step {{ $num }}</div>
                            <div class="text-truncate">{{ $lbl }}</div>
                            @if(in_array($num, $completedSteps))
                                <i class="fas fa-check-circle mt-1"></i>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Step Content Cards -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            {{-- STEP 1: Company Profile --}}
            @if($currentStep === 1)
                <div>
                    <h5 class="fw-bold text-primary mb-1"><i class="fas fa-building me-2"></i> Step 1: Legal Identity & Operating Profile</h5>
                    <p class="text-muted fs--1 mb-4">Confirm your transport enterprise legal identity, tax NTN registration, and local currency.</p>

                    <form wire:submit.prevent="completeStep1">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">Legal Entity Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('company_name') is-invalid @enderror" wire:model="company_name">
                                @error('company_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">Trading / Brand Name</label>
                                <input type="text" class="form-control" wire:model="trading_name">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fs--1 fw-semi-bold">Tax NTN / STRN #</label>
                                <input type="text" class="form-control" wire:model="tax_number" placeholder="e.g. 4018294-1">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fs--1 fw-semi-bold">SECP / Registration #</label>
                                <input type="text" class="form-control" wire:model="registration_number" placeholder="e.g. 0094821">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fs--1 fw-semi-bold">Operating Currency <span class="text-danger">*</span></label>
                                <select class="form-select" wire:model="currency">
                                    <option value="PKR">PKR - Pakistani Rupee</option>
                                    <option value="USD">USD - US Dollar</option>
                                    <option value="AED">AED - UAE Dirham</option>
                                    <option value="SAR">SAR - Saudi Riyal</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">Official Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control @error('email') is-invalid @enderror" wire:model="email">
                                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">Official Phone <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('phone') is-invalid @enderror" wire:model="phone">
                                @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label fs--1 fw-semi-bold">Headquarters Address</label>
                                <textarea class="form-control" wire:model="address" rows="2"></textarea>
                            </div>

                            <div class="col-12 text-end mt-4">
                                <button type="submit" class="btn btn-primary px-4 shadow-sm">
                                    Continue to Branch Setup <i class="fas fa-arrow-right ms-1"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            @endif

            {{-- STEP 2: Branch Setup --}}
            @if($currentStep === 2)
                <div>
                    <h5 class="fw-bold text-primary mb-1"><i class="fas fa-code-branch me-2"></i> Step 2: Primary Branch & Dispatch Hub</h5>
                    <p class="text-muted fs--1 mb-4">Set up or verify your initial primary head office branch and local terminal.</p>

                    <form wire:submit.prevent="completeStep2">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label fs--1 fw-semi-bold">Terminal / Branch Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('branch_name') is-invalid @enderror" wire:model="branch_name">
                                @error('branch_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fs--1 fw-semi-bold">Branch Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('branch_code') is-invalid @enderror" wire:model="branch_code">
                                @error('branch_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">City / District <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('branch_city') is-invalid @enderror" wire:model="branch_city">
                                @error('branch_city') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">Terminal Phone</label>
                                <input type="text" class="form-control" wire:model="branch_phone">
                            </div>

                            <div class="col-12 d-flex justify-content-between mt-4">
                                <button type="button" class="btn btn-outline-secondary" wire:click="$set('currentStep', 1)">
                                    <i class="fas fa-arrow-left me-1"></i> Back
                                </button>
                                <button type="submit" class="btn btn-primary px-4 shadow-sm">
                                    Continue to Fiscal Year <i class="fas fa-arrow-right ms-1"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            @endif

            {{-- STEP 3: Financial Configuration --}}
            @if($currentStep === 3)
                <div>
                    <h5 class="fw-bold text-primary mb-1"><i class="fas fa-calendar-alt me-2"></i> Step 3: Fiscal Calendar & Accounting Periods</h5>
                    <p class="text-muted fs--1 mb-4">Define your first financial year. TMS will automatically generate 12 monthly accounting periods for period locking.</p>

                    <form wire:submit.prevent="completeStep3">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">Fiscal Year Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('fy_name') is-invalid @enderror" wire:model="fy_name" placeholder="e.g. FY 2026-2027">
                                @error('fy_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">Decimal Precision</label>
                                <select class="form-select" wire:model="currency_precision">
                                    <option value="0">0 decimals (Whole currency units)</option>
                                    <option value="2">2 decimals (Standard e.g. 1,000.00)</option>
                                    <option value="3">3 decimals</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">Fiscal Start Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control @error('fy_start_date') is-invalid @enderror" wire:model="fy_start_date">
                                @error('fy_start_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">Fiscal End Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control @error('fy_end_date') is-invalid @enderror" wire:model="fy_end_date">
                                @error('fy_end_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12 d-flex justify-content-between mt-4">
                                <button type="button" class="btn btn-outline-secondary" wire:click="$set('currentStep', 2)">
                                    <i class="fas fa-arrow-left me-1"></i> Back
                                </button>
                                <button type="submit" class="btn btn-primary px-4 shadow-sm">
                                    Continue to Master Lookups <i class="fas fa-arrow-right ms-1"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            @endif

            {{-- STEP 4: Master Data Verification --}}
            @if($currentStep === 4)
                <div>
                    <h5 class="fw-bold text-primary mb-1"><i class="fas fa-database me-2"></i> Step 4: Standard Transport Lookups & Sequences</h5>
                    <p class="text-muted fs--1 mb-4">Initialize pre-configured industry masters (truck types, freight measurement units, fuel grades, document prefixes) for your transport company.</p>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="border rounded p-3 bg-light text-center h-100">
                                <i class="fas fa-truck text-primary fs-3 mb-2"></i>
                                <div class="fw-bold fs--1">12 Vehicle & Truck Types</div>
                                <div class="fs--2 text-muted">22W Trailers, 10W Flatbeds, Reefers</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3 bg-light text-center h-100">
                                <i class="fas fa-balance-scale text-success fs-3 mb-2"></i>
                                <div class="fw-bold fs--1">Freight Measurement Units</div>
                                <div class="fs--2 text-muted">MT, KG, 50Kg Bags, Bales, Cartons</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3 bg-light text-center h-100">
                                <i class="fas fa-receipt text-warning fs-3 mb-2"></i>
                                <div class="fw-bold fs--1">11 Document Sequences</div>
                                <div class="fs--2 text-muted">Bilties, Trips, Invoices, Vouchers</div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-outline-secondary" wire:click="$set('currentStep', 3)">
                            <i class="fas fa-arrow-left me-1"></i> Back
                        </button>
                        <button type="button" class="btn btn-primary px-4 shadow-sm" wire:click="completeStep4">
                            Activate Standard Masters <i class="fas fa-arrow-right ms-1"></i>
                        </button>
                    </div>
                </div>
            @endif

            {{-- STEP 5: Staff Setup --}}
            @if($currentStep === 5)
                <div>
                    <h5 class="fw-bold text-primary mb-1"><i class="fas fa-users-cog me-2"></i> Step 5: Key Operations Team (Optional)</h5>
                    <p class="text-muted fs--1 mb-4">Optionally create your first booking clerk or dispatcher now, or skip to add users later.</p>

                    <form wire:submit.prevent="completeStep5">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">Staff Member Full Name</label>
                                <input type="text" class="form-control" wire:model="staff_name" placeholder="e.g. Asif Ali (Dispatcher)">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">Staff Email (Login ID)</label>
                                <input type="email" class="form-control @error('staff_email') is-invalid @enderror" wire:model="staff_email" placeholder="dispatcher@yourcompany.com">
                                @error('staff_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">Temporary Password</label>
                                <input type="password" class="form-control @error('staff_password') is-invalid @enderror" wire:model="staff_password" placeholder="Min. 8 characters">
                                @error('staff_password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">System Role</label>
                                <select class="form-select" wire:model="staff_role">
                                    <option value="dispatcher">Fleet Dispatcher</option>
                                    <option value="booking_clerk">Booking Clerk</option>
                                    <option value="accountant">Financial Accountant</option>
                                </select>
                            </div>

                            <div class="col-12 d-flex justify-content-between mt-4">
                                <button type="button" class="btn btn-outline-secondary" wire:click="$set('currentStep', 4)">
                                    <i class="fas fa-arrow-left me-1"></i> Back
                                </button>
                                <div>
                                    <button type="button" class="btn btn-link text-600 me-2" wire:click="completeStep5">Skip this step</button>
                                    <button type="submit" class="btn btn-primary px-4 shadow-sm">
                                        Continue to Fleet Setup <i class="fas fa-arrow-right ms-1"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            @endif

            {{-- STEP 6: Initial Fleet Setup --}}
            @if($currentStep === 6)
                <div>
                    <h5 class="fw-bold text-primary mb-1"><i class="fas fa-truck-moving me-2"></i> Step 6: Initial Fleet Profile & Launch</h5>
                    <p class="text-muted fs--1 mb-4">Optionally log your first company vehicle or primary truck type to wrap up configuration.</p>

                    <form wire:submit.prevent="completeStep6">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">Vehicle Registration Plate (Optional)</label>
                                <input type="text" class="form-control" wire:model="vehicle_registration" placeholder="e.g. LES-19-4820">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">Vehicle Configuration</label>
                                <select class="form-select" wire:model="vehicle_type">
                                    <option value="TRAILER_22W">22-Wheeler Articulated Trailer (50 Ton)</option>
                                    <option value="FLATBED_10W">10-Wheeler Rigid Flatbed (30 Ton)</option>
                                    <option value="BOX_6W">6-Wheeler Enclosed Box Truck (15 Ton)</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">Ownership Category</label>
                                <select class="form-select" wire:model="ownership_type">
                                    <option value="company_owned">Company Owned Fleet Truck</option>
                                    <option value="market_attached">Market / Hired Third-Party Truck</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">Payload Capacity (Metric Tons)</label>
                                <input type="number" step="0.5" class="form-control" wire:model="payload_capacity">
                            </div>

                            <div class="col-12"><hr class="my-3"></div>

                            <div class="col-12">
                                <div class="card bg-soft-success border-success">
                                    <div class="card-body p-3">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-check-circle fs-3 text-success me-3"></i>
                                            <div>
                                                <h6 class="mb-0 fw-bold text-success">Ready for Operational Deployment!</h6>
                                                <div class="fs--1 text-700">Clicking 'Complete Setup' will finalize your onboarding checklist and activate full access to all operational freight modules.</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 d-flex justify-content-between mt-4">
                                <button type="button" class="btn btn-outline-secondary" wire:click="$set('currentStep', 5)">
                                    <i class="fas fa-arrow-left me-1"></i> Back
                                </button>
                                <button type="submit" class="btn btn-success btn-lg px-5 shadow-sm fw-bold">
                                    <i class="fas fa-rocket me-2"></i> Complete Setup & Launch TMS
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>
</div>

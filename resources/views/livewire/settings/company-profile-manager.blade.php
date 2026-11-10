<div>
    @if(session()->has('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <form wire:submit.prevent="save">
        <div class="row g-3">
            <!-- Left Column: Legal Identity & Branding -->
            <div class="col-lg-7">
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-light">
                        <h6 class="mb-0 fw-bold"><i class="fas fa-building me-1 text-primary"></i> Legal Identity & Company Details</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">Legal Entity Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm @error('name') is-invalid @enderror" wire:model="name" placeholder="e.g. Falcon Express Cargo Logistics Ltd">
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">Trading / Brand Name</label>
                                <input type="text" class="form-control form-control-sm @error('trading_name') is-invalid @enderror" wire:model="trading_name" placeholder="e.g. Falcon Express Cargo">
                                @error('trading_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fs--1 fw-semi-bold">Company Code (Tenant Prefix)</label>
                                <input type="text" class="form-control form-control-sm bg-200" value="{{ $code }}" disabled readonly>
                                <div class="fs--2 text-muted">Fixed tenant identifier</div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fs--1 fw-semi-bold">Registration / SECP #</label>
                                <input type="text" class="form-control form-control-sm @error('registration_number') is-invalid @enderror" wire:model="registration_number" placeholder="e.g. 0094821">
                                @error('registration_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fs--1 fw-semi-bold">Tax NTN / STRN #</label>
                                <input type="text" class="form-control form-control-sm @error('tax_number') is-invalid @enderror" wire:model="tax_number" placeholder="e.g. 4018294-1">
                                @error('tax_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">Official Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control form-control-sm @error('email') is-invalid @enderror" wire:model="email">
                                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">Primary Phone <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm @error('phone') is-invalid @enderror" wire:model="phone" placeholder="+92-42-35889900">
                                @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">Official Website</label>
                                <input type="url" class="form-control form-control-sm @error('website') is-invalid @enderror" wire:model="website" placeholder="https://falconexpress.com">
                                @error('website') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">Country <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm @error('country') is-invalid @enderror" wire:model="country">
                                @error('country') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label fs--1 fw-semi-bold">Headquarters Address</label>
                                <textarea class="form-control form-control-sm @error('address') is-invalid @enderror" wire:model="address" rows="2"></textarea>
                                @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Regional & Financial Localization -->
            <div class="col-lg-5">
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-light">
                        <h6 class="mb-0 fw-bold"><i class="fas fa-globe-asia me-1 text-success"></i> Regional & Financial Settings</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">Operating Currency <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm" wire:model="currency">
                                    <option value="PKR">PKR - Pakistani Rupee</option>
                                    <option value="USD">USD - US Dollar</option>
                                    <option value="AED">AED - UAE Dirham</option>
                                    <option value="SAR">SAR - Saudi Riyal</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">Currency Precision</label>
                                <select class="form-select form-select-sm" wire:model="currency_precision">
                                    <option value="0">0 (e.g. Rs 1,500)</option>
                                    <option value="2">2 (e.g. Rs 1,500.00)</option>
                                    <option value="3">3 (e.g. Rs 1,500.000)</option>
                                </select>
                                <div class="fs--2 text-muted">Decimal positions for freight pricing</div>
                            </div>

                            <div class="col-12">
                                <label class="form-label fs--1 fw-semi-bold">System Timezone <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm" wire:model="timezone">
                                    <option value="Asia/Karachi">Asia/Karachi (PKT +05:00)</option>
                                    <option value="Asia/Dubai">Asia/Dubai (GST +04:00)</option>
                                    <option value="Asia/Riyadh">Asia/Riyadh (AST +03:00)</option>
                                    <option value="UTC">UTC (Coordinated Universal Time)</option>
                                </select>
                            </div>

                            <div class="col-12"><hr class="my-2"></div>

                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="isTaxEnabled" wire:model="is_tax_enabled">
                                    <label class="form-check-label fw-semi-bold fs--1" for="isTaxEnabled">Enable Sales Tax / VAT on Freight Invoicing</label>
                                </div>
                            </div>

                            @if($is_tax_enabled)
                            <div class="col-md-7">
                                <label class="form-label fs--1 fw-semi-bold">Tax Title</label>
                                <input type="text" class="form-control form-control-sm" wire:model="tax_name" placeholder="e.g. PRA / Sindh Sales Tax / GST">
                            </div>

                            <div class="col-md-5">
                                <label class="form-label fs--1 fw-semi-bold">Default Rate (%)</label>
                                <input type="number" step="0.01" class="form-control form-control-sm" wire:model="default_tax_rate">
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary shadow-sm">
                        <i class="fas fa-save me-1"></i> Save Configuration Changes
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<div>
    <!-- Top Action Bar -->
    <div class="card mb-3 shadow-sm border">
        <div class="card-body p-3 d-flex justify-content-between align-items-center">
            <div>
                <h6 class="mb-0 fw-bold">Configured Subscription Plans</h6>
                <div class="fs--1 text-muted">Active tiers available to onboarded transport companies.</div>
            </div>
            <button class="btn btn-primary btn-sm" wire:click="openCreateModal">
                <i class="fas fa-plus-circle me-1"></i> Create Subscription Plan
            </button>
        </div>
    </div>

    <!-- Plans Cards Grid -->
    <div class="row g-3">
        @forelse($plans as $plan)
        <div class="col-md-6 col-xl-4">
            <div class="card h-100 shadow-sm border {{ $plan->is_active ? '' : 'bg-50 opacity-75' }}">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0 fw-bold text-primary">{{ $plan->name }}</h5>
                        <div class="fs--2 text-muted">Slug: <code>{{ $plan->slug }}</code></div>
                    </div>
                    @if($plan->is_active)
                        <span class="badge bg-soft-success text-success">Active</span>
                    @else
                        <span class="badge bg-soft-danger text-danger">Deactivated</span>
                    @endif
                </div>
                <div class="card-body">
                    <p class="fs--1 text-600 mb-3">{{ $plan->description ?? 'No description provided.' }}</p>

                    <!-- Pricing Matrix -->
                    <div class="bg-100 p-2 rounded mb-3">
                        <div class="d-flex justify-content-between fs--1 mb-1">
                            <span>Monthly Price:</span>
                            <strong class="text-primary">PKR {{ number_format($plan->monthly_price, 2) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between fs--1 mb-1">
                            <span>Quarterly Price:</span>
                            <strong>PKR {{ number_format($plan->quarterly_price, 2) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between fs--1">
                            <span>Annual Price:</span>
                            <strong>PKR {{ number_format($plan->annual_price, 2) }}</strong>
                        </div>
                    </div>

                    <!-- Quota Limits -->
                    <ul class="list-unstyled fs--1 mb-3">
                        <li class="mb-1"><i class="fas fa-truck text-primary me-2"></i> Max Vehicles: <strong>{{ $plan->max_vehicles === -1 ? 'Unlimited' : $plan->max_vehicles }}</strong></li>
                        <li class="mb-1"><i class="fas fa-users text-primary me-2"></i> Staff Users: <strong>{{ $plan->max_users === -1 ? 'Unlimited' : $plan->max_users }}</strong></li>
                        <li class="mb-1"><i class="fas fa-code-branch text-primary me-2"></i> Operational Hubs: <strong>{{ $plan->max_branches === -1 ? 'Unlimited' : $plan->max_branches }}</strong></li>
                        <li class="mb-1"><i class="fas fa-hdd text-primary me-2"></i> Storage Quota: <strong>{{ $plan->max_storage_mb === -1 ? 'Unlimited' : round($plan->max_storage_mb / 1024, 1) . ' GB' }}</strong></li>
                        <li><i class="fas fa-hourglass-start text-primary me-2"></i> Free Trial: <strong>{{ $plan->trial_duration_days }} Days</strong></li>
                    </ul>

                    <!-- Included Features -->
                    <div class="border-top pt-2">
                        <div class="fs--2 text-uppercase fw-bold text-600 mb-1">Bundled Features ({{ count($plan->features ?? []) }}/{{ count($allFeatures) }}):</div>
                        <div class="d-flex flex-wrap gap-1">
                            @foreach($allFeatures as $slug => $label)
                                @if($plan->hasFeature($slug))
                                    <span class="badge bg-soft-primary text-primary fs--2">{{ $label }}</span>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-light d-flex justify-content-between align-items-center">
                    <span class="fs--2 text-muted">{{ $plan->companies_count }} active companies on plan</span>
                    <div>
                        <button class="btn btn-outline-primary btn-sm py-0 px-2" wire:click="editPlan({{ $plan->id }})">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn {{ $plan->is_active ? 'btn-outline-danger' : 'btn-outline-success' }} btn-sm py-0 px-2 ms-1" wire:click="togglePlanStatus({{ $plan->id }})">
                            {{ $plan->is_active ? 'Deactivate' : 'Activate' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="col-12 text-center py-5">
            <h5 class="text-500">No subscription plans found. Create the first plan to begin tenant onboarding.</h5>
        </div>
        @endforelse
    </div>

    <!-- Create / Edit Plan Modal -->
    @if($showModal)
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.6);">
        <div class="modal-dialog modal-lg">
            <div class="modal-content shadow-lg border-0">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold">
                        <i class="fas {{ $isEditing ? 'fa-edit' : 'fa-plus-circle' }} me-2 text-primary"></i>
                        {{ $isEditing ? 'Edit Subscription Plan' : 'Create New Subscription Plan' }}
                    </h5>
                    <button type="button" class="btn-close" wire:click="$set('showModal', false)"></button>
                </div>
                <form wire:submit.prevent="savePlan">
                    <div class="modal-body">
                        <!-- Basic Info -->
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fs--1">Plan Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm @error('name') is-invalid @enderror" wire:model="name" placeholder="e.g. Enterprise Tier">
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fs--1">Slug <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm @error('slug') is-invalid @enderror" wire:model="slug" placeholder="e.g. enterprise">
                                @error('slug') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fs--1">Description</label>
                                <textarea class="form-control form-control-sm" wire:model="description" rows="2" placeholder="Brief summary of target audience and inclusions"></textarea>
                            </div>
                        </div>

                        <!-- Pricing Slabs -->
                        <h6 class="fw-bold text-primary mb-2">Pricing Slabs (PKR)</h6>
                        <div class="row g-2 mb-3">
                            <div class="col-md-4">
                                <label class="form-label fs--1">Monthly Price (PKR) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control form-control-sm @error('monthly_price') is-invalid @enderror" wire:model="monthly_price">
                                @error('monthly_price') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fs--1">Quarterly Price (PKR) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control form-control-sm @error('quarterly_price') is-invalid @enderror" wire:model="quarterly_price">
                                @error('quarterly_price') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fs--1">Annual Price (PKR) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control form-control-sm @error('annual_price') is-invalid @enderror" wire:model="annual_price">
                                @error('annual_price') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <!-- Quota Limits -->
                        <h6 class="fw-bold text-primary mb-2">Resource Quotas (-1 for Unlimited)</h6>
                        <div class="row g-2 mb-3">
                            <div class="col-md-3">
                                <label class="form-label fs--1">Max Vehicles <span class="text-danger">*</span></label>
                                <input type="number" class="form-control form-control-sm" wire:model="max_vehicles">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fs--1">Max Users <span class="text-danger">*</span></label>
                                <input type="number" class="form-control form-control-sm" wire:model="max_users">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fs--1">Max Hubs / Branches <span class="text-danger">*</span></label>
                                <input type="number" class="form-control form-control-sm" wire:model="max_branches">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fs--1">Trial Duration (Days)</label>
                                <input type="number" class="form-control form-control-sm" wire:model="trial_duration_days">
                            </div>
                        </div>

                        <!-- Features Selection Checklist -->
                        <h6 class="fw-bold text-primary mb-2">Included Modules & Features</h6>
                        <div class="border rounded p-3 bg-50">
                            <div class="row g-2">
                                @foreach($allFeatures as $slug => $label)
                                <div class="col-md-6 col-lg-4">
                                    <div class="form-check fs--1">
                                        <input class="form-check-input" type="checkbox" value="{{ $slug }}" id="feat_{{ $slug }}" wire:model="features">
                                        <label class="form-check-label" for="feat_{{ $slug }}">{{ $label }}</label>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="$set('showModal', false)">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm px-4">
                            <i class="fas fa-save me-1"></i> Save Plan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>


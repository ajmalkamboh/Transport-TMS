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

    <div class="row g-3">
        <!-- Category Sidebar Tabs -->
        <div class="col-lg-3">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-light py-2">
                    <h6 class="mb-0 fw-bold fs--1 text-uppercase text-600"><i class="fas fa-layer-group me-1"></i> Master Categories</h6>
                </div>
                <div class="list-group list-group-flush fs--1">
                    @foreach($categories as $catKey => $catLabel)
                        <button type="button" wire:click="selectCategory('{{ $catKey }}')" class="list-group-item list-group-item-action text-start d-flex justify-content-between align-items-center {{ $selectedCategory === $catKey ? 'active' : '' }}">
                            <span>{{ $catLabel }}</span>
                            <span class="fas fa-chevron-right fs--2 opacity-75"></span>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Master Items List -->
        <div class="col-lg-9">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0 fw-bold text-primary">
                            <i class="fas fa-list-ul me-1"></i> {{ $categories[$selectedCategory] }}
                        </h6>
                        <div class="fs--2 text-muted">Configured operational lookup standards for {{ $company->name }}</div>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm shadow-sm" wire:click="openCreateModal">
                        <i class="fas fa-plus-circle me-1"></i> Add Custom Lookup
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped fs--1 mb-0 align-middle">
                            <thead class="bg-200 text-900">
                                <tr>
                                    <th>Code</th>
                                    <th>Display Name & Description</th>
                                    <th>Symbol / Metadata</th>
                                    <th>Scope / Origin</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($items as $item)
                                    <tr>
                                        <td>
                                            <span class="badge bg-light text-dark font-monospace border">{{ $item->code }}</span>
                                        </td>
                                        <td>
                                            <div class="fw-bold">{{ $item->name }}</div>
                                            @if($item->description)
                                                <div class="fs--2 text-muted">{{ $item->description }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            @if($item->meta && isset($item->meta['symbol']))
                                                <span class="badge bg-soft-info text-info">{{ $item->meta['symbol'] }}</span>
                                            @else
                                                <span class="text-muted fs--2">&mdash;</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($item->is_system)
                                                <span class="badge bg-soft-secondary text-secondary"><i class="fas fa-globe me-1"></i> System Standard</span>
                                            @else
                                                <span class="badge bg-soft-primary text-primary"><i class="fas fa-building me-1"></i> Tenant Custom</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($item->is_active)
                                                <span class="badge bg-soft-success text-success">Active</span>
                                            @else
                                                <span class="badge bg-soft-danger text-danger">Inactive</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @if(!$item->is_system)
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-{{ $item->is_active ? 'warning' : 'success' }} btn-sm py-0 px-2 fs--2" wire:click="toggleItemStatus({{ $item->id }})">
                                                        <i class="fas fa-{{ $item->is_active ? 'ban' : 'check' }}"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 fs--2" wire:click="deleteCustomItem({{ $item->id }})" onclick="return confirm('Delete this custom lookup item?')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            @else
                                                <span class="fs--2 text-muted" title="System standards cannot be edited"><i class="fas fa-lock"></i></span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">No lookups configured under this category.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Custom Item Modal -->
    @if($showCreateModal)
    <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-plus-circle me-1 text-primary"></i> Add Custom {{ $categories[$selectedCategory] }} Item
                    </h5>
                    <button type="button" class="btn-close" wire:click="$set('showCreateModal', false)"></button>
                </div>
                <form wire:submit.prevent="saveCustomItem">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label fs--1 fw-semi-bold">Unique Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm @error('code') is-invalid @enderror" wire:model="code" placeholder="e.g. CNG_CYLINDER">
                                @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-7">
                                <label class="form-label fs--1 fw-semi-bold">Display Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm @error('name') is-invalid @enderror" wire:model="name" placeholder="e.g. CNG Cylinder Truck">
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fs--1 fw-semi-bold">Unit Symbol / Short Tag (Optional)</label>
                                <input type="text" class="form-control form-control-sm" wire:model="meta_symbol" placeholder="e.g. Cyl">
                            </div>

                            <div class="col-12">
                                <label class="form-label fs--1 fw-semi-bold">Description (Optional)</label>
                                <textarea class="form-control form-control-sm" wire:model="description" rows="2" placeholder="Operational use and specifications"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="$set('showCreateModal', false)">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-save me-1"></i> Save Custom Lookup
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>


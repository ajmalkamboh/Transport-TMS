<?php

namespace App\Livewire\Fleet;

use App\Models\Company;
use App\Models\OwnerContract;
use App\Models\VehicleOwner;
use App\Services\AuditLogService;
use App\Services\DocumentSequenceService;
use App\Services\TenantAuthorizationService;
use App\Services\TenantContextService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class VehicleOwnerManager extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';

    // Owner Modal
    public bool $showOwnerModal = false;
    public bool $isEditingOwner = false;
    public ?int $selectedOwnerId = null;

    public string $name = '';
    public string $company_name = '';
    public string $cnic_number = '';
    public string $phone = '';
    public string $secondary_phone = '';
    public string $email = '';
    public string $address = '';
    public string $city = '';
    public string $bank_name = '';
    public string $bank_account_title = '';
    public string $bank_account_number = '';
    public string $bank_iban = '';
    public string $tax_number = '';
    public string $status = 'active';
    public string $notes = '';

    // Contract Modal
    public bool $showContractModal = false;
    public ?VehicleOwner $contractOwner = null;
    public string $contract_number = '';
    public string $payment_model = OwnerContract::MODEL_FIXED_PER_TRIP;
    public float $rate_amount = 0.00;
    public float $freight_percentage = 85.00;
    public float $commission_percentage = 15.00;
    public bool $deduct_fuel = true;
    public bool $deduct_toll_taxes = false;
    public bool $deduct_advances = true;
    public string $route_origin = '';
    public string $route_destination = '';
    public string $start_date = '';
    public ?string $end_date = null;
    public string $contract_status = 'active';
    public string $terms_and_conditions = '';

    // Owner Detail Modal
    public bool $showDetailModal = false;
    public ?VehicleOwner $viewingOwner = null;

    protected function checkAuthorization(): void
    {
        if (!auth()->check()) {
            throw new AuthorizationException('Authentication required.');
        }

        $user = auth()->user();
        if (!$user->isSuperAdmin() && !$user->isTenantAdmin() && !$user->hasPermission('view_fleet') && !$user->hasPermission('manage_fleet')) {
            throw new AuthorizationException('Access denied: You do not have permission to manage vehicle owners.');
        }
    }

    public function render()
    {
        $this->checkAuthorization();

        $companyId = app(TenantContextService::class)->getCompanyId() ?? auth()->user()->company_id;

        $owners = VehicleOwner::where('company_id', $companyId)
            ->with(['vehicles', 'contracts'])
            ->withCount(['vehicles', 'contracts'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('company_name', 'like', '%' . $this->search . '%')
                      ->orWhere('phone', 'like', '%' . $this->search . '%')
                      ->orWhere('cnic_number', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->latest()
            ->paginate(10);

        return view('livewire.fleet.vehicle-owner-manager', [
            'owners' => $owners,
            'paymentModels' => OwnerContract::ALL_MODELS,
        ]);
    }

    public function openCreateOwnerModal(): void
    {
        $this->checkAuthorization();
        $this->resetOwnerForm();
        $this->isEditingOwner = false;
        $this->showOwnerModal = true;
    }

    public function resetOwnerForm(): void
    {
        $this->selectedOwnerId = null;
        $this->name = '';
        $this->company_name = '';
        $this->cnic_number = '';
        $this->phone = '';
        $this->secondary_phone = '';
        $this->email = '';
        $this->address = '';
        $this->city = '';
        $this->bank_name = '';
        $this->bank_account_title = '';
        $this->bank_account_number = '';
        $this->bank_iban = '';
        $this->tax_number = '';
        $this->status = 'active';
        $this->notes = '';
        $this->resetErrorBag();
    }

    public function editOwner(int $ownerId): void
    {
        $this->checkAuthorization();

        /** @var VehicleOwner $owner */
        $owner = VehicleOwner::withoutGlobalScopes()->findOrFail($ownerId);
        app(TenantAuthorizationService::class)->validateTenantOwnership($owner);

        $this->selectedOwnerId = $owner->id;
        $this->name = $owner->name;
        $this->company_name = $owner->company_name ?? '';
        $this->cnic_number = $owner->cnic_number ?? '';
        $this->phone = $owner->phone;
        $this->secondary_phone = $owner->secondary_phone ?? '';
        $this->email = $owner->email ?? '';
        $this->address = $owner->address ?? '';
        $this->city = $owner->city ?? '';
        $this->bank_name = $owner->bank_name ?? '';
        $this->bank_account_title = $owner->bank_account_title ?? '';
        $this->bank_account_number = $owner->bank_account_number ?? '';
        $this->bank_iban = $owner->bank_iban ?? '';
        $this->tax_number = $owner->tax_number ?? '';
        $this->status = $owner->status;
        $this->notes = $owner->notes ?? '';

        $this->isEditingOwner = true;
        $this->showOwnerModal = true;
    }

    public function saveOwner(): void
    {
        $this->checkAuthorization();
        $companyId = app(TenantContextService::class)->getCompanyId() ?? auth()->user()->company_id;

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:191'],
            'company_name' => ['nullable', 'string', 'max:191'],
            'cnic_number' => ['nullable', 'string', 'max:30'],
            'phone' => ['required', 'string', 'max:30'],
            'secondary_phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:191'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_title' => ['nullable', 'string', 'max:100'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_iban' => ['nullable', 'string', 'max:50'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::in(['active', 'inactive', 'blacklisted'])],
            'notes' => ['nullable', 'string'],
        ]);

        if ($this->isEditingOwner) {
            /** @var VehicleOwner $owner */
            $owner = VehicleOwner::withoutGlobalScopes()->findOrFail($this->selectedOwnerId);
            app(TenantAuthorizationService::class)->validateTenantOwnership($owner);

            $old = $owner->toArray();
            $owner->update($validated);

            app(AuditLogService::class)->log('owner_updated', $owner, $old, $owner->toArray(), 'Vehicle owner details updated.');
            session()->flash('success', sprintf('Owner [%s] updated successfully.', $owner->name));
        } else {
            $owner = VehicleOwner::create(array_merge($validated, ['company_id' => $companyId]));
            app(AuditLogService::class)->log('owner_created', $owner, null, $owner->toArray(), 'New vehicle owner profile registered.');
            session()->flash('success', sprintf('Owner [%s] registered successfully.', $owner->name));
        }

        $this->showOwnerModal = false;
        $this->resetOwnerForm();
    }

    public function openContractModal(int $ownerId): void
    {
        $this->checkAuthorization();
        /** @var VehicleOwner $owner */
        $owner = VehicleOwner::withoutGlobalScopes()->findOrFail($ownerId);
        app(TenantAuthorizationService::class)->validateTenantOwnership($owner);

        $this->contractOwner = $owner;
        $this->contract_number = 'CON-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $owner->name), 0, 3)) . '-' . rand(1000, 9999);
        $this->payment_model = OwnerContract::MODEL_FIXED_PER_TRIP;
        $this->rate_amount = 0.00;
        $this->freight_percentage = 85.00;
        $this->commission_percentage = 15.00;
        $this->deduct_fuel = true;
        $this->deduct_toll_taxes = false;
        $this->deduct_advances = true;
        $this->route_origin = '';
        $this->route_destination = '';
        $this->start_date = now()->toDateString();
        $this->end_date = now()->addYear()->toDateString();
        $this->contract_status = 'active';
        $this->terms_and_conditions = 'Standard attached fleet agreement subject to company dispatch policies.';

        $this->showContractModal = true;
    }

    public function saveContract(): void
    {
        $this->checkAuthorization();
        $companyId = app(TenantContextService::class)->getCompanyId() ?? auth()->user()->company_id;

        $validated = $this->validate([
            'contract_number' => ['required', 'string', 'max:50'],
            'payment_model' => ['required', Rule::in(array_keys(OwnerContract::ALL_MODELS))],
            'rate_amount' => ['required', 'numeric', 'min:0'],
            'freight_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'commission_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'deduct_fuel' => ['boolean'],
            'deduct_toll_taxes' => ['boolean'],
            'deduct_advances' => ['boolean'],
            'route_origin' => ['nullable', 'string', 'max:100'],
            'route_destination' => ['nullable', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'contract_status' => ['required', Rule::in(['active', 'expired', 'terminated'])],
            'terms_and_conditions' => ['nullable', 'string'],
        ]);

        $contract = OwnerContract::create(array_merge($validated, [
            'company_id' => $companyId,
            'vehicle_owner_id' => $this->contractOwner->id,
            'status' => $validated['contract_status'],
        ]));

        app(AuditLogService::class)->log('contract_created', $contract, null, $contract->toArray(), sprintf('Created owner contract [%s].', $contract->contract_number));

        session()->flash('success', sprintf('Contract [%s] established for [%s].', $contract->contract_number, $this->contractOwner->name));
        $this->showContractModal = false;
    }

    public function viewOwner(int $ownerId): void
    {
        $this->checkAuthorization();
        $this->viewingOwner = VehicleOwner::withoutGlobalScopes()
            ->with(['vehicles', 'contracts'])
            ->findOrFail($ownerId);
        app(TenantAuthorizationService::class)->validateTenantOwnership($this->viewingOwner);

        $this->showDetailModal = true;
    }
}

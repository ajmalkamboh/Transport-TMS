<?php

namespace App\Livewire\Fleet;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Driver;
use App\Models\OwnerContract;
use App\Models\Vehicle;
use App\Models\VehicleOwner;
use App\Services\AuditLogService;
use App\Services\FeatureManagerService;
use App\Services\FleetManagementService;
use App\Services\TenantAuthorizationService;
use App\Services\TenantContextService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class VehicleManager extends Component
{
    use WithPagination;

    // Filters
    public string $search = '';
    public string $ownershipFilter = '';
    public string $statusFilter = '';
    public ?int $branchFilter = null;

    // Form Modal
    public bool $showModal = false;
    public bool $isEditing = false;
    public ?int $selectedVehicleId = null;

    // Fields
    public string $registration_number = '';
    public string $fleet_number = '';
    public string $ownership_type = Vehicle::OWNERSHIP_COMPANY;
    public ?int $vehicle_owner_id = null;
    public ?int $active_contract_id = null;
    public ?int $branch_id = null;
    public string $vehicle_category = 'HCV';
    public string $vehicle_type = 'TRAILER_22W';
    public string $make = 'Hino';
    public string $model = '500 Series';
    public ?int $manufacturing_year = 2022;
    public string $chassis_number = '';
    public string $engine_number = '';
    public float $payload_capacity_tons = 45.00;
    public ?float $volume_capacity_cbm = 90.00;
    public string $fuel_type = 'DIESEL';
    public ?float $fuel_tank_capacity_liters = 400.00;
    public int $current_odometer_km = 0;
    public ?string $registration_expiry_date = null;
    public ?string $insurance_expiry_date = null;
    public ?string $fitness_expiry_date = null;
    public ?string $route_permit_expiry_date = null;
    public string $status = Vehicle::STATUS_AVAILABLE;
    public string $notes = '';

    // Driver Assignment Modal
    public bool $showDriverModal = false;
    public ?int $assigningVehicleId = null;
    public ?Vehicle $assigningVehicle = null;
    public ?int $primary_driver_id = null;
    public ?int $co_driver_id = null;
    public string $assignment_notes = '';

    protected function checkAuthorization(): void
    {
        if (!auth()->check()) {
            throw new AuthorizationException('Authentication required.');
        }

        $user = auth()->user();
        if (!$user->isSuperAdmin() && !$user->isTenantAdmin() && !$user->hasPermission('view_fleet') && !$user->hasPermission('manage_fleet')) {
            throw new AuthorizationException('Access denied: You do not have permission to access Fleet Management.');
        }
    }

    public function render()
    {
        $this->checkAuthorization();

        $companyId = app(TenantContextService::class)->getCompanyId() ?? auth()->user()->company_id;
        $company = Company::findOrFail($companyId);

        $vehicles = Vehicle::where('company_id', $companyId)
            ->with(['branch', 'owner', 'contract', 'currentDriver'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('registration_number', 'like', '%' . $this->search . '%')
                      ->orWhere('fleet_number', 'like', '%' . $this->search . '%')
                      ->orWhere('make', 'like', '%' . $this->search . '%')
                      ->orWhere('model', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->ownershipFilter, fn($q) => $q->where('ownership_type', $this->ownershipFilter))
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->branchFilter, fn($q) => $q->where('branch_id', $this->branchFilter))
            ->latest()
            ->paginate(10);

        $branches = Branch::where('company_id', $companyId)->where('is_active', true)->get();
        $owners = VehicleOwner::where('company_id', $companyId)->where('status', 'active')->get();
        $drivers = Driver::where('company_id', $companyId)->where('status', 'available')->get();
        $contracts = $this->vehicle_owner_id
            ? OwnerContract::where('company_id', $companyId)->where('vehicle_owner_id', $this->vehicle_owner_id)->where('status', 'active')->get()
            : collect();

        return view('livewire.fleet.vehicle-manager', [
            'vehicles' => $vehicles,
            'company' => $company,
            'branches' => $branches,
            'owners' => $owners,
            'drivers' => $drivers,
            'contracts' => $contracts,
            'canAddVehicle' => $company->canAddVehicle(),
        ]);
    }

    public function openCreateModal(): void
    {
        $this->checkAuthorization();
        $companyId = app(TenantContextService::class)->getCompanyId() ?? auth()->user()->company_id;
        $company = Company::findOrFail($companyId);

        // Enforce Plan Vehicle Quota
        app(FeatureManagerService::class)->checkVehicleLimit($company);

        $this->resetForm();
        $this->isEditing = false;
        $this->showModal = true;
    }

    public function resetForm(): void
    {
        $this->selectedVehicleId = null;
        $this->registration_number = '';
        $this->fleet_number = '';
        $this->ownership_type = Vehicle::OWNERSHIP_COMPANY;
        $this->vehicle_owner_id = null;
        $this->active_contract_id = null;
        $this->branch_id = null;
        $this->vehicle_category = 'HCV';
        $this->vehicle_type = 'TRAILER_22W';
        $this->make = 'Hino';
        $this->model = '500 Series';
        $this->manufacturing_year = 2022;
        $this->chassis_number = '';
        $this->engine_number = '';
        $this->payload_capacity_tons = 45.00;
        $this->volume_capacity_cbm = 90.00;
        $this->fuel_type = 'DIESEL';
        $this->fuel_tank_capacity_liters = 400.00;
        $this->current_odometer_km = 0;
        $this->registration_expiry_date = null;
        $this->insurance_expiry_date = null;
        $this->fitness_expiry_date = null;
        $this->route_permit_expiry_date = null;
        $this->status = Vehicle::STATUS_AVAILABLE;
        $this->notes = '';
        $this->resetErrorBag();
    }

    public function editVehicle(int $vehicleId): void
    {
        $this->checkAuthorization();

        /** @var Vehicle $vehicle */
        $vehicle = Vehicle::withoutGlobalScopes()->findOrFail($vehicleId);
        app(TenantAuthorizationService::class)->validateTenantOwnership($vehicle);

        $this->selectedVehicleId = $vehicle->id;
        $this->registration_number = $vehicle->registration_number;
        $this->fleet_number = $vehicle->fleet_number ?? '';
        $this->ownership_type = $vehicle->ownership_type;
        $this->vehicle_owner_id = $vehicle->vehicle_owner_id;
        $this->active_contract_id = $vehicle->active_contract_id;
        $this->branch_id = $vehicle->branch_id;
        $this->vehicle_category = $vehicle->vehicle_category;
        $this->vehicle_type = $vehicle->vehicle_type;
        $this->make = $vehicle->make ?? '';
        $this->model = $vehicle->model ?? '';
        $this->manufacturing_year = $vehicle->manufacturing_year;
        $this->chassis_number = $vehicle->chassis_number ?? '';
        $this->engine_number = $vehicle->engine_number ?? '';
        $this->payload_capacity_tons = (float) $vehicle->payload_capacity_tons;
        $this->volume_capacity_cbm = (float) $vehicle->volume_capacity_cbm;
        $this->fuel_type = $vehicle->fuel_type;
        $this->fuel_tank_capacity_liters = (float) $vehicle->fuel_tank_capacity_liters;
        $this->current_odometer_km = $vehicle->current_odometer_km;
        $this->registration_expiry_date = $vehicle->registration_expiry_date?->toDateString();
        $this->insurance_expiry_date = $vehicle->insurance_expiry_date?->toDateString();
        $this->fitness_expiry_date = $vehicle->fitness_expiry_date?->toDateString();
        $this->route_permit_expiry_date = $vehicle->route_permit_expiry_date?->toDateString();
        $this->status = $vehicle->status;
        $this->notes = $vehicle->notes ?? '';

        $this->isEditing = true;
        $this->showModal = true;
    }

    public function saveVehicle(): void
    {
        $this->checkAuthorization();
        $companyId = app(TenantContextService::class)->getCompanyId() ?? auth()->user()->company_id;
        $company = Company::findOrFail($companyId);

        $rules = [
            'registration_number' => [
                'required', 'string', 'max:30',
                Rule::unique('vehicles', 'registration_number')
                    ->where('company_id', $companyId)
                    ->ignore($this->selectedVehicleId),
            ],
            'fleet_number' => ['nullable', 'string', 'max:30'],
            'ownership_type' => ['required', Rule::in([Vehicle::OWNERSHIP_COMPANY, Vehicle::OWNERSHIP_ATTACHED, Vehicle::OWNERSHIP_RENTED])],
            'vehicle_owner_id' => [
                $this->ownership_type !== Vehicle::OWNERSHIP_COMPANY ? 'required' : 'nullable',
                'exists:vehicle_owners,id',
            ],
            'active_contract_id' => ['nullable', 'exists:owner_contracts,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'vehicle_category' => ['required', 'string', 'max:50'],
            'vehicle_type' => ['required', 'string', 'max:50'],
            'make' => ['nullable', 'string', 'max:50'],
            'model' => ['nullable', 'string', 'max:50'],
            'manufacturing_year' => ['nullable', 'integer', 'min:1980', 'max:' . (date('Y') + 1)],
            'payload_capacity_tons' => ['required', 'numeric', 'min:0.1'],
            'fuel_type' => ['required', 'string', 'max:30'],
            'current_odometer_km' => ['required', 'integer', 'min:0'],
            'status' => ['required', Rule::in([Vehicle::STATUS_AVAILABLE, Vehicle::STATUS_ON_TRIP, Vehicle::STATUS_MAINTENANCE, Vehicle::STATUS_INACTIVE, Vehicle::STATUS_RETIRED])],
            'registration_expiry_date' => ['nullable', 'date'],
            'insurance_expiry_date' => ['nullable', 'date'],
            'fitness_expiry_date' => ['nullable', 'date'],
            'route_permit_expiry_date' => ['nullable', 'date'],
        ];

        $validated = $this->validate($rules);

        if ($this->isEditing) {
            /** @var Vehicle $vehicle */
            $vehicle = Vehicle::withoutGlobalScopes()->findOrFail($this->selectedVehicleId);
            app(TenantAuthorizationService::class)->validateTenantOwnership($vehicle);

            $old = $vehicle->toArray();
            $vehicle->update($validated);

            app(AuditLogService::class)->log('vehicle_updated', $vehicle, $old, $vehicle->toArray(), 'Vehicle specifications updated.');
            session()->flash('success', sprintf('Vehicle [%s] updated successfully.', $vehicle->registration_number));
        } else {
            $vehicle = app(FleetManagementService::class)->registerVehicle($company, $validated);
            session()->flash('success', sprintf('Vehicle [%s] successfully registered into fleet.', $vehicle->registration_number));
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function openAssignDriverModal(int $vehicleId): void
    {
        $this->checkAuthorization();
        /** @var Vehicle $vehicle */
        $vehicle = Vehicle::withoutGlobalScopes()->with(['currentDriver', 'activeDriverAssignments.driver'])->findOrFail($vehicleId);
        app(TenantAuthorizationService::class)->validateTenantOwnership($vehicle);

        $this->assigningVehicleId = $vehicle->id;
        $this->assigningVehicle = $vehicle;
        $this->primary_driver_id = $vehicle->current_driver_id;

        $coDriverAssign = $vehicle->activeDriverAssignments->firstWhere('is_primary', false);
        $this->co_driver_id = $coDriverAssign ? $coDriverAssign->driver_id : null;
        $this->assignment_notes = '';

        $this->showDriverModal = true;
    }

    public function assignDrivers(): void
    {
        $this->checkAuthorization();

        $this->validate([
            'primary_driver_id' => ['required', 'exists:drivers,id'],
            'co_driver_id' => ['nullable', 'exists:drivers,id', 'different:primary_driver_id'],
        ]);

        /** @var Vehicle $vehicle */
        $vehicle = Vehicle::withoutGlobalScopes()->findOrFail($this->assigningVehicleId);
        app(TenantAuthorizationService::class)->validateTenantOwnership($vehicle);

        app(FleetManagementService::class)->assignDrivers(
            $vehicle,
            $this->primary_driver_id,
            $this->co_driver_id,
            auth()->id(),
            $this->assignment_notes
        );

        session()->flash('success', sprintf('Drivers assigned successfully to truck [%s].', $vehicle->registration_number));
        $this->showDriverModal = false;
    }

    public function unassignDrivers(int $vehicleId): void
    {
        $this->checkAuthorization();
        /** @var Vehicle $vehicle */
        $vehicle = Vehicle::withoutGlobalScopes()->findOrFail($vehicleId);
        app(TenantAuthorizationService::class)->validateTenantOwnership($vehicle);

        app(FleetManagementService::class)->unassignDrivers($vehicle);
        session()->flash('success', sprintf('Drivers unassigned from truck [%s].', $vehicle->registration_number));
    }
}


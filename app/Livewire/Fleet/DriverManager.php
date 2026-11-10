<?php

namespace App\Livewire\Fleet;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Driver;
use App\Models\DriverAdvance;
use App\Models\Vehicle;
use App\Services\AuditLogService;
use App\Services\DriverOperationService;
use App\Services\TenantAuthorizationService;
use App\Services\TenantContextService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class DriverManager extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public string $licenseFilter = '';

    // Driver Modal
    public bool $showDriverModal = false;
    public bool $isEditingDriver = false;
    public ?int $selectedDriverId = null;

    public string $name = '';
    public string $father_name = '';
    public string $cnic_number = '';
    public string $phone = '';
    public string $secondary_phone = '';
    public string $license_number = '';
    public string $license_category = 'HTV';
    public string $license_expiry_date = '';
    public string $license_issuing_authority = 'Islamabad Traffic Police';
    public ?string $date_of_birth = null;
    public string $blood_group = 'B+';
    public string $address = '';
    public string $emergency_contact_name = '';
    public string $emergency_contact_relation = 'Brother';
    public string $emergency_contact_phone = '';
    public string $employment_type = 'permanent';
    public float $basic_salary = 35000.00;
    public float $per_trip_allowance = 5000.00;
    public float $daily_batta = 1500.00;
    public string $status = Driver::STATUS_AVAILABLE;
    public ?int $branch_id = null;
    public string $notes = '';

    // Advance Modal
    public bool $showAdvanceModal = false;
    public ?Driver $advancingDriver = null;
    public float $advance_amount = 0.00;
    public string $advance_purpose = 'Trip Diesel & Highway Allowance';
    public string $payment_method = 'cash';
    public string $advance_reference = '';
    public string $advance_notes = '';

    // Detail Modal
    public bool $showDetailModal = false;
    public ?Driver $viewingDriver = null;

    protected function checkAuthorization(): void
    {
        if (!auth()->check()) {
            throw new AuthorizationException('Authentication required.');
        }

        $user = auth()->user();
        if (!$user->isSuperAdmin() && !$user->isTenantAdmin() && !$user->hasPermission('view_fleet') && !$user->hasPermission('manage_fleet')) {
            throw new AuthorizationException('Access denied: You do not have permission to manage drivers.');
        }
    }

    public function render()
    {
        $this->checkAuthorization();

        $companyId = app(TenantContextService::class)->getCompanyId() ?? auth()->user()->company_id;

        $drivers = Driver::where('company_id', $companyId)
            ->with(['currentVehicle', 'branch'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('cnic_number', 'like', '%' . $this->search . '%')
                      ->orWhere('phone', 'like', '%' . $this->search . '%')
                      ->orWhere('license_number', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->licenseFilter, fn($q) => $q->where('license_category', $this->licenseFilter))
            ->latest()
            ->paginate(10);

        $branches = Branch::where('company_id', $companyId)->where('is_active', true)->get();

        return view('livewire.fleet.driver-manager', [
            'drivers' => $drivers,
            'branches' => $branches,
        ]);
    }

    public function openCreateDriverModal(): void
    {
        $this->checkAuthorization();
        $this->resetDriverForm();
        $this->isEditingDriver = false;
        $this->showDriverModal = true;
    }

    public function resetDriverForm(): void
    {
        $this->selectedDriverId = null;
        $this->name = '';
        $this->father_name = '';
        $this->cnic_number = '';
        $this->phone = '';
        $this->secondary_phone = '';
        $this->license_number = '';
        $this->license_category = 'HTV';
        $this->license_expiry_date = now()->addYears(2)->toDateString();
        $this->license_issuing_authority = 'Islamabad Traffic Police';
        $this->date_of_birth = null;
        $this->blood_group = 'B+';
        $this->address = '';
        $this->emergency_contact_name = '';
        $this->emergency_contact_relation = 'Brother';
        $this->emergency_contact_phone = '';
        $this->employment_type = 'permanent';
        $this->basic_salary = 35000.00;
        $this->per_trip_allowance = 5000.00;
        $this->daily_batta = 1500.00;
        $this->status = Driver::STATUS_AVAILABLE;
        $this->branch_id = null;
        $this->notes = '';
        $this->resetErrorBag();
    }

    public function editDriver(int $driverId): void
    {
        $this->checkAuthorization();

        /** @var Driver $driver */
        $driver = Driver::withoutGlobalScopes()->findOrFail($driverId);
        app(TenantAuthorizationService::class)->validateTenantOwnership($driver);

        $this->selectedDriverId = $driver->id;
        $this->name = $driver->name;
        $this->father_name = $driver->father_name ?? '';
        $this->cnic_number = $driver->cnic_number;
        $this->phone = $driver->phone;
        $this->secondary_phone = $driver->secondary_phone ?? '';
        $this->license_number = $driver->license_number;
        $this->license_category = $driver->license_category;
        $this->license_expiry_date = $driver->license_expiry_date->toDateString();
        $this->license_issuing_authority = $driver->license_issuing_authority ?? '';
        $this->date_of_birth = $driver->date_of_birth?->toDateString();
        $this->blood_group = $driver->blood_group ?? 'B+';
        $this->address = $driver->address ?? '';
        $this->emergency_contact_name = $driver->emergency_contact_name ?? '';
        $this->emergency_contact_relation = $driver->emergency_contact_relation ?? '';
        $this->emergency_contact_phone = $driver->emergency_contact_phone ?? '';
        $this->employment_type = $driver->employment_type;
        $this->basic_salary = (float) $driver->basic_salary;
        $this->per_trip_allowance = (float) $driver->per_trip_allowance;
        $this->daily_batta = (float) $driver->daily_batta;
        $this->status = $driver->status;
        $this->branch_id = $driver->branch_id;
        $this->notes = $driver->notes ?? '';

        $this->isEditingDriver = true;
        $this->showDriverModal = true;
    }

    public function saveDriver(): void
    {
        $this->checkAuthorization();
        $companyId = app(TenantContextService::class)->getCompanyId() ?? auth()->user()->company_id;

        $rules = [
            'name' => ['required', 'string', 'max:191'],
            'father_name' => ['nullable', 'string', 'max:191'],
            'cnic_number' => [
                'required', 'string', 'max:30',
                Rule::unique('drivers', 'cnic_number')
                    ->where('company_id', $companyId)
                    ->ignore($this->selectedDriverId),
            ],
            'phone' => ['required', 'string', 'max:30'],
            'secondary_phone' => ['nullable', 'string', 'max:30'],
            'license_number' => ['required', 'string', 'max:50'],
            'license_category' => ['required', 'string', 'max:50'],
            'license_expiry_date' => ['required', 'date'],
            'license_issuing_authority' => ['nullable', 'string', 'max:100'],
            'date_of_birth' => ['nullable', 'date'],
            'blood_group' => ['nullable', 'string', 'max:10'],
            'address' => ['nullable', 'string'],
            'emergency_contact_name' => ['nullable', 'string', 'max:100'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:50'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'employment_type' => ['required', Rule::in(['permanent', 'contractual', 'trip_based', 'daily_wages'])],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'per_trip_allowance' => ['required', 'numeric', 'min:0'],
            'daily_batta' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in([Driver::STATUS_AVAILABLE, Driver::STATUS_ON_TRIP, Driver::STATUS_ON_LEAVE, Driver::STATUS_SUSPENDED, Driver::STATUS_TERMINATED])],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'notes' => ['nullable', 'string'],
        ];

        $validated = $this->validate($rules);

        if ($this->isEditingDriver) {
            /** @var Driver $driver */
            $driver = Driver::withoutGlobalScopes()->findOrFail($this->selectedDriverId);
            app(TenantAuthorizationService::class)->validateTenantOwnership($driver);

            $old = $driver->toArray();
            $driver->update($validated);

            app(AuditLogService::class)->log('driver_updated', $driver, $old, $driver->toArray(), 'Driver profile updated.');
            session()->flash('success', sprintf('Driver [%s] profile updated.', $driver->name));
        } else {
            $driver = Driver::create(array_merge($validated, ['company_id' => $companyId]));
            app(AuditLogService::class)->log('driver_created', $driver, null, $driver->toArray(), 'New driver registered.');
            session()->flash('success', sprintf('Driver [%s] successfully registered.', $driver->name));
        }

        $this->showDriverModal = false;
        $this->resetDriverForm();
    }

    public function openAdvanceModal(int $driverId): void
    {
        $this->checkAuthorization();
        /** @var Driver $driver */
        $driver = Driver::withoutGlobalScopes()->findOrFail($driverId);
        app(TenantAuthorizationService::class)->validateTenantOwnership($driver);

        $this->advancingDriver = $driver;
        $this->advance_amount = 5000.00;
        $this->advance_purpose = 'Trip Diesel & Highway Allowance';
        $this->payment_method = 'cash';
        $this->advance_reference = '';
        $this->advance_notes = '';
        $this->showAdvanceModal = true;
    }

    public function disburseAdvance(): void
    {
        $this->checkAuthorization();

        $this->validate([
            'advance_amount' => ['required', 'numeric', 'min:1'],
            'advance_purpose' => ['required', 'string', 'max:191'],
            'payment_method' => ['required', 'string'],
            'advance_reference' => ['nullable', 'string', 'max:100'],
        ]);

        $advance = app(DriverOperationService::class)->disburseAdvance(
            $this->advancingDriver,
            $this->advance_amount,
            $this->advance_purpose,
            $this->payment_method,
            $this->advance_reference,
            null,
            auth()->id(),
            $this->advance_notes
        );

        session()->flash('success', sprintf('Cash advance of PKR %s disbursed to [%s] with voucher [%s].', number_format($this->advance_amount, 2), $this->advancingDriver->name, $advance->advance_number));
        $this->showAdvanceModal = false;
    }

    public function viewDriver(int $driverId): void
    {
        $this->checkAuthorization();
        $this->viewingDriver = Driver::withoutGlobalScopes()
            ->with(['currentVehicle', 'branch', 'advances' => fn($q) => $q->latest()->limit(5)])
            ->findOrFail($driverId);
        app(TenantAuthorizationService::class)->validateTenantOwnership($this->viewingDriver);

        $this->showDetailModal = true;
    }
}

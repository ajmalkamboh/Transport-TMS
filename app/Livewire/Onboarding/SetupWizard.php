<?php

namespace App\Livewire\Onboarding;

use App\Models\Branch;
use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\FinancialYear;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\DocumentSequenceService;
use App\Services\FinancialPeriodService;
use App\Services\MasterDataService;
use App\Services\TenantContextService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class SetupWizard extends Component
{
    public int $currentStep = 1;
    public array $completedSteps = [];

    // Step 1: Company Profile
    public string $company_name = '';
    public string $trading_name = '';
    public string $registration_number = '';
    public string $tax_number = '';
    public string $email = '';
    public string $phone = '';
    public string $address = '';
    public string $country = 'Pakistan';
    public string $currency = 'PKR';
    public string $timezone = 'Asia/Karachi';

    // Step 2: Primary Branch Setup
    public string $branch_name = '';
    public string $branch_code = '';
    public string $branch_city = 'Lahore';
    public string $branch_phone = '';

    // Step 3: Financial Year Configuration
    public string $fy_name = '';
    public string $fy_start_date = '';
    public string $fy_end_date = '';
    public int $currency_precision = 2;

    // Step 4: Master Data Status
    public bool $masterDataSeeded = false;

    // Step 5: Staff Member (Optional)
    public string $staff_name = '';
    public string $staff_email = '';
    public string $staff_password = '';
    public string $staff_role = 'dispatcher';

    // Step 6: Initial Fleet (Optional)
    public string $vehicle_registration = '';
    public string $vehicle_type = 'TRAILER_22W';
    public string $ownership_type = 'company_owned';
    public float $payload_capacity = 45.00;

    protected function checkAuthorization(): void
    {
        if (!auth()->check()) {
            throw new AuthorizationException('Authentication required.');
        }

        $user = auth()->user();
        if (!$user->isSuperAdmin() && !$user->isTenantAdmin()) {
            throw new AuthorizationException('Only tenant business owners and administrators can complete system onboarding.');
        }
    }

    public function mount(): void
    {
        $this->checkAuthorization();

        $company = app(TenantContextService::class)->getCompany() ?? auth()->user()->company;
        if (!$company) {
            throw new \RuntimeException('Tenant company context not found.');
        }

        // Initialize Step 1 values
        $this->company_name = $company->name;
        $this->trading_name = $company->trading_name ?? $company->name;
        $this->registration_number = $company->registration_number ?? '';
        $this->tax_number = $company->tax_number ?? '';
        $this->email = $company->email ?? '';
        $this->phone = $company->phone ?? '';
        $this->address = $company->address ?? '';
        $this->country = $company->country ?? 'Pakistan';
        $this->currency = $company->currency ?? 'PKR';
        $this->timezone = $company->timezone ?? 'Asia/Karachi';

        // Initialize Step 2 values
        $headBranch = $company->branches()->where('is_head_office', true)->first();
        if ($headBranch) {
            $this->branch_name = $headBranch->name;
            $this->branch_code = $headBranch->code;
            $this->branch_city = $headBranch->city;
            $this->branch_phone = $headBranch->phone ?? $company->phone;
        } else {
            $this->branch_name = $company->name . ' (Head Office)';
            $this->branch_code = $company->code . '-HQ';
            $this->branch_city = 'Lahore';
            $this->branch_phone = $company->phone;
        }

        // Initialize Step 3 values
        $this->fy_name = 'FY ' . date('Y') . '-' . (date('Y') + 1);
        $this->fy_start_date = date('Y') . '-07-01';
        $this->fy_end_date = (date('Y') + 1) . '-06-30';
        $this->currency_precision = (int) ($company->currency_precision ?? 2);

        // Resume step if previously started
        if ($company->onboarding_step > 1 && !$company->isOnboarded()) {
            $this->currentStep = min(6, $company->onboarding_step);
            for ($i = 1; $i < $this->currentStep; $i++) {
                $this->completedSteps[] = $i;
            }
        }
    }

    public function render()
    {
        $this->checkAuthorization();

        $company = app(TenantContextService::class)->getCompany() ?? auth()->user()->company;

        return view('livewire.onboarding.setup-wizard', [
            'company' => $company,
            'progress' => (int) round((count($this->completedSteps) / 6) * 100),
        ]);
    }

    public function goToStep(int $step): void
    {
        if ($step >= 1 && $step <= 6 && in_array($step - 1, $this->completedSteps)) {
            $this->currentStep = $step;
        }
    }

    public function completeStep1(): void
    {
        $this->checkAuthorization();
        $company = app(TenantContextService::class)->getCompany() ?? auth()->user()->company;

        $validated = $this->validate([
            'company_name' => ['required', 'string', 'max:191'],
            'trading_name' => ['nullable', 'string', 'max:191'],
            'registration_number' => ['nullable', 'string', 'max:50'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:191'],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'country' => ['required', 'string', 'max:50'],
            'currency' => ['required', 'string', 'max:10'],
            'timezone' => ['required', 'string', 'max:50'],
        ]);

        $company->update([
            'name' => $validated['company_name'],
            'trading_name' => $validated['trading_name'] ?: $validated['company_name'],
            'registration_number' => $validated['registration_number'],
            'tax_number' => $validated['tax_number'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'address' => $validated['address'],
            'country' => $validated['country'],
            'currency' => $validated['currency'],
            'timezone' => $validated['timezone'],
            'onboarding_step' => max($company->onboarding_step, 2),
        ]);

        $this->markStepCompleted(1);
        $this->currentStep = 2;
    }

    public function completeStep2(): void
    {
        $this->checkAuthorization();
        $company = app(TenantContextService::class)->getCompany() ?? auth()->user()->company;

        $validated = $this->validate([
            'branch_name' => ['required', 'string', 'max:191'],
            'branch_code' => ['required', 'string', 'max:30'],
            'branch_city' => ['required', 'string', 'max:100'],
            'branch_phone' => ['nullable', 'string', 'max:30'],
        ]);

        $headBranch = $company->branches()->where('is_head_office', true)->first();
        if ($headBranch) {
            $headBranch->update([
                'name' => $validated['branch_name'],
                'code' => strtoupper($validated['branch_code']),
                'city' => $validated['branch_city'],
                'phone' => $validated['branch_phone'],
                'is_default' => true,
            ]);
        } else {
            Branch::create([
                'company_id' => $company->id,
                'name' => $validated['branch_name'],
                'code' => strtoupper($validated['branch_code']),
                'city' => $validated['branch_city'],
                'phone' => $validated['branch_phone'],
                'is_head_office' => true,
                'is_default' => true,
                'is_active' => true,
            ]);
        }

        $company->update(['onboarding_step' => max($company->onboarding_step, 3)]);
        $this->markStepCompleted(2);
        $this->currentStep = 3;
    }

    public function completeStep3(): void
    {
        $this->checkAuthorization();
        $company = app(TenantContextService::class)->getCompany() ?? auth()->user()->company;

        $validated = $this->validate([
            'fy_name' => ['required', 'string', 'max:50'],
            'fy_start_date' => ['required', 'date'],
            'fy_end_date' => ['required', 'date', 'after:fy_start_date'],
            'currency_precision' => ['required', 'integer', 'min:0', 'max:4'],
        ]);

        $company->update([
            'currency_precision' => $validated['currency_precision'],
            'onboarding_step' => max($company->onboarding_step, 4),
        ]);

        // Generate Financial Year if not existing
        if (!$company->financialYears()->where('name', $validated['fy_name'])->exists()) {
            app(FinancialPeriodService::class)->createFinancialYearWithPeriods(
                $company,
                $validated['fy_name'],
                $validated['fy_start_date'],
                $validated['fy_end_date'],
                true
            );
        }

        $this->markStepCompleted(3);
        $this->currentStep = 4;
    }

    public function completeStep4(): void
    {
        $this->checkAuthorization();
        $company = app(TenantContextService::class)->getCompany() ?? auth()->user()->company;

        // Ensure system master lookups are seeded
        app(MasterDataService::class)->seedSystemDefaultLookups();

        // Ensure core document sequences are initialized for the tenant
        $docSeqService = app(DocumentSequenceService::class);
        foreach (DocumentSequence::ALL_TYPES as $type => $info) {
            $prefix = strtoupper($company->code) . '-' . $info['default_prefix'];
            $docSeqService->configureSequence($company->id, $type, $prefix);
        }

        $this->masterDataSeeded = true;
        $company->update(['onboarding_step' => max($company->onboarding_step, 5)]);

        $this->markStepCompleted(4);
        $this->currentStep = 5;
    }

    public function completeStep5(): void
    {
        $this->checkAuthorization();
        $company = app(TenantContextService::class)->getCompany() ?? auth()->user()->company;
        $branch = $company->branches()->where('is_head_office', true)->first();

        // Optional staff member creation
        if (!empty($this->staff_email) && !empty($this->staff_name) && !empty($this->staff_password)) {
            $this->validate([
                'staff_name' => ['required', 'string', 'max:191'],
                'staff_email' => ['required', 'email', 'unique:users,email'],
                'staff_password' => ['required', 'string', 'min:8'],
                'staff_role' => ['required', 'string'],
            ]);

            $role = Role::where('slug', $this->staff_role)->first();

            $staff = User::create([
                'company_id' => $company->id,
                'branch_id' => $branch?->id,
                'role_id' => $role?->id,
                'name' => $this->staff_name,
                'email' => $this->staff_email,
                'password' => Hash::make($this->staff_password),
                'status' => 'active',
                'is_super_admin' => false,
            ]);

            if ($branch) {
                $staff->branches()->sync([$branch->id]);
            }
        }

        $company->update(['onboarding_step' => max($company->onboarding_step, 6)]);
        $this->markStepCompleted(5);
        $this->currentStep = 6;
    }

    public function completeStep6(): void
    {
        $this->checkAuthorization();
        /** @var Company $company */
        $company = app(TenantContextService::class)->getCompany() ?? auth()->user()->company;

        // If fleet truck entered, record in settings temporarily until Fleet module in Phase 4
        if (!empty($this->vehicle_registration)) {
            $settings = $company->settings ?? [];
            $settings['initial_fleet'] = [
                'registration' => strtoupper($this->vehicle_registration),
                'vehicle_type' => $this->vehicle_type,
                'ownership_type' => $this->ownership_type,
                'payload_capacity' => $this->payload_capacity,
            ];
            $company->settings = $settings;
        }

        // Finalize Onboarding
        $company->onboarding_completed_at = now();
        $company->onboarding_step = 6;
        $company->save();

        $this->markStepCompleted(6);

        app(AuditLogService::class)->log(
            'onboarding_completed',
            $company,
            null,
            $company->toArray(),
            'Tenant business owner successfully finished initial organizational onboarding wizard.'
        );

        session()->flash('success', 'Setup Wizard completed! Your transport enterprise is now configured and ready for operations.');
        $this->redirect(route('dashboard'));
    }

    protected function markStepCompleted(int $step): void
    {
        if (!in_array($step, $this->completedSteps)) {
            $this->completedSteps[] = $step;
        }
    }
}


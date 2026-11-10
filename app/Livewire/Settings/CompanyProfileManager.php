<?php

namespace App\Livewire\Settings;

use App\Models\Company;
use App\Services\AuditLogService;
use App\Services\TenantContextService;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Component;

class CompanyProfileManager extends Component
{
    public int $companyId;
    public string $name = '';
    public string $trading_name = '';
    public string $code = '';
    public string $registration_number = '';
    public string $tax_number = '';
    public string $email = '';
    public string $phone = '';
    public string $website = '';
    public string $address = '';
    public string $country = 'Pakistan';
    public string $currency = 'PKR';
    public string $timezone = 'Asia/Karachi';
    public int $currency_precision = 2;
    public bool $is_tax_enabled = true;
    public string $tax_name = 'Sales Tax / GST';
    public float $default_tax_rate = 16.00;

    protected function checkAuthorization(): void
    {
        if (!auth()->check()) {
            throw new AuthorizationException('Authentication required.');
        }

        $user = auth()->user();
        if (!$user->isSuperAdmin() && !$user->isTenantAdmin() && !$user->hasPermission('manage_settings')) {
            throw new AuthorizationException('Access denied: You do not have permission to manage company settings.');
        }
    }

    public function mount(): void
    {
        $this->checkAuthorization();

        $contextService = app(TenantContextService::class);
        $company = $contextService->getCompany() ?? auth()->user()->company;

        if (!$company) {
            throw new \RuntimeException('No tenant company context found.');
        }

        $this->companyId = $company->id;
        $this->name = $company->name;
        $this->trading_name = $company->trading_name ?? '';
        $this->code = $company->code;
        $this->registration_number = $company->registration_number ?? '';
        $this->tax_number = $company->tax_number ?? '';
        $this->email = $company->email ?? '';
        $this->phone = $company->phone ?? '';
        $this->website = $company->website ?? '';
        $this->address = $company->address ?? '';
        $this->country = $company->country ?? 'Pakistan';
        $this->currency = $company->currency ?? 'PKR';
        $this->timezone = $company->timezone ?? 'Asia/Karachi';
        $this->currency_precision = (int) ($company->currency_precision ?? 2);
        $this->is_tax_enabled = (bool) ($company->is_tax_enabled ?? true);
        $this->tax_name = $company->tax_name ?? 'Sales Tax / GST';
        $this->default_tax_rate = (float) ($company->default_tax_rate ?? 16.00);
    }

    public function render()
    {
        $this->checkAuthorization();

        $company = Company::findOrFail($this->companyId);

        return view('livewire.settings.company-profile-manager', [
            'company' => $company,
        ]);
    }

    public function save(): void
    {
        $this->checkAuthorization();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:191'],
            'trading_name' => ['nullable', 'string', 'max:191'],
            'registration_number' => ['nullable', 'string', 'max:50'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:191'],
            'phone' => ['required', 'string', 'max:30'],
            'website' => ['nullable', 'string', 'max:191'],
            'address' => ['nullable', 'string'],
            'country' => ['required', 'string', 'max:50'],
            'currency' => ['required', 'string', 'max:10'],
            'timezone' => ['required', 'string', 'max:50'],
            'currency_precision' => ['required', 'integer', 'min:0', 'max:4'],
            'is_tax_enabled' => ['boolean'],
            'tax_name' => ['required', 'string', 'max:50'],
            'default_tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        /** @var Company $company */
        $company = Company::findOrFail($this->companyId);
        $old = $company->toArray();

        $company->update($validated);

        app(AuditLogService::class)->log(
            'company_profile_updated',
            $company,
            $old,
            $company->toArray(),
            'Tenant company profile and system configuration updated.'
        );

        session()->flash('success', 'Company profile and system settings updated successfully.');
    }
}


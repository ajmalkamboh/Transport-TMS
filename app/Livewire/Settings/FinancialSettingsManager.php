<?php

namespace App\Livewire\Settings;

use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\FinancialYear;
use App\Services\AuditLogService;
use App\Services\FinancialPeriodService;
use App\Services\TenantAuthorizationService;
use App\Services\TenantContextService;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Component;

class FinancialSettingsManager extends Component
{
    public bool $showCreateModal = false;
    public string $name = '';
    public string $start_date = '';
    public string $end_date = '';
    public bool $make_current = true;

    public ?int $selectedYearId = null;

    protected function checkAuthorization(): void
    {
        if (!auth()->check()) {
            throw new AuthorizationException('Authentication required.');
        }

        $user = auth()->user();
        if (!$user->isSuperAdmin() && !$user->isTenantAdmin() && !$user->hasPermission('manage_financial_settings')) {
            throw new AuthorizationException('Access denied: You do not have permission to manage financial configurations.');
        }
    }

    public function render()
    {
        $this->checkAuthorization();

        $companyId = app(TenantContextService::class)->getCompanyId() ?? auth()->user()->company_id;
        $company = Company::findOrFail($companyId);

        $financialYears = FinancialYear::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->with(['periods.closer'])
            ->orderByDesc('start_date')
            ->get();

        $selectedYear = $this->selectedYearId
            ? $financialYears->firstWhere('id', $this->selectedYearId)
            : $financialYears->firstWhere('is_current', true) ?? $financialYears->first();

        return view('livewire.settings.financial-settings-manager', [
            'company' => $company,
            'financialYears' => $financialYears,
            'selectedYear' => $selectedYear,
        ]);
    }

    public function selectYear(int $yearId): void
    {
        $this->selectedYearId = $yearId;
    }

    public function openCreateModal(): void
    {
        $this->checkAuthorization();
        $this->name = 'FY ' . date('Y') . '-' . (date('Y') + 1);
        $this->start_date = date('Y') . '-07-01'; // Default Pakistan fiscal year July 1
        $this->end_date = (date('Y') + 1) . '-06-30'; // June 30
        $this->make_current = true;
        $this->showCreateModal = true;
    }

    public function createFinancialYear(): void
    {
        $this->checkAuthorization();
        $companyId = app(TenantContextService::class)->getCompanyId() ?? auth()->user()->company_id;
        $company = Company::findOrFail($companyId);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:50'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'make_current' => ['boolean'],
        ]);

        $fy = app(FinancialPeriodService::class)->createFinancialYearWithPeriods(
            $company,
            $validated['name'],
            $validated['start_date'],
            $validated['end_date'],
            $validated['make_current']
        );

        app(AuditLogService::class)->log(
            'financial_year_created',
            $fy,
            null,
            $fy->toArray(),
            sprintf('Created Financial Year [%s] with 12 monthly accounting periods.', $fy->name)
        );

        $this->selectedYearId = $fy->id;
        $this->showCreateModal = false;
        session()->flash('success', sprintf('Financial Year [%s] and 12 monthly periods generated successfully.', $fy->name));
    }

    public function makeCurrent(int $yearId): void
    {
        $this->checkAuthorization();
        $companyId = app(TenantContextService::class)->getCompanyId() ?? auth()->user()->company_id;

        /** @var FinancialYear $fy */
        $fy = FinancialYear::withoutGlobalScopes()->findOrFail($yearId);
        app(TenantAuthorizationService::class)->validateTenantOwnership($fy);

        FinancialYear::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->update(['is_current' => false]);

        $fy->is_current = true;
        $fy->save();

        session()->flash('success', sprintf('Financial Year [%s] marked as Current Fiscal Year.', $fy->name));
    }

    public function togglePeriodStatus(int $periodId): void
    {
        $this->checkAuthorization();

        /** @var AccountingPeriod $period */
        $period = AccountingPeriod::withoutGlobalScopes()->findOrFail($periodId);
        app(TenantAuthorizationService::class)->validateTenantOwnership($period);

        if ($period->isOpen()) {
            app(FinancialPeriodService::class)->closeAccountingPeriod($period, auth()->id());
            session()->flash('success', sprintf('Accounting Period [%s] is now CLOSED. Transactions dated in this period are now restricted.', $period->name));
        } else {
            app(FinancialPeriodService::class)->reopenAccountingPeriod($period);
            session()->flash('success', sprintf('Accounting Period [%s] has been REOPENED.', $period->name));
        }
    }

    public function closeYear(int $yearId): void
    {
        $this->checkAuthorization();

        /** @var FinancialYear $fy */
        $fy = FinancialYear::withoutGlobalScopes()->findOrFail($yearId);
        app(TenantAuthorizationService::class)->validateTenantOwnership($fy);

        app(FinancialPeriodService::class)->closeFinancialYear($fy, 'Closed by tenant administrator.');

        session()->flash('success', sprintf('Financial Year [%s] and all its periods have been CLOSED.', $fy->name));
    }
}

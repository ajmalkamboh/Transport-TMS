<?php

namespace App\Livewire\Reports;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Models\VehicleOwner;
use App\Models\Warehouse;
use App\Services\BusinessIntelligenceService;
use App\Services\FinancialReportService;
use App\Services\TenantContextService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ReportCenter extends Component
{
    // Navigation Tabs
    public string $activeTab = 'executive'; // executive, fleet, driver, customer, trip, owner, financial, inventory
    public string $financialSubReport = 'pnl'; // pnl, trial_balance, balance_sheet, cash_flow, expenses

    // Filter controls
    public string $fromDate = '';
    public string $toDate = '';
    public ?int $branchFilter = null;
    public ?int $vehicleFilter = null;
    public ?int $customerFilter = null;
    public ?int $driverFilter = null;
    public ?int $ownerFilter = null;
    public ?int $warehouseFilter = null;
    public string $statusFilter = '';

    protected $queryString = [
        'activeTab' => ['except' => 'executive'],
    ];

    public function mount(): void
    {
        $this->fromDate = now()->startOfMonth()->toDateString();
        $this->toDate = now()->toDateString();
    }

    public function setPreset(string $preset): void
    {
        switch ($preset) {
            case 'today':
                $this->fromDate = now()->toDateString();
                $this->toDate = now()->toDateString();
                break;
            case 'this_month':
                $this->fromDate = now()->startOfMonth()->toDateString();
                $this->toDate = now()->toDateString();
                break;
            case 'this_quarter':
                $this->fromDate = now()->startOfQuarter()->toDateString();
                $this->toDate = now()->toDateString();
                break;
            case 'this_year':
                $this->fromDate = now()->startOfYear()->toDateString();
                $this->toDate = now()->toDateString();
                break;
        }
    }

    public function resetFilters(): void
    {
        $this->branchFilter = null;
        $this->vehicleFilter = null;
        $this->customerFilter = null;
        $this->driverFilter = null;
        $this->ownerFilter = null;
        $this->warehouseFilter = null;
        $this->statusFilter = '';
        $this->fromDate = now()->startOfMonth()->toDateString();
        $this->toDate = now()->toDateString();
    }

    public function render()
    {
        $companyId = app(TenantContextService::class)->getCompanyId() ?? Auth::user()->company_id;
        $biService = app(BusinessIntelligenceService::class);
        $finService = app(FinancialReportService::class);

        // Filter Dropdowns
        $branches = Branch::withoutGlobalScopes()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get();
        $vehicles = Vehicle::withoutGlobalScopes()->where('company_id', $companyId)->where('is_active', true)->orderBy('registration_number')->get();
        $customers = Customer::withoutGlobalScopes()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get();
        $drivers = Driver::withoutGlobalScopes()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get();
        $owners = VehicleOwner::withoutGlobalScopes()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get();
        $warehouses = Warehouse::withoutGlobalScopes()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get();

        $reportData = [];

        if ($this->activeTab === 'executive') {
            $reportData = $biService->getExecutiveDashboardMetrics(
                $companyId,
                $this->branchFilter,
                $this->fromDate,
                $this->toDate
            );
        } elseif ($this->activeTab === 'fleet') {
            $reportData = $biService->getFleetReports(
                $companyId,
                $this->branchFilter,
                $this->fromDate,
                $this->toDate,
                $this->statusFilter ?: null,
                $this->vehicleFilter
            );
        } elseif ($this->activeTab === 'driver') {
            $reportData = $biService->getDriverReports(
                $companyId,
                $this->branchFilter,
                $this->fromDate,
                $this->toDate,
                $this->driverFilter
            );
        } elseif ($this->activeTab === 'customer') {
            $reportData = $biService->getCustomerAgingAndRevenueReport(
                $companyId,
                $this->branchFilter,
                $this->toDate,
                $this->customerFilter
            );
        } elseif ($this->activeTab === 'trip') {
            $reportData = [
                'trips' => $biService->getTripReports($companyId, $this->branchFilter, $this->fromDate, $this->toDate, $this->statusFilter ?: null),
                'routes' => $biService->getRoutePerformanceSummary($companyId, $this->branchFilter, $this->fromDate, $this->toDate),
            ];
        } elseif ($this->activeTab === 'owner') {
            $reportData = $biService->getVehicleOwnerReports(
                $companyId,
                $this->branchFilter,
                $this->fromDate,
                $this->toDate,
                $this->ownerFilter
            );
        } elseif ($this->activeTab === 'financial') {
            if ($this->financialSubReport === 'trial_balance') {
                $reportData = $finService->getTrialBalance($companyId, $this->toDate, $this->branchFilter);
            } elseif ($this->financialSubReport === 'balance_sheet') {
                $reportData = $finService->getBalanceSheet($companyId, $this->toDate, $this->branchFilter);
            } elseif ($this->financialSubReport === 'cash_flow') {
                $reportData = $biService->getCashFlowStatement($companyId, $this->fromDate, $this->toDate, $this->branchFilter);
            } else {
                $reportData = $finService->getProfitAndLoss($companyId, $this->fromDate, $this->toDate, $this->branchFilter);
            }
        } elseif ($this->activeTab === 'inventory') {
            $reportData = $biService->getInventoryReports(
                $companyId,
                $this->warehouseFilter,
                $this->fromDate,
                $this->toDate
            );
        }

        return view('livewire.reports.report-center', [
            'branches' => $branches,
            'vehicles' => $vehicles,
            'customers' => $customers,
            'drivers' => $drivers,
            'owners' => $owners,
            'warehouses' => $warehouses,
            'reportData' => $reportData,
        ]);
    }
}


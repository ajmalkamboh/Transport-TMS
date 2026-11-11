<?php

namespace App\Livewire\Finance;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Trip;
use App\Services\FinancialReportService;
use App\Services\TenantContextService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class FinancialReportManager extends Component
{
    public string $activeReport = 'trial_balance'; // 'trial_balance', 'pnl', 'balance_sheet', 'customer_statement', 'trip_profitability'

    // Filter controls
    public string $fromDate = '';
    public string $toDate = '';
    public string $asOfDate = '';
    public ?int $selectedBranchId = null;
    public ?int $selectedCustomerId = null;
    public ?int $selectedTripId = null;

    public function mount(): void
    {
        $this->fromDate = now()->startOfYear()->toDateString();
        $this->toDate = now()->toDateString();
        $this->asOfDate = now()->toDateString();
    }

    public function render()
    {
        $companyId = app(TenantContextService::class)->getCompanyId() ?? Auth::user()->company_id;
        $reportService = app(FinancialReportService::class);

        $branches = Branch::withoutGlobalScopes()->where('company_id', $companyId)->get();
        $customers = Customer::withoutGlobalScopes()->where('company_id', $companyId)->orderBy('name')->get();
        $trips = Trip::withoutGlobalScopes()->where('company_id', $companyId)->latest('id')->take(40)->get();

        $data = [];

        if ($this->activeReport === 'trial_balance') {
            $data = $reportService->getTrialBalance($companyId, $this->asOfDate, $this->selectedBranchId);
        } elseif ($this->activeReport === 'pnl') {
            $data = $reportService->getProfitAndLoss($companyId, $this->fromDate, $this->toDate, $this->selectedBranchId);
        } elseif ($this->activeReport === 'balance_sheet') {
            $data = $reportService->getBalanceSheet($companyId, $this->asOfDate, $this->selectedBranchId);
        } elseif ($this->activeReport === 'customer_statement') {
            if (!$this->selectedCustomerId && $customers->isNotEmpty()) {
                $this->selectedCustomerId = $customers->first()->id;
            }
            if ($this->selectedCustomerId) {
                $data = $reportService->getCustomerStatement($companyId, $this->selectedCustomerId, $this->fromDate, $this->toDate);
            }
        } elseif ($this->activeReport === 'trip_profitability') {
            if (!$this->selectedTripId && $trips->isNotEmpty()) {
                $this->selectedTripId = $trips->first()->id;
            }
            if ($this->selectedTripId) {
                $data = $reportService->getTripProfitability($companyId, $this->selectedTripId);
            }
        }

        return view('livewire.finance.financial-report-manager', compact(
            'branches',
            'customers',
            'trips',
            'data'
        ));
    }
}


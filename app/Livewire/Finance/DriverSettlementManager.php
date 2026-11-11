<?php

namespace App\Livewire\Finance;

use App\Models\Driver;
use App\Models\DriverSettlement;
use App\Models\Trip;
use App\Services\DriverAccountService;
use App\Services\TenantContextService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class DriverSettlementManager extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public ?int $driverFilter = null;

    // Create Settlement Modal State
    public bool $showCreateModal = false;
    public ?int $selectedDriverId = null;
    public ?int $selectedTripId = null;
    public string $settlementDate = '';
    public float $totalAdvances = 0.00;
    public float $driverAllowance = 0.00;
    public float $outOfPocketExpenses = 0.00;
    public string $notes = '';

    public function mount(): void
    {
        $this->settlementDate = now()->toDateString();
    }

    public function openCreateModal(): void
    {
        $this->reset(['selectedDriverId', 'selectedTripId', 'totalAdvances', 'driverAllowance', 'outOfPocketExpenses', 'notes']);
        $this->settlementDate = now()->toDateString();
        $this->showCreateModal = true;
    }

    public function saveSettlement(): void
    {
        $this->validate([
            'selectedDriverId' => 'required|exists:drivers,id',
            'settlementDate' => 'required|date',
            'driverAllowance' => 'required|numeric|min:0',
        ]);

        $companyId = app(TenantContextService::class)->getCompanyId() ?? Auth::user()->company_id;

        try {
            $settlement = app(DriverAccountService::class)->createDriverSettlement([
                'company_id' => $companyId,
                'driver_id' => $this->selectedDriverId,
                'trip_id' => $this->selectedTripId,
                'settlement_date' => $this->settlementDate,
                'total_advances' => $this->totalAdvances,
                'driver_allowance' => $this->driverAllowance,
                'out_of_pocket_expenses' => $this->outOfPocketExpenses,
                'notes' => $this->notes,
            ], Auth::user());

            session()->flash('success', "Driver settlement [{$settlement->settlement_number}] created successfully as draft.");
            $this->showCreateModal = false;
        } catch (\Throwable $e) {
            $this->addError('driverAllowance', $e->getMessage());
        }
    }

    public function postSettlement(int $settlementId): void
    {
        $companyId = app(TenantContextService::class)->getCompanyId() ?? Auth::user()->company_id;
        $settlement = DriverSettlement::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->findOrFail($settlementId);

        try {
            app(DriverAccountService::class)->postDriverSettlement($settlement, Auth::user());
            session()->flash('success', "Settlement [{$settlement->settlement_number}] posted to General Ledger.");
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $companyId = app(TenantContextService::class)->getCompanyId() ?? Auth::user()->company_id;

        $drivers = Driver::withoutGlobalScopes()->where('company_id', $companyId)->orderBy('name')->get();
        $trips = Trip::withoutGlobalScopes()->where('company_id', $companyId)->latest('id')->take(30)->get();

        $query = DriverSettlement::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->with(['driver', 'trip', 'voucher']);

        if ($this->driverFilter) {
            $query->where('driver_id', $this->driverFilter);
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('settlement_number', 'like', "%{$this->search}%")
                  ->orWhereHas('driver', fn($d) => $d->where('name', 'like', "%{$this->search}%"));
            });
        }

        $settlements = $query->orderByDesc('settlement_date')->orderByDesc('id')->paginate(15);

        return view('livewire.finance.driver-settlement-manager', compact(
            'settlements',
            'drivers',
            'trips'
        ));
    }
}


<?php

namespace App\Livewire\Finance;

use App\Models\Branch;
use App\Models\OwnerContract;
use App\Models\OwnerPayment;
use App\Models\OwnerSettlement;
use App\Models\OwnerSettlementDeduction;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Models\VehicleOwner;
use App\Services\OwnerLedgerService;
use App\Services\OwnerSettlementService;
use App\Services\TenantContextService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class OwnerSettlementManager extends Component
{
    use WithPagination;

    public string $activeTab = 'settlements'; // 'settlements', 'ledger', 'payments'
    public string $search = '';
    public string $statusFilter = '';
    public ?int $ownerFilter = null;

    // Settlement Creation Modal
    public bool $showCreateModal = false;
    public ?int $selectedTripId = null;
    public ?int $selectedVehicleId = null;
    public ?int $selectedOwnerId = null;
    public ?int $selectedContractId = null;
    public string $settlementDate = '';
    public string $paymentModel = OwnerSettlement::MODEL_FIXED_PER_TRIP;
    public float $customerFreightTotal = 0.00;
    public float $agreedOwnerPayable = 0.00;
    public float $additionalCharges = 0.00;
    public string $calculationNotes = '';
    public ?float $customNegotiatedRate = null;

    // Deductions table in modal
    public array $pendingDeductions = [];

    // Reversal Modal
    public bool $showReverseModal = false;
    public ?int $settlementToReverseId = null;
    public string $reversalReason = '';

    // Payment Modal
    public bool $showPaymentModal = false;
    public ?int $paymentSettlementId = null;
    public ?int $paymentOwnerId = null;
    public float $paymentAmount = 0.00;
    public string $paymentDate = '';
    public string $paymentMethod = OwnerPayment::METHOD_BANK_TRANSFER;
    public string $paymentReference = '';
    public string $chequeNumber = '';
    public string $chequeDate = '';
    public string $bankName = '';
    public string $bankAccountNumber = '';
    public string $paymentNotes = '';

    // Ledger View State
    public ?int $ledgerOwnerId = null;
    public string $ledgerFromDate = '';
    public string $ledgerToDate = '';

    public function mount(): void
    {
        $this->settlementDate = now()->toDateString();
        $this->paymentDate = now()->toDateString();
        $this->ledgerFromDate = now()->startOfMonth()->toDateString();
        $this->ledgerToDate = now()->toDateString();

        $firstOwner = VehicleOwner::where('company_id', $this->getCompanyId())->first();
        if ($firstOwner) {
            $this->ledgerOwnerId = $firstOwner->id;
        }
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    protected function getCompanyId(): int
    {
        return app(TenantContextService::class)->getCompanyId() ?? Auth::user()->company_id;
    }

    public function updatedSelectedTripId($tripId): void
    {
        if (!$tripId) {
            return;
        }

        $trip = Trip::with(['resources.vehicle.owner', 'consignments'])->find($tripId);
        if (!$trip) {
            return;
        }

        $vehicle = $trip->primaryVehicle() ?? $trip->resources()->where('resource_type', 'vehicle')->first()?->vehicle;

        if (!$vehicle || !$vehicle->vehicle_owner_id) {
            session()->flash('error', 'Selected trip does not have an attached third-party vehicle.');
            $this->selectedTripId = null;
            return;
        }

        $this->selectedVehicleId = $vehicle->id;
        $this->selectedOwnerId = $vehicle->vehicle_owner_id;
        $this->selectedContractId = $vehicle->active_contract_id ?? $vehicle->owner?->activeContract?->id;

        $service = app(OwnerSettlementService::class);
        $calc = $service->calculateOwnerPayable($trip, $vehicle->contract);

        $this->paymentModel = $calc['payment_model'];
        $this->customerFreightTotal = $calc['customer_freight_total'];
        $this->agreedOwnerPayable = $calc['agreed_owner_payable'];
        $this->calculationNotes = $calc['calculation_details']['formula'] ?? '';

        // Auto-detect pending deductions
        $this->pendingDeductions = $service->detectPendingDeductions($trip);
    }

    public function applyNegotiatedRate(): void
    {
        if ($this->customNegotiatedRate > 0) {
            $this->agreedOwnerPayable = round((float) $this->customNegotiatedRate, 2);
            $this->paymentModel = OwnerSettlement::MODEL_NEGOTIATED;
            $this->calculationNotes = "Special Negotiated Rate: PKR " . number_format($this->agreedOwnerPayable, 2);
        }
    }

    public function addManualDeduction(): void
    {
        $this->pendingDeductions[] = [
            'deduction_type' => OwnerSettlementDeduction::TYPE_OTHER,
            'amount' => 0.00,
            'reference_type' => null,
            'reference_id' => null,
            'description' => 'Manual deduction / penalty',
        ];
    }

    public function removeDeduction(int $index): void
    {
        unset($this->pendingDeductions[$index]);
        $this->pendingDeductions = array_values($this->pendingDeductions);
    }

    public function openCreateModal(): void
    {
        $this->reset([
            'selectedTripId',
            'selectedVehicleId',
            'selectedOwnerId',
            'selectedContractId',
            'customerFreightTotal',
            'agreedOwnerPayable',
            'additionalCharges',
            'customNegotiatedRate',
            'pendingDeductions',
        ]);
        $this->settlementDate = now()->toDateString();
        $this->showCreateModal = true;
    }

    public function saveSettlement(): void
    {
        $this->validate([
            'selectedOwnerId' => 'required|exists:vehicle_owners,id',
            'selectedVehicleId' => 'required|exists:vehicles,id',
            'settlementDate' => 'required|date',
            'agreedOwnerPayable' => 'required|numeric|min:0',
        ]);

        try {
            $service = app(OwnerSettlementService::class);

            $trip = $this->selectedTripId ? Trip::find($this->selectedTripId) : null;
            $contract = $this->selectedContractId ? OwnerContract::find($this->selectedContractId) : null;

            $contractSnapshot = null;
            $calculationDetails = null;

            if ($trip) {
                $calc = $service->calculateOwnerPayable($trip, $contract, [
                    'payment_model' => $this->paymentModel,
                    'negotiated_rate' => $this->paymentModel === OwnerSettlement::MODEL_NEGOTIATED ? $this->agreedOwnerPayable : null,
                ]);
                $contractSnapshot = $calc['contract_snapshot'];
                $calculationDetails = $calc['calculation_details'];
            }

            $service->createSettlement([
                'company_id' => $this->getCompanyId(),
                'branch_id' => $trip?->branch_id ?? Auth::user()->branch_id,
                'vehicle_owner_id' => $this->selectedOwnerId,
                'vehicle_id' => $this->selectedVehicleId,
                'trip_id' => $this->selectedTripId,
                'owner_contract_id' => $this->selectedContractId,
                'settlement_date' => $this->settlementDate,
                'payment_model' => $this->paymentModel,
                'customer_freight_total' => $this->customerFreightTotal,
                'agreed_owner_payable' => $this->agreedOwnerPayable,
                'additional_charges' => $this->additionalCharges,
                'contract_snapshot' => $contractSnapshot,
                'calculation_details' => $calculationDetails,
                'deductions' => $this->pendingDeductions,
            ], Auth::user());

            session()->flash('success', 'Owner settlement draft created successfully.');
            $this->showCreateModal = false;
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function submitSettlement(int $id): void
    {
        try {
            $settlement = OwnerSettlement::where('company_id', $this->getCompanyId())->findOrFail($id);
            app(OwnerSettlementService::class)->submitSettlement($settlement, Auth::user());
            session()->flash('success', "Settlement {$settlement->settlement_number} submitted for approval.");
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function approveSettlement(int $id): void
    {
        try {
            $settlement = OwnerSettlement::where('company_id', $this->getCompanyId())->findOrFail($id);
            app(OwnerSettlementService::class)->approveSettlement($settlement, Auth::user());
            session()->flash('success', "Settlement {$settlement->settlement_number} approved successfully.");
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function postSettlement(int $id): void
    {
        try {
            $settlement = OwnerSettlement::where('company_id', $this->getCompanyId())->findOrFail($id);
            app(OwnerSettlementService::class)->postSettlement($settlement, Auth::user());
            session()->flash('success', "Settlement {$settlement->settlement_number} posted to owner ledger. Entries are now permanent.");
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function openReverseModal(int $id): void
    {
        $this->settlementToReverseId = $id;
        $this->reversalReason = '';
        $this->showReverseModal = true;
    }

    public function reverseSettlement(): void
    {
        $this->validate([
            'reversalReason' => 'required|string|min:5',
        ]);

        try {
            $settlement = OwnerSettlement::where('company_id', $this->getCompanyId())->findOrFail($this->settlementToReverseId);
            app(OwnerSettlementService::class)->reverseSettlement($settlement, $this->reversalReason, Auth::user());
            session()->flash('success', "Settlement {$settlement->settlement_number} reversed with audit entries recorded.");
            $this->showReverseModal = false;
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function openPaymentModal(?int $settlementId = null, ?int $ownerId = null): void
    {
        $this->paymentSettlementId = $settlementId;
        $this->paymentOwnerId = $ownerId;

        if ($settlementId) {
            $s = OwnerSettlement::find($settlementId);
            if ($s) {
                $this->paymentOwnerId = $s->vehicle_owner_id;
                $this->paymentAmount = (float) $s->balance_due;
            }
        } else {
            $this->paymentAmount = 0.00;
        }

        $this->paymentDate = now()->toDateString();
        $this->paymentMethod = OwnerPayment::METHOD_BANK_TRANSFER;
        $this->showPaymentModal = true;
    }

    public function savePayment(): void
    {
        $this->validate([
            'paymentOwnerId' => 'required|exists:vehicle_owners,id',
            'paymentAmount' => 'required|numeric|min:1',
            'paymentDate' => 'required|date',
            'paymentMethod' => 'required|string',
        ]);

        try {
            $service = app(OwnerSettlementService::class);
            $service->recordOwnerPayment([
                'company_id' => $this->getCompanyId(),
                'branch_id' => Auth::user()->branch_id,
                'vehicle_owner_id' => $this->paymentOwnerId,
                'owner_settlement_id' => $this->paymentSettlementId,
                'payment_date' => $this->paymentDate,
                'amount' => $this->paymentAmount,
                'payment_method' => $this->paymentMethod,
                'payment_reference' => $this->paymentReference,
                'cheque_number' => $this->chequeNumber,
                'cheque_date' => $this->chequeDate ?: null,
                'bank_name' => $this->bankName,
                'bank_account_number' => $this->bankAccountNumber,
                'notes' => $this->paymentNotes,
            ], Auth::user());

            session()->flash('success', 'Owner payment recorded and debited to ledger successfully.');
            $this->showPaymentModal = false;
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $companyId = $this->getCompanyId();

        // Eligible Trips for new settlements
        $eligibleTrips = Trip::where('company_id', $companyId)
            ->whereIn('trip_status', [Trip::STATUS_DELIVERED, Trip::STATUS_OPERATIONALLY_COMPLETED, Trip::STATUS_CLOSED])
            ->whereDoesntHave('ownerSettlements', function ($q) {
                $q->whereNotIn('status', [OwnerSettlement::STATUS_CANCELLED, OwnerSettlement::STATUS_REVERSED]);
            })
            ->latest('id')
            ->take(30)
            ->get();

        $owners = VehicleOwner::where('company_id', $companyId)->orderBy('name')->get();
        $vehicles = Vehicle::where('company_id', $companyId)
            ->where('ownership_type', '!=', Vehicle::OWNERSHIP_COMPANY)
            ->get();

        // Tab 1: Settlements
        $settlementsQuery = OwnerSettlement::with(['owner', 'vehicle', 'trip'])
            ->where('company_id', $companyId)
            ->when($this->search, function ($q) {
                $q->where('settlement_number', 'like', "%{$this->search}%")
                    ->orWhereHas('owner', fn($sq) => $sq->where('name', 'like', "%{$this->search}%"));
            })
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->ownerFilter, fn($q) => $q->where('vehicle_owner_id', $this->ownerFilter))
            ->orderByDesc('id');

        $settlements = $settlementsQuery->paginate(15);

        // Tab 2: Ledger
        $ledgerSummary = null;
        if ($this->activeTab === 'ledger' && $this->ledgerOwnerId) {
            $selectedOwner = VehicleOwner::where('company_id', $companyId)->find($this->ledgerOwnerId);
            if ($selectedOwner) {
                $ledgerSummary = app(OwnerLedgerService::class)->getOwnerLedgerSummary(
                    $selectedOwner,
                    $this->ledgerFromDate ?: null,
                    $this->ledgerToDate ?: null
                );
            }
        }

        // Tab 3: Payments
        $paymentsQuery = OwnerPayment::with(['owner', 'settlement'])
            ->where('company_id', $companyId)
            ->when($this->ownerFilter, fn($q) => $q->where('vehicle_owner_id', $this->ownerFilter))
            ->orderByDesc('id');

        $payments = $paymentsQuery->paginate(15);

        return view('livewire.finance.owner-settlement-manager', [
            'settlements' => $settlements,
            'ledgerSummary' => $ledgerSummary,
            'payments' => $payments,
            'eligibleTrips' => $eligibleTrips,
            'owners' => $owners,
            'vehicles' => $vehicles,
        ]);
    }
}


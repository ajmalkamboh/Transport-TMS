<?php

namespace App\Livewire\Operations;

use App\Models\Driver;
use App\Models\Trip;
use App\Models\TripExpense;
use App\Models\Vehicle;
use App\Services\TenantAuthorizationService;
use App\Services\TenantContextService;
use App\Services\TripExpenseService;
use Livewire\Component;
use Livewire\WithPagination;

class TripExpenseManager extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public string $categoryFilter = '';
    public ?int $tripFilter = null;

    // Record Expense Modal
    public bool $showExpenseModal = false;
    public ?int $trip_id = null;
    public ?int $vehicle_id = null;
    public ?int $driver_id = null;
    public string $expense_category = 'toll_tax';
    public float $amount = 1500.00;
    public string $payment_method = 'cash';
    public string $expense_date = '';
    public string $notes = '';
    public bool $auto_submit = true;

    // Reject Modal
    public bool $showRejectModal = false;
    public ?TripExpense $rejectingExpense = null;
    public string $rejection_reason = '';

    // View Modal
    public bool $showDetailModal = false;
    public ?TripExpense $selectedExpense = null;

    public function mount(): void
    {
        $this->expense_date = now()->toDateString();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function openExpenseModal(): void
    {
        $this->resetErrorBag();
        $this->expense_date = now()->toDateString();
        $this->trip_id = null;
        $this->vehicle_id = null;
        $this->driver_id = null;
        $this->expense_category = 'toll_tax';
        $this->amount = 1500.00;
        $this->payment_method = 'cash';
        $this->notes = '';
        $this->auto_submit = true;
        $this->showExpenseModal = true;
    }

    public function updatedTripId($value): void
    {
        if ($value) {
            $trip = Trip::find($value);
            if ($trip) {
                $primaryVeh = $trip->primaryVehicle();
                $primaryDrv = $trip->primaryDriver();
                $this->vehicle_id = $primaryVeh?->id;
                $this->driver_id = $primaryDrv?->id;
            }
        }
    }

    public function saveExpense(
        TripExpenseService $expenseService,
        TenantContextService $tenantContext
    ): void {
        $this->validate([
            'expense_category' => 'required|string',
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|string',
            'expense_date' => 'required|date',
        ]);

        $company = $tenantContext->getCompany();
        if (!$company) {
            session()->flash('error', 'No active company tenant context.');
            return;
        }

        $expense = $expenseService->recordExpense($company, [
            'trip_id' => $this->trip_id ?: null,
            'vehicle_id' => $this->vehicle_id ?: null,
            'driver_id' => $this->driver_id ?: null,
            'expense_category' => $this->expense_category,
            'amount' => $this->amount,
            'payment_method' => $this->payment_method,
            'expense_date' => $this->expense_date,
            'notes' => $this->notes ?: null,
            'status' => $this->auto_submit ? TripExpense::STATUS_SUBMITTED : TripExpense::STATUS_DRAFT,
        ], auth()->id());

        $this->showExpenseModal = false;
        session()->flash('success', "Expense voucher [{$expense->voucher_number}] recorded successfully.");
    }

    public function submit(int $id, TripExpenseService $expenseService): void
    {
        $expense = TripExpense::findOrFail($id);
        $expenseService->submitExpense($expense);
        session()->flash('success', "Expense [{$expense->voucher_number}] submitted for approval.");
    }

    public function approve(int $id, TripExpenseService $expenseService): void
    {
        $expense = TripExpense::findOrFail($id);
        $expenseService->approveExpense($expense, auth()->id());
        session()->flash('success', "Expense voucher [{$expense->voucher_number}] approved.");
    }

    public function openRejectModal(int $id): void
    {
        $this->rejectingExpense = TripExpense::findOrFail($id);
        $this->rejection_reason = '';
        $this->showRejectModal = true;
    }

    public function confirmReject(TripExpenseService $expenseService): void
    {
        $this->validate([
            'rejection_reason' => 'required|string|max:255',
        ]);

        $expenseService->rejectExpense($this->rejectingExpense, $this->rejection_reason, auth()->id());
        $this->showRejectModal = false;
        session()->flash('warning', "Expense voucher [{$this->rejectingExpense->voucher_number}] rejected.");
    }

    public function post(int $id, TripExpenseService $expenseService): void
    {
        try {
            $expense = TripExpense::findOrFail($id);
            $expenseService->postExpense($expense, auth()->id());
            session()->flash('success', "Expense voucher [{$expense->voucher_number}] posted to general ledger. Duplicate lock engaged.");
        } catch (\LogicException | \InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function viewDetails(int $id, TenantAuthorizationService $auth): void
    {
        $this->selectedExpense = TripExpense::with(['trip', 'vehicle', 'driver', 'recorder', 'approver', 'poster'])
            ->findOrFail($id);
        $auth->validateTenantOwnership($this->selectedExpense);
        $this->showDetailModal = true;
    }

    public function render()
    {
        $query = TripExpense::with(['trip', 'vehicle', 'driver', 'recorder', 'approver'])
            ->latest('expense_date');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('voucher_number', 'like', "%{$this->search}%")
                    ->orWhere('notes', 'like', "%{$this->search}%")
                    ->orWhereHas('vehicle', function ($vq) {
                        $vq->where('registration_number', 'like', "%{$this->search}%");
                    })
                    ->orWhereHas('trip', function ($tq) {
                        $tq->where('trip_number', 'like', "%{$this->search}%");
                    });
            });
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->categoryFilter) {
            $query->where('expense_category', $this->categoryFilter);
        }

        if ($this->tripFilter) {
            $query->where('trip_id', $this->tripFilter);
        }

        $expenses = $query->paginate(15);

        $vehicles = Vehicle::all();
        $drivers = Driver::all();
        $trips = Trip::latest()->take(25)->get();

        // Metrics
        $totalExpenses = TripExpense::sum('amount');
        $postedExpenses = TripExpense::where('status', TripExpense::STATUS_POSTED)->sum('amount');
        $pendingCount = TripExpense::where('status', TripExpense::STATUS_SUBMITTED)->count();

        return view('livewire.operations.trip-expense-manager', [
            'expenses' => $expenses,
            'vehicles' => $vehicles,
            'drivers' => $drivers,
            'trips' => $trips,
            'totalExpenses' => $totalExpenses,
            'postedExpenses' => $postedExpenses,
            'pendingCount' => $pendingCount,
        ]);
    }
}

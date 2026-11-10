<?php

namespace App\Livewire\Fleet;

use App\Models\Driver;
use App\Models\FuelCard;
use App\Models\FuelEntry;
use App\Models\FuelStation;
use App\Models\FuelSupplier;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Services\FuelManagementService;
use App\Services\TenantAuthorizationService;
use App\Services\TenantContextService;
use Livewire\Component;
use Livewire\WithPagination;

class FuelManager extends Component
{
    use WithPagination;

    public string $search = '';
    public ?int $vehicleFilter = null;
    public bool $suspiciousOnly = false;

    // Log Refuel Modal
    public bool $showRefuelModal = false;
    public ?int $vehicle_id = null;
    public ?int $driver_id = null;
    public ?int $trip_id = null;
    public ?int $fuel_supplier_id = null;
    public ?int $fuel_station_id = null;
    public ?int $fuel_card_id = null;
    public string $fuel_type = 'diesel';
    public float $quantity_liters = 250.00;
    public float $rate_per_liter = 285.50;
    public int $odometer_km = 0;
    public string $entry_date = '';
    public string $payment_method = 'fuel_card';
    public string $payment_status = 'unpaid';
    public string $invoice_reference = '';
    public bool $is_full_tank = true;

    // Add Supplier Modal
    public bool $showSupplierModal = false;
    public string $supplier_name = '';
    public string $supplier_contact = '';
    public string $supplier_phone = '';
    public string $supplier_ntn = '';
    public float $supplier_credit_limit = 500000.00;

    // Add Card Modal
    public bool $showCardModal = false;
    public string $card_number = '';
    public ?int $card_supplier_id = null;
    public ?int $card_vehicle_id = null;
    public float $card_monthly_limit = 200000.00;

    // View Modal
    public bool $showDetailModal = false;
    public ?FuelEntry $selectedEntry = null;

    public function mount(): void
    {
        $this->entry_date = now()->toDateString();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingVehicleFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSuspiciousOnly(): void
    {
        $this->resetPage();
    }

    public function openRefuelModal(): void
    {
        $this->resetErrorBag();
        $this->entry_date = now()->toDateString();
        $this->vehicle_id = null;
        $this->driver_id = null;
        $this->trip_id = null;
        $this->odometer_km = 0;
        $this->showRefuelModal = true;
    }

    public function updatedVehicleId($value): void
    {
        if ($value) {
            $veh = Vehicle::find($value);
            if ($veh) {
                $this->odometer_km = (int) $veh->current_odometer + 450;
                $this->driver_id = $veh->current_driver_id;
            }
        }
    }

    public function saveFuelEntry(
        FuelManagementService $fuelService,
        TenantContextService $tenantContext
    ): void {
        $this->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'quantity_liters' => 'required|numeric|min:1',
            'rate_per_liter' => 'required|numeric|min:1',
            'odometer_km' => 'required|integer|min:0',
            'entry_date' => 'required|date',
            'fuel_type' => 'required|string',
            'payment_method' => 'required|string',
        ]);

        $company = $tenantContext->getCompany();
        if (!$company) {
            session()->flash('error', 'No active company tenant context.');
            return;
        }

        $entry = $fuelService->recordFuelEntry($company, [
            'vehicle_id' => $this->vehicle_id,
            'driver_id' => $this->driver_id ?: null,
            'trip_id' => $this->trip_id ?: null,
            'fuel_supplier_id' => $this->fuel_supplier_id ?: null,
            'fuel_station_id' => $this->fuel_station_id ?: null,
            'fuel_card_id' => $this->fuel_card_id ?: null,
            'fuel_type' => $this->fuel_type,
            'quantity_liters' => $this->quantity_liters,
            'rate_per_liter' => $this->rate_per_liter,
            'odometer_km' => $this->odometer_km,
            'entry_date' => $this->entry_date,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'invoice_reference' => $this->invoice_reference ?: null,
            'is_full_tank' => $this->is_full_tank,
        ]);

        $this->showRefuelModal = false;
        if ($entry->is_suspicious) {
            session()->flash('warning', "Fuel entry [{$entry->entry_number}] logged with audit warning: {$entry->anomaly_reason}");
        } else {
            session()->flash('success', "Refuel entry [{$entry->entry_number}] saved. Economy: {$entry->fuel_economy_km_per_liter} KM/L.");
        }
    }

    public function saveSupplier(
        FuelManagementService $fuelService,
        TenantContextService $tenantContext
    ): void {
        $this->validate([
            'supplier_name' => 'required|string|max:150',
            'supplier_phone' => 'nullable|string|max:50',
        ]);

        $company = $tenantContext->getCompany();
        $supplier = $fuelService->createSupplier($company, [
            'name' => $this->supplier_name,
            'contact_person' => $this->supplier_contact ?: null,
            'phone' => $this->supplier_phone ?: null,
            'ntn_number' => $this->supplier_ntn ?: null,
            'credit_limit' => $this->supplier_credit_limit,
        ]);

        $this->showSupplierModal = false;
        session()->flash('success', "Fuel supplier [{$supplier->name}] registered successfully.");
    }

    public function saveCard(
        FuelManagementService $fuelService,
        TenantContextService $tenantContext
    ): void {
        $this->validate([
            'card_number' => 'required|string|max:50',
        ]);

        $company = $tenantContext->getCompany();
        $card = $fuelService->createFuelCard($company, [
            'card_number' => $this->card_number,
            'fuel_supplier_id' => $this->card_supplier_id ?: null,
            'assigned_vehicle_id' => $this->card_vehicle_id ?: null,
            'monthly_limit' => $this->card_monthly_limit,
        ]);

        $this->showCardModal = false;
        session()->flash('success', "Fuel card [{$card->card_number}] registered successfully.");
    }

    public function viewDetails(int $id, TenantAuthorizationService $auth): void
    {
        $this->selectedEntry = FuelEntry::with(['vehicle', 'driver', 'supplier', 'station', 'card', 'trip'])
            ->findOrFail($id);
        $auth->validateTenantOwnership($this->selectedEntry);
        $this->showDetailModal = true;
    }

    public function render()
    {
        $query = FuelEntry::with(['vehicle', 'driver', 'supplier', 'card', 'trip'])
            ->latest('entry_date');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('entry_number', 'like', "%{$this->search}%")
                    ->orWhere('invoice_reference', 'like', "%{$this->search}%")
                    ->orWhereHas('vehicle', function ($vq) {
                        $vq->where('registration_number', 'like', "%{$this->search}%");
                    })
                    ->orWhereHas('driver', function ($dq) {
                        $dq->where('name', 'like', "%{$this->search}%");
                    });
            });
        }

        if ($this->vehicleFilter) {
            $query->where('vehicle_id', $this->vehicleFilter);
        }

        if ($this->suspiciousOnly) {
            $query->where('is_suspicious', true);
        }

        $entries = $query->paginate(15);

        $vehicles = Vehicle::all();
        $drivers = Driver::all();
        $suppliers = FuelSupplier::where('status', 'active')->get();
        $stations = FuelStation::where('status', 'active')->get();
        $cards = FuelCard::where('status', 'active')->get();
        $trips = Trip::latest()->take(25)->get();

        // Summary KPI Metrics
        $totalLiters = FuelEntry::sum('quantity_liters');
        $totalCost = FuelEntry::sum('total_amount');
        $totalDistance = FuelEntry::sum('distance_km');
        $avgEconomy = ($totalDistance > 0 && $totalLiters > 0) ? round($totalDistance / $totalLiters, 2) : 0.00;
        $flaggedCount = FuelEntry::where('is_suspicious', true)->count();

        return view('livewire.fleet.fuel-manager', [
            'entries' => $entries,
            'vehicles' => $vehicles,
            'drivers' => $drivers,
            'suppliers' => $suppliers,
            'stations' => $stations,
            'cards' => $cards,
            'trips' => $trips,
            'totalLiters' => $totalLiters,
            'totalCost' => $totalCost,
            'avgEconomy' => $avgEconomy,
            'flaggedCount' => $flaggedCount,
        ]);
    }
}

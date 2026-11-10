<?php

namespace App\Livewire\Operations;

use App\Models\Branch;
use App\Models\Consignment;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\TripCheckpoint;
use App\Models\TripConsignment;
use App\Models\TripResource;
use App\Models\Vehicle;
use App\Services\DeliveryManagementService;
use App\Services\TripDispatchService;
use App\Services\TripPlanningService;
use App\Services\TenantAuthorizationService;
use App\Services\TenantContextService;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Component;
use Livewire\WithPagination;

class TripManager extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public ?int $branchFilter = null;

    // Create Trip Modal
    public bool $showCreateModal = false;
    public ?int $branch_id = null;
    public string $trip_date = '';
    public string $origin_city = 'Karachi';
    public string $destination_city = 'Lahore';
    public string $planned_route = 'National Highway N-5 / Motorway M-5';
    public string $estimated_departure = '';
    public string $estimated_arrival = '';
    public string $trip_notes = '';

    // Active Selected Trip
    public ?Trip $selectedTrip = null;

    // Resource Management Modal
    public bool $showResourceModal = false;
    public string $resource_type = 'vehicle';
    public ?int $resource_id = null;
    public string $role = 'primary_vehicle';
    public bool $override_allowed = false;
    public string $override_reason = '';

    // Consignment Allocation Modal
    public bool $showAllocationModal = false;
    public ?int $selected_consignment_id = null;
    public int $allocated_quantity = 1;

    // Loading Modal
    public bool $showLoadingModal = false;
    public string $loading_supervisor = '';
    public string $loading_started_at = '';
    public string $loading_completed_at = '';

    // Dispatch Modal
    public bool $showDispatchModal = false;
    public int $departure_odometer = 0;
    public string $actual_departure = '';
    public bool $driver_signoff_confirmed = false;

    // Checkpoint Modal
    public bool $showCheckpointModal = false;
    public string $checkpoint_name = '';
    public ?int $checkpoint_odometer = null;
    public int $delay_minutes = 0;
    public string $delay_reason = '';
    public string $checkpoint_notes = '';

    // Operational Completion Modal
    public bool $showCompleteModal = false;
    public int $arrival_odometer = 0;
    public string $actual_arrival = '';

    // Financial Closure Modal
    public bool $showFinanceModal = false;
    public string $financial_notes = '';

    // Detail Modal
    public bool $showDetailModal = false;

    public function mount(): void
    {
        $this->trip_date = now()->toDateString();
        $this->estimated_departure = now()->addHours(4)->format('Y-m-d\TH:i');
        $this->estimated_arrival = now()->addDays(2)->format('Y-m-d\TH:i');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->resetErrorBag();
        $this->trip_date = now()->toDateString();
        $this->estimated_departure = now()->addHours(4)->format('Y-m-d\TH:i');
        $this->estimated_arrival = now()->addDays(2)->format('Y-m-d\TH:i');
        $this->showCreateModal = true;
    }

    public function createTrip(
        TripPlanningService $planningService,
        TenantContextService $tenantContext
    ): void {
        $this->validate([
            'origin_city' => 'required|string|max:100',
            'destination_city' => 'required|string|max:100',
            'trip_date' => 'required|date',
            'branch_id' => 'nullable|exists:branches,id',
            'planned_route' => 'nullable|string|max:255',
            'trip_notes' => 'nullable|string',
        ]);

        $company = $tenantContext->getCompany();
        if (!$company) {
            session()->flash('error', 'No active company tenant context.');
            return;
        }

        $trip = $planningService->createTrip($company, [
            'branch_id' => $this->branch_id,
            'trip_date' => $this->trip_date,
            'origin_city' => $this->origin_city,
            'destination_city' => $this->destination_city,
            'planned_route' => $this->planned_route,
            'estimated_departure' => $this->estimated_departure ?: null,
            'estimated_arrival' => $this->estimated_arrival ?: null,
            'trip_notes' => $this->trip_notes,
        ]);

        $this->showCreateModal = false;
        session()->flash('success', "Trip [{$trip->trip_number}] created successfully.");
    }

    public function openResourceModal(int $tripId, TenantAuthorizationService $auth): void
    {
        $this->resetErrorBag();
        $this->selectedTrip = Trip::with(['resources.vehicle', 'resources.driver'])->findOrFail($tripId);
        $auth->validateTenantOwnership($this->selectedTrip);
        $this->resource_type = 'vehicle';
        $this->role = 'primary_vehicle';
        $this->resource_id = null;
        $this->override_allowed = false;
        $this->override_reason = '';
        $this->showResourceModal = true;
    }

    public function assignResource(TripPlanningService $planningService): void
    {
        $this->validate([
            'resource_type' => 'required|in:vehicle,driver',
            'resource_id' => 'required|integer',
            'role' => 'required|string|max:50',
            'override_reason' => 'required_if:override_allowed,true',
        ]);

        try {
            $planningService->assignResource(
                $this->selectedTrip,
                $this->resource_type,
                $this->resource_id,
                $this->role,
                $this->override_allowed,
                $this->override_reason ?: null
            );

            $this->selectedTrip->load(['resources.vehicle', 'resources.driver']);
            $this->resource_id = null;
            $this->override_allowed = false;
            $this->override_reason = '';
            session()->flash('resource_success', 'Resource assigned to trip successfully.');
        } catch (\InvalidArgumentException $e) {
            session()->flash('resource_error', $e->getMessage());
        }
    }

    public function releaseResource(int $resourceId): void
    {
        $resource = TripResource::where('trip_id', $this->selectedTrip->id)->findOrFail($resourceId);
        $resource->release();
        $this->selectedTrip->load(['resources.vehicle', 'resources.driver']);
        session()->flash('resource_success', 'Resource released back to available.');
    }

    public function openAllocationModal(int $tripId, TenantAuthorizationService $auth): void
    {
        $this->resetErrorBag();
        $this->selectedTrip = Trip::with(['tripConsignments.consignment.customer'])->findOrFail($tripId);
        $auth->validateTenantOwnership($this->selectedTrip);
        $this->selected_consignment_id = null;
        $this->allocated_quantity = 1;
        $this->showAllocationModal = true;
    }

    public function allocateConsignment(TripPlanningService $planningService): void
    {
        $this->validate([
            'selected_consignment_id' => 'required|exists:consignments,id',
            'allocated_quantity' => 'required|integer|min:1',
        ]);

        $consignment = Consignment::findOrFail($this->selected_consignment_id);

        try {
            $planningService->allocateConsignment($this->selectedTrip, $consignment, $this->allocated_quantity);
            $this->selectedTrip->load(['tripConsignments.consignment.customer']);
            $this->selected_consignment_id = null;
            $this->allocated_quantity = 1;
            session()->flash('allocation_success', 'Consignment cargo allocated to trip.');
        } catch (\InvalidArgumentException $e) {
            session()->flash('allocation_error', $e->getMessage());
        }
    }

    public function openLoadingModal(int $tripId, TenantAuthorizationService $auth): void
    {
        $this->resetErrorBag();
        $this->selectedTrip = Trip::with(['tripConsignments.consignment'])->findOrFail($tripId);
        $auth->validateTenantOwnership($this->selectedTrip);
        $this->loading_supervisor = auth()->user()->name ?? 'Terminal Incharge';
        $this->loading_started_at = now()->subHours(2)->format('Y-m-d\TH:i');
        $this->loading_completed_at = now()->format('Y-m-d\TH:i');
        $this->showLoadingModal = true;
    }

    public function completeLoading(TripPlanningService $planningService): void
    {
        $this->validate([
            'loading_supervisor' => 'required|string|max:100',
        ]);

        $planningService->recordLoading(
            $this->selectedTrip,
            $this->loading_supervisor,
            $this->loading_started_at ?: null,
            $this->loading_completed_at ?: null
        );

        $this->showLoadingModal = false;
        session()->flash('success', "Loading verified for Trip [{$this->selectedTrip->trip_number}]. Loading slip ready.");
    }

    public function openDispatchModal(int $tripId, TenantAuthorizationService $auth): void
    {
        $this->resetErrorBag();
        $this->selectedTrip = Trip::with(['activeResources.vehicle', 'activeResources.driver', 'tripConsignments'])->findOrFail($tripId);
        $auth->validateTenantOwnership($this->selectedTrip);

        $primaryVeh = $this->selectedTrip->primaryVehicle();
        $this->departure_odometer = $primaryVeh ? (int) $primaryVeh->current_odometer : 0;
        $this->actual_departure = now()->format('Y-m-d\TH:i');
        $this->driver_signoff_confirmed = false;
        $this->showDispatchModal = true;
    }

    public function dispatchTrip(TripDispatchService $dispatchService): void
    {
        $this->validate([
            'departure_odometer' => 'required|integer|min:0',
            'driver_signoff_confirmed' => 'accepted',
        ]);

        try {
            $dispatchService->dispatchTrip(
                $this->selectedTrip,
                $this->departure_odometer,
                $this->actual_departure ?: null,
                auth()->id()
            );

            $this->showDispatchModal = false;
            session()->flash('success', "Trip [{$this->selectedTrip->trip_number}] dispatched successfully!");
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function openCheckpointModal(int $tripId, TenantAuthorizationService $auth): void
    {
        $this->resetErrorBag();
        $this->selectedTrip = Trip::with(['checkpoints', 'activeResources.vehicle'])->findOrFail($tripId);
        $auth->validateTenantOwnership($this->selectedTrip);
        $this->checkpoint_name = '';
        $this->checkpoint_odometer = $this->selectedTrip->primaryVehicle()?->current_odometer ?? $this->selectedTrip->departure_odometer_km;
        $this->delay_minutes = 0;
        $this->delay_reason = '';
        $this->checkpoint_notes = '';
        $this->showCheckpointModal = true;
    }

    public function recordCheckpoint(TripDispatchService $dispatchService): void
    {
        $this->validate([
            'checkpoint_name' => 'required|string|max:100',
            'delay_minutes' => 'nullable|integer|min:0',
        ]);

        $dispatchService->recordCheckpoint(
            $this->selectedTrip,
            $this->checkpoint_name,
            $this->checkpoint_odometer,
            $this->delay_minutes,
            $this->delay_reason ?: null,
            $this->checkpoint_notes ?: null
        );

        $this->selectedTrip->load('checkpoints');
        $this->checkpoint_name = '';
        $this->delay_minutes = 0;
        $this->delay_reason = '';
        $this->checkpoint_notes = '';
        session()->flash('checkpoint_success', 'Checkpoint recorded on trip route.');
    }

    public function openCompleteModal(int $tripId, TenantAuthorizationService $auth): void
    {
        $this->resetErrorBag();
        $this->selectedTrip = Trip::with(['tripConsignments'])->findOrFail($tripId);
        $auth->validateTenantOwnership($this->selectedTrip);
        $this->arrival_odometer = ($this->selectedTrip->departure_odometer_km ?? 0) + 1250;
        $this->actual_arrival = now()->format('Y-m-d\TH:i');
        $this->showCompleteModal = true;
    }

    public function completeOperationally(DeliveryManagementService $deliveryService): void
    {
        $this->validate([
            'arrival_odometer' => 'required|integer|min:' . ($this->selectedTrip->departure_odometer_km ?? 0),
        ]);

        try {
            $deliveryService->completeOperationally(
                $this->selectedTrip,
                $this->arrival_odometer,
                $this->actual_arrival ?: null
            );

            $this->showCompleteModal = false;
            session()->flash('success', "Trip [{$this->selectedTrip->trip_number}] operationally completed. Fleet vehicles & drivers released!");
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function openFinanceModal(int $tripId, TenantAuthorizationService $auth): void
    {
        $this->resetErrorBag();
        $this->selectedTrip = Trip::findOrFail($tripId);
        $auth->validateTenantOwnership($this->selectedTrip);
        $this->financial_notes = '';
        $this->showFinanceModal = true;
    }

    public function closeFinancially(DeliveryManagementService $deliveryService): void
    {
        try {
            $deliveryService->closeFinancially($this->selectedTrip, $this->financial_notes ?: null);
            $this->showFinanceModal = false;
            session()->flash('success', "Trip [{$this->selectedTrip->trip_number}] audited and financially closed.");
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function viewDetails(int $tripId, TenantAuthorizationService $auth): void
    {
        $this->selectedTrip = Trip::with([
            'branch',
            'dispatcher',
            'dispatchedByUser',
            'resources.vehicle',
            'resources.driver',
            'tripConsignments.consignment.customer',
            'checkpoints',
            'proofOfDeliveries',
        ])->findOrFail($tripId);
        $auth->validateTenantOwnership($this->selectedTrip);
        $this->showDetailModal = true;
    }

    public function render()
    {
        $query = Trip::with(['branch', 'dispatcher', 'activeResources.vehicle', 'activeResources.driver'])
            ->latest('trip_date');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('trip_number', 'like', "%{$this->search}%")
                    ->orWhere('origin_city', 'like', "%{$this->search}%")
                    ->orWhere('destination_city', 'like', "%{$this->search}%")
                    ->orWhere('planned_route', 'like', "%{$this->search}%");
            });
        }

        if ($this->statusFilter) {
            $query->where('trip_status', $this->statusFilter);
        }

        if ($this->branchFilter) {
            $query->where('branch_id', $this->branchFilter);
        }

        $trips = $query->paginate(15);

        $branches = Branch::where('is_active', true)->get();
        $availableVehicles = Vehicle::all();
        $availableDrivers = Driver::all();
        $availableConsignments = Consignment::where('remaining_quantity', '>', 0)
            ->whereIn('status', [Consignment::STATUS_BOOKED, Consignment::STATUS_DISPATCHED_IN_TRANSIT])
            ->get();

        return view('livewire.operations.trip-manager', [
            'trips' => $trips,
            'branches' => $branches,
            'availableVehicles' => $availableVehicles,
            'availableDrivers' => $availableDrivers,
            'availableConsignments' => $availableConsignments,
        ]);
    }
}

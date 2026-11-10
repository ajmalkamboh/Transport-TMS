<?php

namespace App\Livewire\Fleet;

use App\Models\Driver;
use App\Models\JobCard;
use App\Models\JobCardItem;
use App\Models\MaintenanceAlert;
use App\Models\MaintenanceSchedule;
use App\Models\Vehicle;
use App\Models\VehicleComponent;
use App\Services\TenantAuthorizationService;
use App\Services\TenantContextService;
use App\Services\VehicleMaintenanceService;
use Livewire\Component;
use Livewire\WithPagination;

class WorkshopManager extends Component
{
    use WithPagination;

    public string $activeTab = 'job_cards';
    public string $search = '';
    public string $statusFilter = '';

    // Create Job Card Modal
    public bool $showCreateModal = false;
    public ?int $vehicle_id = null;
    public ?int $driver_id = null;
    public ?int $maintenance_schedule_id = null;
    public string $workshop_type = 'in_house';
    public string $workshop_name = 'Central Terminal Mechanical Workshop';
    public string $service_type = 'preventive';
    public string $priority = 'medium';
    public int $odometer_km = 0;
    public string $scheduled_date = '';
    public float $labour_charges = 5000.00;
    public string $issue_description = '';
    public string $technician_name = '';

    // Add Spare Part Modal
    public bool $showPartModal = false;
    public ?JobCard $selectedJobCard = null;
    public string $part_name = '';
    public string $part_number = '';
    public float $part_quantity = 1.0;
    public float $part_unit_cost = 0.00;

    // Complete Job Card Modal
    public bool $showCompleteModal = false;
    public float $complete_labour = 0.00;
    public float $complete_tax = 0.00;
    public string $work_performed = '';
    public string $supervisor_notes = '';

    // Add Schedule Modal
    public bool $showScheduleModal = false;
    public ?int $sched_vehicle_id = null;
    public string $sched_title = 'Routine Engine Oil & Filter Change';
    public string $sched_category = 'oil_change';
    public int $sched_interval_km = 10000;
    public int $sched_interval_days = 90;

    // Install Component Modal
    public bool $showComponentModal = false;
    public ?int $comp_vehicle_id = null;
    public string $component_type = 'tyre';
    public string $brand_name = 'General Tyre';
    public string $model_or_size = '295/80 R22.5';
    public string $serial_number = '';
    public string $position = 'front_left';
    public int $expected_life_km = 80000;

    public function mount(): void
    {
        $this->scheduled_date = now()->toDateString();
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
        $this->scheduled_date = now()->toDateString();
        $this->vehicle_id = null;
        $this->odometer_km = 0;
        $this->issue_description = '';
        $this->showCreateModal = true;
    }

    public function updatedVehicleId($value): void
    {
        if ($value) {
            $veh = Vehicle::find($value);
            if ($veh) {
                $this->odometer_km = (int) $veh->current_odometer;
                $this->driver_id = $veh->current_driver_id;
            }
        }
    }

    public function saveJobCard(
        VehicleMaintenanceService $maintService,
        TenantContextService $tenantContext
    ): void {
        $this->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'service_type' => 'required|string',
            'odometer_km' => 'required|integer|min:0',
            'scheduled_date' => 'required|date',
            'workshop_type' => 'required|string',
        ]);

        $company = $tenantContext->getCompany();
        $jobCard = $maintService->createJobCard($company, [
            'vehicle_id' => $this->vehicle_id,
            'driver_id' => $this->driver_id ?: null,
            'maintenance_schedule_id' => $this->maintenance_schedule_id ?: null,
            'workshop_type' => $this->workshop_type,
            'workshop_name' => $this->workshop_name,
            'service_type' => $this->service_type,
            'priority' => $this->priority,
            'odometer_km' => $this->odometer_km,
            'scheduled_date' => $this->scheduled_date,
            'labour_charges' => $this->labour_charges,
            'issue_description' => $this->issue_description ?: null,
            'technician_name' => $this->technician_name ?: null,
        ], auth()->id());

        $this->showCreateModal = false;
        session()->flash('success', "Job card [{$jobCard->job_card_number}] created. Vehicle placed into maintenance downtime.");
    }

    public function openPartModal(int $jobCardId): void
    {
        $this->selectedJobCard = JobCard::findOrFail($jobCardId);
        $this->part_name = '';
        $this->part_number = '';
        $this->part_quantity = 1.0;
        $this->part_unit_cost = 0.00;
        $this->showPartModal = true;
    }

    public function addPart(VehicleMaintenanceService $maintService): void
    {
        $this->validate([
            'part_name' => 'required|string|max:150',
            'part_quantity' => 'required|numeric|min:1',
            'part_unit_cost' => 'required|numeric|min:0',
        ]);

        $maintService->addJobCardPart($this->selectedJobCard, [
            'part_name' => $this->part_name,
            'part_number' => $this->part_number ?: null,
            'quantity' => $this->part_quantity,
            'unit_cost' => $this->part_unit_cost,
        ]);

        $this->showPartModal = false;
        session()->flash('success', "Spare part added to Job Card [{$this->selectedJobCard->job_card_number}].");
    }

    public function openCompleteModal(int $jobCardId): void
    {
        $this->selectedJobCard = JobCard::with('items')->findOrFail($jobCardId);
        $this->complete_labour = (float) $this->selectedJobCard->labour_charges;
        $this->complete_tax = 0.00;
        $this->work_performed = $this->selectedJobCard->issue_description ?? '';
        $this->supervisor_notes = '';
        $this->showCompleteModal = true;
    }

    public function completeJobCard(VehicleMaintenanceService $maintService): void
    {
        $maintService->completeJobCard($this->selectedJobCard, [
            'labour_charges' => $this->complete_labour,
            'tax_amount' => $this->complete_tax,
            'work_performed' => $this->work_performed ?: null,
            'supervisor_notes' => $this->supervisor_notes ?: null,
        ], auth()->id());

        $this->showCompleteModal = false;
        session()->flash('success', "Job Card [{$this->selectedJobCard->job_card_number}] completed! Vehicle returned to available fleet.");
    }

    public function saveSchedule(
        VehicleMaintenanceService $maintService,
        TenantContextService $tenantContext
    ): void {
        $this->validate([
            'sched_title' => 'required|string|max:150',
            'sched_interval_km' => 'nullable|integer|min:1',
            'sched_interval_days' => 'nullable|integer|min:1',
        ]);

        $company = $tenantContext->getCompany();
        $maintService->createSchedule($company, [
            'vehicle_id' => $this->sched_vehicle_id ?: null,
            'title' => $this->sched_title,
            'maintenance_category' => $this->sched_category,
            'interval_km' => $this->sched_interval_km ?: null,
            'interval_days' => $this->sched_interval_days ?: null,
        ]);

        $this->showScheduleModal = false;
        session()->flash('success', 'Preventive maintenance schedule configured.');
    }

    public function saveComponent(
        VehicleMaintenanceService $maintService,
        TenantContextService $tenantContext
    ): void {
        $this->validate([
            'comp_vehicle_id' => 'required|exists:vehicles,id',
            'component_type' => 'required|string',
            'brand_name' => 'required|string|max:100',
        ]);

        $company = $tenantContext->getCompany();
        $maintService->installComponent($company, [
            'vehicle_id' => $this->comp_vehicle_id,
            'component_type' => $this->component_type,
            'brand_name' => $this->brand_name,
            'model_or_size' => $this->model_or_size ?: null,
            'serial_number' => $this->serial_number ?: null,
            'position' => $this->position ?: null,
            'expected_life_km' => $this->expected_life_km ?: null,
        ]);

        $this->showComponentModal = false;
        session()->flash('success', 'Component logged and tracking initialized.');
    }

    public function render(VehicleMaintenanceService $maintService, TenantContextService $tenantContext)
    {
        $company = $tenantContext->getCompany();

        // Run alert scan
        if ($company) {
            $maintService->evaluateFleetAlerts($company);
        }

        $jobCardsQuery = JobCard::with(['vehicle', 'driver', 'items'])->latest('scheduled_date');
        if ($this->search) {
            $jobCardsQuery->where(function ($q) {
                $q->where('job_card_number', 'like', "%{$this->search}%")
                    ->orWhere('technician_name', 'like', "%{$this->search}%")
                    ->orWhereHas('vehicle', function ($vq) {
                        $vq->where('registration_number', 'like', "%{$this->search}%");
                    });
            });
        }
        if ($this->statusFilter) {
            $jobCardsQuery->where('status', $this->statusFilter);
        }

        $jobCards = $jobCardsQuery->paginate(10);
        $schedules = MaintenanceSchedule::with('vehicle')->get();
        $components = VehicleComponent::with('vehicle')->latest()->take(20)->get();
        $alerts = MaintenanceAlert::with('vehicle')->where('is_resolved', false)->latest()->get();

        $vehicles = Vehicle::all();
        $drivers = Driver::all();

        return view('livewire.fleet.workshop-manager', [
            'jobCards' => $jobCards,
            'schedules' => $schedules,
            'components' => $components,
            'alerts' => $alerts,
            'vehicles' => $vehicles,
            'drivers' => $drivers,
        ]);
    }
}


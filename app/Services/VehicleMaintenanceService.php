<?php

namespace App\Services;

use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\JobCard;
use App\Models\JobCardItem;
use App\Models\MaintenanceAlert;
use App\Models\MaintenanceSchedule;
use App\Models\Vehicle;
use App\Models\VehicleComponent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class VehicleMaintenanceService
{
    public function __construct(
        protected DocumentSequenceService $sequenceService,
        protected AuditLogService $auditLog
    ) {}

    /**
     * Define a preventive maintenance schedule / service interval rule.
     */
    public function createSchedule(Company $company, array $data): MaintenanceSchedule
    {
        return DB::transaction(function () use ($company, $data) {
            $vehicle = !empty($data['vehicle_id']) ? Vehicle::find($data['vehicle_id']) : null;
            $currentOdo = $vehicle ? (int) $vehicle->current_odometer : 0;

            $intervalKm = !empty($data['interval_km']) ? (int) $data['interval_km'] : null;
            $intervalDays = !empty($data['interval_days']) ? (int) $data['interval_days'] : null;

            $nextDueOdo = $intervalKm ? ($currentOdo + $intervalKm) : null;
            $nextDueDate = $intervalDays ? now()->addDays($intervalDays)->toDateString() : null;

            $schedule = MaintenanceSchedule::create([
                'company_id' => $company->id,
                'vehicle_id' => $vehicle?->id,
                'title' => $data['title'],
                'maintenance_category' => $data['maintenance_category'] ?? MaintenanceSchedule::CAT_OIL_CHANGE,
                'interval_km' => $intervalKm,
                'interval_days' => $intervalDays,
                'last_performed_at' => $data['last_performed_at'] ?? now()->toDateString(),
                'last_performed_odometer_km' => $currentOdo,
                'next_due_date' => $data['next_due_date'] ?? $nextDueDate,
                'next_due_odometer_km' => $data['next_due_odometer_km'] ?? $nextDueOdo,
                'alert_lead_km' => $data['alert_lead_km'] ?? 500,
                'alert_lead_days' => $data['alert_lead_days'] ?? 7,
                'status' => MaintenanceSchedule::STATUS_ACTIVE,
            ]);

            $this->auditLog->log('maintenance_schedule_created', $schedule, null, $schedule->toArray(), 'Preventive maintenance schedule configured.');

            return $schedule;
        });
    }

    /**
     * Create workshop job card and move vehicle into maintenance downtime.
     */
    public function createJobCard(Company $company, array $data, ?int $userId = null): JobCard
    {
        return DB::transaction(function () use ($company, $data, $userId) {
            $vehicle = Vehicle::where('company_id', $company->id)->findOrFail($data['vehicle_id']);
            $currentOdo = $data['odometer_km'] ?? $vehicle->current_odometer;

            $jobCardNumber = $this->sequenceService->getNextSequenceNumber(
                DocumentSequence::TYPE_JOB_CARD,
                null,
                5,
                $company->id,
                $data['branch_id'] ?? $vehicle->branch_id
            );

            $jobCard = JobCard::create([
                'company_id' => $company->id,
                'branch_id' => $data['branch_id'] ?? $vehicle->branch_id,
                'job_card_number' => $jobCardNumber,
                'vehicle_id' => $vehicle->id,
                'driver_id' => $data['driver_id'] ?? null,
                'maintenance_schedule_id' => $data['maintenance_schedule_id'] ?? null,
                'workshop_type' => $data['workshop_type'] ?? JobCard::WORKSHOP_IN_HOUSE,
                'workshop_name' => $data['workshop_name'] ?? 'In-House Central Terminal Workshop',
                'service_type' => $data['service_type'] ?? JobCard::SERVICE_PREVENTIVE,
                'priority' => $data['priority'] ?? 'medium',
                'odometer_km' => $currentOdo,
                'scheduled_date' => $data['scheduled_date'] ?? now()->toDateString(),
                'started_at' => !empty($data['started_at']) ? Carbon::parse($data['started_at']) : now(),
                'labour_charges' => $data['labour_charges'] ?? 0.00,
                'parts_total_cost' => 0.00,
                'tax_amount' => $data['tax_amount'] ?? 0.00,
                'total_cost' => $data['labour_charges'] ?? 0.00,
                'payment_status' => 'unpaid',
                'status' => JobCard::STATUS_IN_PROGRESS,
                'issue_description' => $data['issue_description'] ?? null,
                'work_performed' => $data['work_performed'] ?? null,
                'supervisor_notes' => $data['supervisor_notes'] ?? null,
                'technician_name' => $data['technician_name'] ?? null,
                'created_by' => $userId ?? auth()->id() ?? 1,
            ]);

            // Transition vehicle to maintenance downtime
            $vehicle->status = Vehicle::STATUS_MAINTENANCE;
            $vehicle->save();

            $this->auditLog->log('job_card_created', $jobCard, null, [
                'job_card' => $jobCardNumber,
                'vehicle' => $vehicle->registration_number,
                'status' => JobCard::STATUS_IN_PROGRESS,
            ], 'Workshop job card initiated; vehicle placed in maintenance.');

            return $jobCard;
        });
    }

    /**
     * Add spare parts usage to job card.
     */
    public function addJobCardPart(JobCard $jobCard, array $itemData): JobCardItem
    {
        return DB::transaction(function () use ($jobCard, $itemData) {
            $qty = (float) ($itemData['quantity'] ?? 1);
            $cost = (float) ($itemData['unit_cost'] ?? 0);
            $totalCost = $itemData['total_cost'] ?? round($qty * $cost, 2);

            $item = JobCardItem::create([
                'company_id' => $jobCard->company_id,
                'job_card_id' => $jobCard->id,
                'part_name' => $itemData['part_name'],
                'part_number' => $itemData['part_number'] ?? null,
                'quantity' => $qty,
                'unit_cost' => $cost,
                'total_cost' => $totalCost,
                'is_inventory_item' => $itemData['is_inventory_item'] ?? false,
                'notes' => $itemData['notes'] ?? null,
            ]);

            $jobCard->recalculateTotals();

            return $item;
        });
    }

    /**
     * Complete job card, calculate downtime hours, restore vehicle to available,
     * update maintenance schedule interval, and resolve alerts.
     */
    public function completeJobCard(JobCard $jobCard, array $completionData, ?int $userId = null): JobCard
    {
        return DB::transaction(function () use ($jobCard, $completionData, $userId) {
            $completedAt = !empty($completionData['completed_at'])
                ? Carbon::parse($completionData['completed_at'])
                : now();

            $startedAt = $jobCard->started_at ?? now()->subHours(4);
            $downtimeHours = round(max(0.5, $startedAt->diffInMinutes($completedAt) / 60), 2);

            if (isset($completionData['labour_charges'])) {
                $jobCard->labour_charges = (float) $completionData['labour_charges'];
            }
            if (isset($completionData['tax_amount'])) {
                $jobCard->tax_amount = (float) $completionData['tax_amount'];
            }

            $jobCard->work_performed = $completionData['work_performed'] ?? $jobCard->work_performed;
            $jobCard->technician_name = $completionData['technician_name'] ?? $jobCard->technician_name;
            $jobCard->supervisor_notes = $completionData['supervisor_notes'] ?? $jobCard->supervisor_notes;
            $jobCard->completed_at = $completedAt;
            $jobCard->downtime_hours = $downtimeHours;
            $jobCard->status = JobCard::STATUS_COMPLETED;
            $jobCard->payment_status = $completionData['payment_status'] ?? 'paid';
            $jobCard->completed_by = $userId ?? auth()->id() ?? 1;

            $jobCard->recalculateTotals();

            // 1. Restore vehicle to Available and update odometer
            $vehicle = $jobCard->vehicle;
            if ($vehicle) {
                $vehicle->status = Vehicle::STATUS_AVAILABLE;
                if ($jobCard->odometer_km > $vehicle->current_odometer) {
                    $vehicle->current_odometer = $jobCard->odometer_km;
                }
                $vehicle->save();
            }

            // 2. Update linked maintenance schedule
            if ($jobCard->maintenance_schedule_id && $jobCard->schedule) {
                $schedule = $jobCard->schedule;
                $schedule->last_performed_at = $completedAt->toDateString();
                $schedule->last_performed_odometer_km = $jobCard->odometer_km;

                if ($schedule->interval_km) {
                    $schedule->next_due_odometer_km = $jobCard->odometer_km + $schedule->interval_km;
                }
                if ($schedule->interval_days) {
                    $schedule->next_due_date = $completedAt->copy()->addDays($schedule->interval_days)->toDateString();
                }

                $schedule->status = MaintenanceSchedule::STATUS_ACTIVE;
                $schedule->save();
            }

            // 3. Resolve any related maintenance alerts for this vehicle
            MaintenanceAlert::where('vehicle_id', $jobCard->vehicle_id)
                ->where('is_resolved', false)
                ->whereIn('alert_type', [
                    MaintenanceAlert::TYPE_OIL_CHANGE_DUE,
                    MaintenanceAlert::TYPE_SERVICE_DUE,
                ])
                ->update([
                    'is_resolved' => true,
                    'resolved_at' => now(),
                    'resolved_by' => $userId ?? auth()->id() ?? 1,
                ]);

            $this->auditLog->log('job_card_completed', $jobCard, null, [
                'total_cost' => $jobCard->total_cost,
                'downtime_hours' => $downtimeHours,
                'status' => JobCard::STATUS_COMPLETED,
            ], 'Job card completed; fleet vehicle returned to active service.');

            return $jobCard;
        });
    }

    /**
     * Install new component on vehicle (Tyre, Battery, Engine Oil, etc.).
     */
    public function installComponent(Company $company, array $data): VehicleComponent
    {
        return DB::transaction(function () use ($company, $data) {
            $vehicle = Vehicle::where('company_id', $company->id)->findOrFail($data['vehicle_id']);
            $currentOdo = $data['installed_odometer_km'] ?? $vehicle->current_odometer;

            $component = VehicleComponent::create([
                'company_id' => $company->id,
                'vehicle_id' => $vehicle->id,
                'job_card_id' => $data['job_card_id'] ?? null,
                'component_type' => $data['component_type'],
                'brand_name' => $data['brand_name'] ?? null,
                'model_or_size' => $data['model_or_size'] ?? null,
                'serial_number' => $data['serial_number'] ?? null,
                'position' => $data['position'] ?? null,
                'installed_date' => $data['installed_date'] ?? now()->toDateString(),
                'installed_odometer_km' => $currentOdo,
                'expected_life_km' => $data['expected_life_km'] ?? null,
                'expected_life_days' => $data['expected_life_days'] ?? null,
                'status' => VehicleComponent::STATUS_ACTIVE,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->auditLog->log('vehicle_component_installed', $component, null, [
                'type' => $component->component_type,
                'vehicle' => $vehicle->registration_number,
                'position' => $component->position,
            ], 'Vehicle component installed and tracked.');

            return $component;
        });
    }

    /**
     * Replace/retire an existing vehicle component.
     */
    public function replaceComponent(VehicleComponent $component, array $data): VehicleComponent
    {
        return DB::transaction(function () use ($component, $data) {
            $component->status = $data['status'] ?? VehicleComponent::STATUS_REPLACED;
            $component->removed_date = $data['removed_date'] ?? now()->toDateString();
            $component->removed_odometer_km = $data['removed_odometer_km'] ?? $component->vehicle->current_odometer;
            if (!empty($data['notes'])) {
                $component->notes = ($component->notes ? $component->notes . "\n" : '') . $data['notes'];
            }
            $component->save();

            $this->auditLog->log('vehicle_component_replaced', $component, null, [
                'status' => $component->status,
                'usage_km' => $component->getUsageKm($component->removed_odometer_km),
            ], 'Vehicle component decommissioned / replaced.');

            return $component;
        });
    }

    /**
     * Comprehensive fleet alert evaluation:
     * - Scans preventive maintenance schedules (oil change, service intervals)
     * - Scans vehicle components for exceeded life
     * - Scans compliance expiry dates (insurance, registration, fitness)
     */
    public function evaluateFleetAlerts(Company $company): array
    {
        $alertsGenerated = [];
        $vehicles = Vehicle::where('company_id', $company->id)->get();

        foreach ($vehicles as $vehicle) {
            $odo = (int) $vehicle->current_odometer;

            // 1. Compliance Expiries
            if ($vehicle->insurance_expiry_date && $vehicle->insurance_expiry_date->diffInDays(now(), false) >= -15) {
                $alertsGenerated[] = MaintenanceAlert::firstOrCreate([
                    'company_id' => $company->id,
                    'vehicle_id' => $vehicle->id,
                    'alert_type' => MaintenanceAlert::TYPE_INSURANCE_EXPIRY,
                    'is_resolved' => false,
                ], [
                    'title' => 'Insurance Expiry Alert',
                    'description' => "Vehicle [{$vehicle->registration_number}] insurance expires on {$vehicle->insurance_expiry_date->format('d M Y')}.",
                    'severity' => $vehicle->insurance_expiry_date->isPast() ? MaintenanceAlert::SEVERITY_CRITICAL : MaintenanceAlert::SEVERITY_WARNING,
                    'due_date' => $vehicle->insurance_expiry_date,
                ]);
            }

            if ($vehicle->fitness_expiry_date && $vehicle->fitness_expiry_date->diffInDays(now(), false) >= -15) {
                $alertsGenerated[] = MaintenanceAlert::firstOrCreate([
                    'company_id' => $company->id,
                    'vehicle_id' => $vehicle->id,
                    'alert_type' => MaintenanceAlert::TYPE_FITNESS_EXPIRY,
                    'is_resolved' => false,
                ], [
                    'title' => 'Fitness Certificate Expiry Alert',
                    'description' => "Vehicle [{$vehicle->registration_number}] fitness certificate expires on {$vehicle->fitness_expiry_date->format('d M Y')}.",
                    'severity' => $vehicle->fitness_expiry_date->isPast() ? MaintenanceAlert::SEVERITY_CRITICAL : MaintenanceAlert::SEVERITY_WARNING,
                    'due_date' => $vehicle->fitness_expiry_date,
                ]);
            }

            // 2. Component Wear / Life Exceeded
            $components = $vehicle->components()->where('status', VehicleComponent::STATUS_ACTIVE)->get();
            foreach ($components as $comp) {
                if ($comp->isLifeExceeded($odo)) {
                    $alertsGenerated[] = MaintenanceAlert::firstOrCreate([
                        'company_id' => $company->id,
                        'vehicle_id' => $vehicle->id,
                        'alert_type' => MaintenanceAlert::TYPE_COMPONENT_LIFE,
                        'is_resolved' => false,
                    ], [
                        'title' => ucfirst($comp->component_type) . ' Life Limit Exceeded',
                        'description' => "Component [{$comp->brand_name} {$comp->model_or_size} ({$comp->position})] has exceeded its rated service threshold ({$comp->getUsageKm($odo)} KM).",
                        'severity' => MaintenanceAlert::SEVERITY_WARNING,
                    ]);
                }
            }
        }

        // 3. Maintenance Schedules
        $schedules = MaintenanceSchedule::where('company_id', $company->id)
            ->where('status', '!=', MaintenanceSchedule::STATUS_INACTIVE)
            ->get();

        foreach ($schedules as $sched) {
            $veh = $sched->vehicle;
            $currOdo = $veh ? (int) $veh->current_odometer : 0;
            $status = $sched->evaluateStatus($currOdo);
            $sched->status = $status;
            $sched->save();

            if (in_array($status, [MaintenanceSchedule::STATUS_DUE, MaintenanceSchedule::STATUS_OVERDUE]) && $veh) {
                $type = $sched->maintenance_category === MaintenanceSchedule::CAT_OIL_CHANGE
                    ? MaintenanceAlert::TYPE_OIL_CHANGE_DUE
                    : MaintenanceAlert::TYPE_SERVICE_DUE;

                $alertsGenerated[] = MaintenanceAlert::firstOrCreate([
                    'company_id' => $company->id,
                    'vehicle_id' => $veh->id,
                    'alert_type' => $type,
                    'is_resolved' => false,
                ], [
                    'title' => "{$sched->title} " . ($status === MaintenanceSchedule::STATUS_OVERDUE ? 'Overdue' : 'Due Soon'),
                    'description' => "Service rule [{$sched->title}] on [{$veh->registration_number}] is {$status} (Next due: {$sched->next_due_odometer_km} KM).",
                    'severity' => $status === MaintenanceSchedule::STATUS_OVERDUE ? MaintenanceAlert::SEVERITY_CRITICAL : MaintenanceAlert::SEVERITY_WARNING,
                    'due_odometer_km' => $sched->next_due_odometer_km,
                    'due_date' => $sched->next_due_date,
                ]);
            }
        }

        return $alertsGenerated;
    }
}

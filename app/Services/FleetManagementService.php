<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Driver;
use App\Models\FleetDocument;
use App\Models\Vehicle;
use App\Models\VehicleDriverAssignment;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FleetManagementService
{
    public function __construct(
        protected FeatureManagerService $featureManager,
        protected AuditLogService $auditLog
    ) {}

    /**
     * Register a new fleet vehicle with plan quota validation.
     *
     * @throws AuthorizationException
     */
    public function registerVehicle(Company $company, array $data): Vehicle
    {
        // 1. Enforce Subscription Plan Vehicle Quota
        $this->featureManager->checkVehicleLimit($company);

        // 2. Validate Attached / Rented truck requirements
        if ($data['ownership_type'] !== Vehicle::OWNERSHIP_COMPANY && empty($data['vehicle_owner_id'])) {
            throw new \InvalidArgumentException('Market attached or rented vehicles must be linked to a registered vehicle owner.');
        }

        return DB::transaction(function () use ($company, $data) {
            $vehicle = Vehicle::create([
                'company_id' => $company->id,
                'branch_id' => $data['branch_id'] ?? null,
                'vehicle_owner_id' => $data['vehicle_owner_id'] ?? null,
                'active_contract_id' => $data['active_contract_id'] ?? null,
                'registration_number' => strtoupper(trim($data['registration_number'])),
                'fleet_number' => $data['fleet_number'] ?? null,
                'ownership_type' => $data['ownership_type'],
                'vehicle_category' => $data['vehicle_category'] ?? 'HCV',
                'vehicle_type' => $data['vehicle_type'] ?? 'TRAILER_22W',
                'make' => $data['make'] ?? null,
                'model' => $data['model'] ?? null,
                'manufacturing_year' => $data['manufacturing_year'] ?? null,
                'chassis_number' => $data['chassis_number'] ?? null,
                'engine_number' => $data['engine_number'] ?? null,
                'payload_capacity_tons' => $data['payload_capacity_tons'] ?? 0.00,
                'volume_capacity_cbm' => $data['volume_capacity_cbm'] ?? null,
                'fuel_type' => $data['fuel_type'] ?? 'DIESEL',
                'fuel_tank_capacity_liters' => $data['fuel_tank_capacity_liters'] ?? null,
                'current_odometer_km' => $data['current_odometer_km'] ?? 0,
                'registration_expiry_date' => $data['registration_expiry_date'] ?? null,
                'insurance_expiry_date' => $data['insurance_expiry_date'] ?? null,
                'fitness_expiry_date' => $data['fitness_expiry_date'] ?? null,
                'route_permit_expiry_date' => $data['route_permit_expiry_date'] ?? null,
                'status' => $data['status'] ?? Vehicle::STATUS_AVAILABLE,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->auditLog->log('vehicle_created', $vehicle, null, $vehicle->toArray(), 'New fleet vehicle registered.');

            return $vehicle;
        });
    }

    /**
     * Assign primary and optional secondary (co-driver) to a vehicle.
     */
    public function assignDrivers(
        Vehicle $vehicle,
        int $primaryDriverId,
        ?int $coDriverId = null,
        ?int $assignedByUserId = null,
        ?string $notes = null
    ): void {
        DB::transaction(function () use ($vehicle, $primaryDriverId, $coDriverId, $assignedByUserId, $notes) {
            $assignedBy = $assignedByUserId ?? auth()->id() ?? 1;

            // 1. Unassign any existing active assignments for this vehicle
            $vehicle->activeDriverAssignments()->update([
                'status' => 'completed',
                'unassigned_at' => now(),
            ]);

            // 2. Clear old vehicle links on drivers
            Driver::where('current_vehicle_id', $vehicle->id)->update(['current_vehicle_id' => null]);

            // 3. Assign Primary Driver
            VehicleDriverAssignment::create([
                'company_id' => $vehicle->company_id,
                'vehicle_id' => $vehicle->id,
                'driver_id' => $primaryDriverId,
                'is_primary' => true,
                'assigned_at' => now(),
                'status' => 'active',
                'assigned_by' => $assignedBy,
                'notes' => $notes,
            ]);

            $primaryDriver = Driver::findOrFail($primaryDriverId);
            $primaryDriver->current_vehicle_id = $vehicle->id;
            $primaryDriver->save();

            $vehicle->current_driver_id = $primaryDriverId;

            // 4. Assign Co-Driver if provided
            if ($coDriverId && $coDriverId !== $primaryDriverId) {
                VehicleDriverAssignment::create([
                    'company_id' => $vehicle->company_id,
                    'vehicle_id' => $vehicle->id,
                    'driver_id' => $coDriverId,
                    'is_primary' => false,
                    'assigned_at' => now(),
                    'status' => 'active',
                    'assigned_by' => $assignedBy,
                    'notes' => 'Assigned as secondary / long-haul relief co-driver.',
                ]);

                $coDriver = Driver::findOrFail($coDriverId);
                $coDriver->current_vehicle_id = $vehicle->id;
                $coDriver->save();
            }

            $vehicle->save();
        });
    }

    /**
     * Unassign all active drivers from a vehicle.
     */
    public function unassignDrivers(Vehicle $vehicle): void
    {
        DB::transaction(function () use ($vehicle) {
            $vehicle->activeDriverAssignments()->update([
                'status' => 'completed',
                'unassigned_at' => now(),
            ]);

            Driver::where('current_vehicle_id', $vehicle->id)->update(['current_vehicle_id' => null]);

            $vehicle->current_driver_id = null;
            $vehicle->save();
        });
    }

    /**
     * Scan fleet vehicles, drivers, and document records for expiry alerts.
     */
    public function getExpiringDocuments(?int $companyId = null, int $withinDays = 30): array
    {
        $now = now()->startOfDay();
        $warningLimit = now()->addDays($withinDays)->endOfDay();
        $criticalLimit = now()->addDays(7)->endOfDay();

        $expired = collect();
        $critical = collect();
        $warning = collect();

        // 1. Scan Vehicle Expiries
        $vehicleQuery = Vehicle::query()->when($companyId, fn($q) => $q->where('company_id', $companyId));

        foreach ($vehicleQuery->get() as $v) {
            $dates = [
                'Fitness Certificate' => $v->fitness_expiry_date,
                'Commercial Insurance' => $v->insurance_expiry_date,
                'Route Permit' => $v->route_permit_expiry_date,
                'Vehicle Registration' => $v->registration_expiry_date,
            ];

            foreach ($dates as $docName => $date) {
                if (!$date) continue;
                $carbonDate = Carbon::parse($date)->startOfDay();

                $entry = [
                    'entity_type' => 'Vehicle',
                    'entity_name' => $v->registration_number . ($v->fleet_number ? " ({$v->fleet_number})" : ''),
                    'document_name' => $docName,
                    'expiry_date' => $carbonDate->toDateString(),
                    'days_remaining' => (int) $now->diffInDays($carbonDate, false),
                ];

                if ($carbonDate->isPast()) {
                    $expired->push($entry);
                } elseif ($carbonDate->lessThanOrEqualTo($criticalLimit)) {
                    $critical->push($entry);
                } elseif ($carbonDate->lessThanOrEqualTo($warningLimit)) {
                    $warning->push($entry);
                }
            }
        }

        // 2. Scan Driver License Expiries
        $driverQuery = Driver::query()->when($companyId, fn($q) => $q->where('company_id', $companyId));

        foreach ($driverQuery->get() as $d) {
            if (!$d->license_expiry_date) continue;
            $licenseDate = Carbon::parse($d->license_expiry_date)->startOfDay();

            $entry = [
                'entity_type' => 'Driver',
                'entity_name' => $d->name . " (Lic: {$d->license_number})",
                'document_name' => "Driving License ({$d->license_category})",
                'expiry_date' => $licenseDate->toDateString(),
                'days_remaining' => (int) $now->diffInDays($licenseDate, false),
            ];

            if ($licenseDate->isPast()) {
                $expired->push($entry);
            } elseif ($licenseDate->lessThanOrEqualTo($criticalLimit)) {
                $critical->push($entry);
            } elseif ($licenseDate->lessThanOrEqualTo($warningLimit)) {
                $warning->push($entry);
            }
        }

        return [
            'expired' => $expired->sortBy('days_remaining')->values()->all(),
            'critical' => $critical->sortBy('days_remaining')->values()->all(),
            'warning' => $warning->sortBy('days_remaining')->values()->all(),
            'total_alerts' => $expired->count() + $critical->count() + $warning->count(),
        ];
    }
}


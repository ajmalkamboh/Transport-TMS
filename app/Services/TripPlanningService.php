<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Consignment;
use App\Models\DocumentSequence;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\TripConsignment;
use App\Models\TripResource;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

class TripPlanningService
{
    public function __construct(
        protected DocumentSequenceService $sequenceService,
        protected AuditLogService $auditLog
    ) {}

    /**
     * Create a new trip with atomic sequence number.
     */
    public function createTrip(Company $company, array $data, ?int $dispatcherId = null): Trip
    {
        return DB::transaction(function () use ($company, $data, $dispatcherId) {
            $tripNumber = $this->sequenceService->getNextSequenceNumber(
                DocumentSequence::TYPE_TRIP,
                null,
                5,
                $company->id,
                $data['branch_id'] ?? null
            );

            $trip = Trip::create([
                'company_id' => $company->id,
                'branch_id' => $data['branch_id'] ?? null,
                'trip_number' => $tripNumber,
                'trip_date' => $data['trip_date'] ?? now()->toDateString(),
                'origin_city' => $data['origin_city'],
                'destination_city' => $data['destination_city'],
                'planned_route' => $data['planned_route'] ?? null,
                'estimated_departure' => $data['estimated_departure'] ?? null,
                'estimated_arrival' => $data['estimated_arrival'] ?? null,
                'assigned_dispatcher_id' => $dispatcherId ?? auth()->id() ?? 1,
                'trip_status' => Trip::STATUS_PLANNED,
                'financial_status' => Trip::FINANCIAL_PENDING,
                'trip_notes' => $data['trip_notes'] ?? null,
            ]);

            $this->auditLog->log('trip_created', $trip, null, $trip->toArray(), 'New trip plan registered.');

            return $trip;
        });
    }

    /**
     * Assign vehicle or commercial driver to trip with availability verification.
     *
     * @throws \InvalidArgumentException
     */
    public function assignResource(
        Trip $trip,
        string $resourceType,
        int $resourceId,
        string $role,
        bool $overrideAllowed = false,
        ?string $overrideReason = null
    ): TripResource {
        // 1. Conflict and Availability Verification
        if (!$overrideAllowed) {
            if ($resourceType === TripResource::TYPE_VEHICLE) {
                $busy = TripResource::where('resource_type', TripResource::TYPE_VEHICLE)
                    ->where('vehicle_id', $resourceId)
                    ->where('is_active', true)
                    ->whereHas('trip', function ($q) {
                        $q->whereIn('trip_status', [Trip::STATUS_DISPATCHED, Trip::STATUS_IN_TRANSIT]);
                    })
                    ->exists();

                if ($busy) {
                    $veh = Vehicle::find($resourceId);
                    throw new \InvalidArgumentException(
                        sprintf('Vehicle [%s] is already deployed on an active in-transit trip.', $veh?->registration_number ?? $resourceId)
                    );
                }
            } elseif ($resourceType === TripResource::TYPE_DRIVER) {
                $busy = TripResource::where('resource_type', TripResource::TYPE_DRIVER)
                    ->where('driver_id', $resourceId)
                    ->where('is_active', true)
                    ->whereHas('trip', function ($q) {
                        $q->whereIn('trip_status', [Trip::STATUS_DISPATCHED, Trip::STATUS_IN_TRANSIT]);
                    })
                    ->exists();

                if ($busy) {
                    $drv = Driver::find($resourceId);
                    throw new \InvalidArgumentException(
                        sprintf('Commercial Driver [%s] is currently dispatched on another active trip.', $drv?->name ?? $resourceId)
                    );
                }
            }
        }

        return DB::transaction(function () use ($trip, $resourceType, $resourceId, $role, $overrideAllowed, $overrideReason) {
            $resource = TripResource::create([
                'company_id' => $trip->company_id,
                'trip_id' => $trip->id,
                'vehicle_id' => $resourceType === TripResource::TYPE_VEHICLE ? $resourceId : null,
                'driver_id' => $resourceType === TripResource::TYPE_DRIVER ? $resourceId : null,
                'resource_type' => $resourceType,
                'role' => $role,
                'assigned_at' => now(),
                'is_active' => true,
                'override_allowed' => $overrideAllowed,
                'override_reason' => $overrideReason,
            ]);

            $this->auditLog->log('trip_resource_assigned', $trip, null, [
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'role' => $role,
            ], 'Resource assigned to trip.');

            return $resource;
        });
    }

    /**
     * Allocate a consignment or partial quantity to a trip.
     *
     * @throws \InvalidArgumentException
     */
    public function allocateConsignment(
        Trip $trip,
        Consignment $consignment,
        int $quantity,
        ?float $weightKg = null
    ): TripConsignment {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Assigned consignment quantity must be greater than zero.');
        }

        if ($quantity > $consignment->remaining_quantity) {
            throw new \InvalidArgumentException(
                sprintf('Allocated quantity [%d] exceeds remaining quantity [%d] on Consignment [%s].', $quantity, $consignment->remaining_quantity, $consignment->consignment_number)
            );
        }

        $calcWeight = $weightKg ?? ($consignment->total_quantity > 0
            ? round(($quantity / $consignment->total_quantity) * (float) $consignment->chargeable_weight_kg, 2)
            : 0.00);

        return DB::transaction(function () use ($trip, $consignment, $quantity, $calcWeight) {
            $allocation = TripConsignment::create([
                'company_id' => $trip->company_id,
                'trip_id' => $trip->id,
                'consignment_id' => $consignment->id,
                'assigned_quantity' => $quantity,
                'weight_kg' => $calcWeight,
                'loading_status' => TripConsignment::LOADING_PLANNED,
                'delivery_status' => TripConsignment::DELIVERY_PENDING,
            ]);

            $trip->refreshCumulativeTotals();

            $this->auditLog->log('trip_consignment_allocated', $trip, null, [
                'consignment_id' => $consignment->id,
                'quantity' => $quantity,
                'weight_kg' => $calcWeight,
            ], 'Consignment allocated to trip.');

            return $allocation;
        });
    }

    /**
     * Complete physical cargo loading and generate loading slip.
     */
    public function recordLoading(
        Trip $trip,
        string $supervisor,
        ?string $startedAt = null,
        ?string $completedAt = null
    ): void {
        DB::transaction(function () use ($trip, $supervisor, $startedAt, $completedAt) {
            $trip->trip_status = Trip::STATUS_LOADING;
            $trip->loading_supervisor = $supervisor;
            $trip->loading_started_at = $startedAt ? \Carbon\Carbon::parse($startedAt) : now()->subHours(2);
            $trip->loading_completed_at = $completedAt ? \Carbon\Carbon::parse($completedAt) : now();
            $trip->save();

            $trip->tripConsignments()->update(['loading_status' => TripConsignment::LOADING_LOADED]);

            $this->auditLog->log('trip_loading_completed', $trip, null, [
                'supervisor' => $supervisor,
                'packages' => $trip->total_packages_count,
            ], 'Trip cargo loading verified.');
        });
    }
}

<?php

namespace App\Services;

use App\Models\Consignment;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\TripCheckpoint;
use App\Models\TripConsignment;
use App\Models\TripResource;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TripDispatchService
{
    public function __construct(
        protected AuditLogService $auditLog
    ) {}

    /**
     * Dispatch an authorized trip with departure odometer and timestamp.
     * Transitions trip to in_transit and allocated consignments to dispatched.
     *
     * @throws \InvalidArgumentException
     */
    public function dispatchTrip(
        Trip $trip,
        int $departureOdometer,
        ?string $actualDeparture = null,
        ?int $dispatcherUserId = null
    ): Trip {
        if (in_array($trip->trip_status, [Trip::STATUS_IN_TRANSIT, Trip::STATUS_OPERATIONALLY_COMPLETED, Trip::STATUS_CLOSED, Trip::STATUS_CANCELLED])) {
            throw new \InvalidArgumentException(
                sprintf('Trip [%s] cannot be dispatched because it is in status [%s].', $trip->trip_number, $trip->trip_status)
            );
        }

        if ($trip->tripConsignments()->count() === 0) {
            throw new \InvalidArgumentException(
                sprintf('Cannot dispatch Trip [%s] without any allocated consignments.', $trip->trip_number)
            );
        }

        if ($trip->activeResources()->count() === 0) {
            throw new \InvalidArgumentException(
                sprintf('Cannot dispatch Trip [%s] without assigned vehicle or driver resources.', $trip->trip_number)
            );
        }

        return DB::transaction(function () use ($trip, $departureOdometer, $actualDeparture, $dispatcherUserId) {
            $departureTime = $actualDeparture ? Carbon::parse($actualDeparture) : now();

            $trip->departure_odometer_km = $departureOdometer;
            $trip->actual_departure = $departureTime;
            $trip->trip_status = Trip::STATUS_IN_TRANSIT;
            $trip->dispatched_by = $dispatcherUserId ?? auth()->id() ?? 1;
            $trip->save();

            // 1. Update Assigned Vehicles and Drivers to on_trip / on_duty
            foreach ($trip->activeResources as $resource) {
                if ($resource->resource_type === TripResource::TYPE_VEHICLE && $resource->vehicle) {
                    $veh = $resource->vehicle;
                    $veh->status = Vehicle::STATUS_ON_TRIP;
                    if ($departureOdometer > $veh->current_odometer) {
                        $veh->current_odometer = $departureOdometer;
                    }
                    $veh->save();
                } elseif ($resource->resource_type === TripResource::TYPE_DRIVER && $resource->driver) {
                    $drv = $resource->driver;
                    $drv->status = Driver::STATUS_ON_DUTY;
                    $drv->save();
                }
            }

            // 2. Transition Allocated Consignments and decrease remaining stock
            foreach ($trip->tripConsignments as $allocation) {
                $allocation->loading_status = TripConsignment::LOADING_DISPATCHED;
                $allocation->save();

                $consignment = $allocation->consignment;
                if ($consignment) {
                    $consignment->recordDispatch($allocation->assigned_quantity);
                }
            }

            $this->auditLog->log('trip_dispatched', $trip, null, [
                'departure_odometer' => $departureOdometer,
                'departure_time' => $departureTime->toIso8601String(),
                'dispatched_by' => $trip->dispatched_by,
                'consignments_count' => $trip->total_consignments_count,
            ], 'Trip dispatched and in transit.');

            return $trip;
        });
    }

    /**
     * Record a route milestone / checkpoint passed by the trip.
     */
    public function recordCheckpoint(
        Trip $trip,
        string $checkpointName,
        ?int $odometer = null,
        int $delayMinutes = 0,
        ?string $delayReason = null,
        ?string $notes = null,
        ?string $actualArrival = null,
        ?string $actualDeparture = null
    ): TripCheckpoint {
        return DB::transaction(function () use (
            $trip,
            $checkpointName,
            $odometer,
            $delayMinutes,
            $delayReason,
            $notes,
            $actualArrival,
            $actualDeparture
        ) {
            $nextOrder = ($trip->checkpoints()->max('stop_order') ?? 0) + 1;

            $checkpoint = TripCheckpoint::create([
                'company_id' => $trip->company_id,
                'trip_id' => $trip->id,
                'checkpoint_name' => $checkpointName,
                'stop_order' => $nextOrder,
                'actual_arrival' => $actualArrival ? Carbon::parse($actualArrival) : now(),
                'actual_departure' => $actualDeparture ? Carbon::parse($actualDeparture) : now(),
                'odometer_km' => $odometer,
                'delay_minutes' => $delayMinutes,
                'delay_reason' => $delayReason,
                'notes' => $notes,
                'status' => TripCheckpoint::STATUS_PASSED,
            ]);

            // Update vehicle current odometer if higher
            if ($odometer && $trip->primaryVehicle()) {
                $primaryVeh = $trip->primaryVehicle();
                if ($odometer > $primaryVeh->current_odometer) {
                    $primaryVeh->current_odometer = $odometer;
                    $primaryVeh->save();
                }
            }

            $this->auditLog->log('trip_checkpoint_recorded', $trip, null, [
                'checkpoint' => $checkpointName,
                'stop_order' => $nextOrder,
                'odometer' => $odometer,
                'delay_minutes' => $delayMinutes,
            ], 'Route checkpoint recorded.');

            return $checkpoint;
        });
    }
}

<?php

namespace App\Services;

use App\Models\ProofOfDelivery;
use App\Models\Trip;
use App\Models\TripConsignment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DeliveryManagementService
{
    public function __construct(
        protected AuditLogService $auditLog
    ) {}

    /**
     * Record physical delivery and generate a Proof of Delivery (POD) record.
     *
     * @throws \InvalidArgumentException
     */
    public function recordDelivery(
        TripConsignment $allocation,
        int $deliveredQty,
        int $damagedQty = 0,
        int $shortQty = 0,
        array $podData = [],
        ?int $verifiedBy = null
    ): ProofOfDelivery {
        if ($deliveredQty < 0 || $damagedQty < 0 || $shortQty < 0) {
            throw new \InvalidArgumentException('Delivered, damaged and short quantities must be non-negative.');
        }

        return DB::transaction(function () use (
            $allocation,
            $deliveredQty,
            $damagedQty,
            $shortQty,
            $podData,
            $verifiedBy
        ) {
            // 1. Record delivery on the trip consignment allocation
            $allocation->recordDelivery(
                $deliveredQty,
                $damagedQty,
                $shortQty,
                $podData['receiver_name'] ?? null,
                $podData['receiver_phone'] ?? null,
                $podData['exception_notes'] ?? null
            );

            // 2. Create Proof of Delivery
            $pod = ProofOfDelivery::create([
                'company_id' => $allocation->company_id,
                'trip_id' => $allocation->trip_id,
                'consignment_id' => $allocation->consignment_id,
                'trip_consignment_id' => $allocation->id,
                'receiver_name' => $podData['receiver_name'] ?? 'Authorized Receiver',
                'receiver_phone' => $podData['receiver_phone'] ?? null,
                'receiver_cnic' => $podData['receiver_cnic'] ?? null,
                'delivery_datetime' => isset($podData['delivery_datetime'])
                    ? Carbon::parse($podData['delivery_datetime'])
                    : now(),
                'delivered_quantity' => $deliveredQty,
                'damaged_quantity' => $damagedQty,
                'short_quantity' => $shortQty,
                'gps_latitude' => $podData['gps_latitude'] ?? null,
                'gps_longitude' => $podData['gps_longitude'] ?? null,
                'signature_file_path' => $podData['signature_file_path'] ?? null,
                'document_file_path' => $podData['document_file_path'] ?? null,
                'photo_file_path' => $podData['photo_file_path'] ?? null,
                'status' => $podData['status'] ?? ProofOfDelivery::STATUS_VERIFIED,
                'exception_notes' => $podData['exception_notes'] ?? null,
                'verified_by' => $verifiedBy ?? auth()->id() ?? 1,
                'verified_at' => now(),
            ]);

            // 4. Check if all trip consignments are completed, transition trip to delivered
            $trip = $allocation->trip;
            $allCompleted = $trip->tripConsignments()
                ->where('delivery_status', TripConsignment::DELIVERY_PENDING)
                ->doesntExist();

            if ($allCompleted && $trip->trip_status === Trip::STATUS_IN_TRANSIT) {
                $trip->trip_status = Trip::STATUS_DELIVERED;
                $trip->save();
            }

            $this->auditLog->log('proof_of_delivery_recorded', $pod, null, [
                'delivered_quantity' => $deliveredQty,
                'damaged_quantity' => $damagedQty,
                'short_quantity' => $shortQty,
                'receiver_name' => $pod->receiver_name,
            ], 'Proof of delivery recorded and verified.');

            return $pod;
        });
    }

    /**
     * Operationally complete the trip:
     * - Records arrival odometer and arrival time
     * - Calculates total distance
     * - Releases all assigned vehicles and drivers back to 'available'
     * - Sets status to operationally_completed (NOT financially closed)
     *
     * @throws \InvalidArgumentException
     */
    public function completeOperationally(
        Trip $trip,
        int $arrivalOdometer,
        ?string $actualArrival = null
    ): Trip {
        if ($trip->departure_odometer_km && $arrivalOdometer < $trip->departure_odometer_km) {
            throw new \InvalidArgumentException(
                sprintf('Arrival odometer [%d km] cannot be less than departure odometer [%d km].', $arrivalOdometer, $trip->departure_odometer_km)
            );
        }

        return DB::transaction(function () use ($trip, $arrivalOdometer, $actualArrival) {
            $arrivalTime = $actualArrival ? Carbon::parse($actualArrival) : now();
            $departureOdometer = $trip->departure_odometer_km ?? $arrivalOdometer;
            $totalDistance = max(0, $arrivalOdometer - $departureOdometer);

            $trip->arrival_odometer_km = $arrivalOdometer;
            $trip->actual_arrival = $arrivalTime;
            $trip->total_distance_km = $totalDistance;
            $trip->trip_status = Trip::STATUS_OPERATIONALLY_COMPLETED;
            $trip->operationally_completed_at = now();
            $trip->save();

            // Release all active vehicles and drivers
            foreach ($trip->activeResources as $resource) {
                if ($resource->resource_type === 'vehicle' && $resource->vehicle) {
                    $veh = $resource->vehicle;
                    if ($arrivalOdometer > $veh->current_odometer) {
                        $veh->current_odometer = $arrivalOdometer;
                        $veh->save();
                    }
                }
                $resource->release();
            }

            $this->auditLog->log('trip_operationally_completed', $trip, null, [
                'arrival_odometer' => $arrivalOdometer,
                'total_distance_km' => $totalDistance,
                'actual_arrival' => $arrivalTime->toIso8601String(),
            ], 'Trip operationally completed; fleet resources released.');

            return $trip;
        });
    }

    /**
     * Financially close the trip:
     * - Verifies that the trip is already operationally completed
     * - Marks financial status and trip status to closed
     *
     * @throws \InvalidArgumentException
     */
    public function closeFinancially(Trip $trip, ?string $notes = null): Trip
    {
        if (!$trip->isOperationallyCompleted()) {
            throw new \InvalidArgumentException(
                sprintf('Trip [%s] must be operationally completed before financial closure.', $trip->trip_number)
            );
        }

        return DB::transaction(function () use ($trip, $notes) {
            $trip->financial_status = Trip::FINANCIAL_CLOSED;
            $trip->financially_closed_at = now();
            $trip->trip_status = Trip::STATUS_CLOSED;
            if ($notes) {
                $trip->trip_notes = ($trip->trip_notes ? $trip->trip_notes . "\n" : '') . '[Financial Closure]: ' . $notes;
            }
            $trip->save();

            $this->auditLog->log('trip_financially_closed', $trip, null, [
                'financial_status' => Trip::FINANCIAL_CLOSED,
                'closed_at' => $trip->financially_closed_at->toIso8601String(),
            ], 'Trip financially audited and closed.');

            return $trip;
        });
    }
}

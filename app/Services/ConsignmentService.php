<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Consignment;
use App\Models\ConsignmentItem;
use App\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;

class ConsignmentService
{
    public function __construct(
        protected DocumentSequenceService $sequenceService,
        protected AuditLogService $auditLog
    ) {}

    /**
     * Create an additional consignment (Bilty) under an existing booking.
     */
    public function createConsignment(Booking $booking, array $data, ?int $userId = null): Consignment
    {
        return DB::transaction(function () use ($booking, $data, $userId) {
            $user = $userId ?? auth()->id() ?? 1;

            $consignmentNumber = $this->sequenceService->getNextSequenceNumber(
                DocumentSequence::TYPE_CONSIGNMENT,
                null,
                5,
                $booking->company_id,
                $booking->branch_id
            );

            $quantity = (int) ($data['total_quantity'] ?? 1);
            $grossWeight = (float) ($data['gross_weight_kg'] ?? 0.00);
            $chargeableWeight = (float) ($data['chargeable_weight_kg'] ?? $grossWeight);

            $freightCharge = (float) ($data['freight_charge'] ?? 0.00);
            $handlingCharge = (float) ($data['handling_charge'] ?? 0.00);
            $insuranceCharge = (float) ($data['insurance_charge'] ?? 0.00);
            $totalCharges = round($freightCharge + $handlingCharge + $insuranceCharge, 2);

            $consignment = Consignment::create([
                'company_id' => $booking->company_id,
                'branch_id' => $booking->branch_id,
                'booking_id' => $booking->id,
                'customer_id' => $booking->customer_id,
                'created_by' => $user,
                'consignment_number' => $consignmentNumber,
                'consignment_date' => $data['consignment_date'] ?? now()->toDateString(),
                'consignee_name' => $data['consignee_name'],
                'consignee_phone' => $data['consignee_phone'],
                'consignee_cnic_or_ntn' => $data['consignee_cnic_or_ntn'] ?? null,
                'consignee_address' => $data['consignee_address'] ?? null,
                'consignee_city' => $data['consignee_city'],
                'pickup_point' => $data['pickup_point'] ?? $booking->origin_city,
                'delivery_point' => $data['delivery_point'] ?? $booking->destination_city,
                'cargo_description' => $data['cargo_description'],
                'cargo_category' => $data['cargo_category'] ?? 'GENERAL_CARGO',
                'packaging_type' => $data['packaging_type'] ?? Consignment::PACKAGING_BAGS,
                'declared_value' => $data['declared_value'] ?? null,
                'gross_weight_kg' => $grossWeight,
                'chargeable_weight_kg' => $chargeableWeight,
                'volume_cbm' => $data['volume_cbm'] ?? null,
                'total_quantity' => $quantity,
                'dispatched_quantity' => 0,
                'delivered_quantity' => 0,
                'remaining_quantity' => $quantity,
                'freight_rate' => (float) ($data['freight_rate'] ?? 0.00),
                'freight_charge' => $freightCharge,
                'handling_charge' => $handlingCharge,
                'insurance_charge' => $insuranceCharge,
                'total_charges' => $totalCharges,
                'payment_mode' => $data['payment_mode'] ?? Consignment::PACKAGING_BAGS,
                'delivery_instructions' => $data['delivery_instructions'] ?? null,
                'status' => Consignment::STATUS_BOOKED,
            ]);

            // Save items if provided
            if (!empty($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $item) {
                    ConsignmentItem::create([
                        'consignment_id' => $consignment->id,
                        'item_description' => $item['description'] ?? 'Package Item',
                        'package_type' => $item['package_type'] ?? 'carton',
                        'quantity' => (int) ($item['quantity'] ?? 1),
                        'weight_kg' => (float) ($item['weight_kg'] ?? 0.00),
                        'declared_value' => !empty($item['declared_value']) ? (float) $item['declared_value'] : null,
                        'dimensions' => $item['dimensions'] ?? null,
                    ]);
                }
            }

            // Refresh parent booking cumulative counts
            $booking->refreshStatistics();

            $this->auditLog->log('consignment_created', $consignment, null, $consignment->toArray(), 'Consignment note issued.');

            return $consignment;
        });
    }

    /**
     * Authorized split dispatch process.
     * Enforces that dispatched quantity cannot exceed remaining undelivered quantity.
     *
     * @throws \InvalidArgumentException
     */
    public function splitAndDispatch(Consignment $consignment, int $dispatchQuantity, array $meta = []): array
    {
        if (!$consignment->canDispatch($dispatchQuantity)) {
            throw new \InvalidArgumentException(
                sprintf('Split dispatch failed: Requested [%d] packages exceeds remaining [%d] packages on Consignment [%s].', $dispatchQuantity, $consignment->remaining_quantity, $consignment->consignment_number)
            );
        }

        DB::transaction(function () use ($consignment, $dispatchQuantity, $meta) {
            $consignment->recordDispatch($dispatchQuantity);

            $this->auditLog->log('consignment_split_dispatched', $consignment, null, [
                'dispatched_units' => $dispatchQuantity,
                'remaining_units' => $consignment->remaining_quantity,
                'meta' => $meta,
            ], 'Consignment split quantity dispatched.');
        });

        return [
            'consignment_id' => $consignment->id,
            'consignment_number' => $consignment->consignment_number,
            'dispatched_quantity' => $consignment->dispatched_quantity,
            'remaining_quantity' => $consignment->remaining_quantity,
            'status' => $consignment->status,
        ];
    }
}


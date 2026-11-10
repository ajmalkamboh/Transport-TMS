<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Company;
use App\Models\Consignment;
use App\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function __construct(
        protected DocumentSequenceService $sequenceService,
        protected AuditLogService $auditLog
    ) {}

    /**
     * Create a direct cargo booking and automatically issue primary consignment note.
     */
    public function createBooking(Company $company, array $data, ?int $userId = null): Booking
    {
        return DB::transaction(function () use ($company, $data, $userId) {
            $user = $userId ?? auth()->id() ?? 1;

            $bookingNumber = $this->sequenceService->getNextSequenceNumber(
                DocumentSequence::TYPE_BOOKING,
                null,
                5,
                $company->id,
                $data['branch_id'] ?? null
            );

            $totalFreight = (float) ($data['total_freight'] ?? 0.00);
            $additionalCharges = (float) ($data['additional_charges'] ?? 0.00);
            $discountAmount = (float) ($data['discount_amount'] ?? 0.00);
            $taxAmount = (float) ($data['tax_amount'] ?? 0.00);
            $netAmount = max(0.00, round(($totalFreight + $additionalCharges - $discountAmount) + $taxAmount, 2));

            $advanceReceived = (float) ($data['advance_received'] ?? 0.00);
            $balanceAmount = max(0.00, round($netAmount - $advanceReceived, 2));

            $booking = Booking::create([
                'company_id' => $company->id,
                'branch_id' => $data['branch_id'] ?? null,
                'customer_id' => $data['customer_id'],
                'quotation_id' => $data['quotation_id'] ?? null,
                'customer_contract_id' => $data['customer_contract_id'] ?? null,
                'created_by' => $user,
                'booking_number' => $bookingNumber,
                'booking_date' => $data['booking_date'] ?? now()->toDateString(),
                'origin_city' => $data['origin_city'],
                'destination_city' => $data['destination_city'],
                'origin_address' => $data['origin_address'] ?? null,
                'destination_address' => $data['destination_address'] ?? null,
                'expected_dispatch_date' => $data['expected_dispatch_date'] ?? null,
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'payment_terms' => $data['payment_terms'] ?? Booking::TERMS_TO_PAY,
                'total_freight' => $totalFreight,
                'additional_charges' => $additionalCharges,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'net_amount' => $netAmount,
                'advance_received' => $advanceReceived,
                'advance_payment_method' => $data['advance_payment_method'] ?? null,
                'advance_receipt_reference' => $data['advance_receipt_reference'] ?? null,
                'advance_recorded_at' => $advanceReceived > 0 ? now() : null,
                'balance_amount' => $balanceAmount,
                'total_consignments_count' => 1,
                'total_packages_count' => (int) ($data['package_count'] ?? 1),
                'total_weight_tons' => (float) ($data['weight_tons'] ?? 0.00),
                'special_instructions' => $data['special_instructions'] ?? null,
                'status' => Booking::STATUS_CONFIRMED,
            ]);

            // Auto-create primary consignment note
            $consignmentNumber = $this->sequenceService->getNextSequenceNumber(
                DocumentSequence::TYPE_CONSIGNMENT,
                null,
                5,
                $company->id,
                $data['branch_id'] ?? null
            );

            $quantity = (int) ($data['package_count'] ?? 1);
            $weightTons = (float) ($data['weight_tons'] ?? 0.00);

            Consignment::create([
                'company_id' => $company->id,
                'branch_id' => $data['branch_id'] ?? null,
                'booking_id' => $booking->id,
                'customer_id' => $data['customer_id'],
                'created_by' => $user,
                'consignment_number' => $consignmentNumber,
                'consignment_date' => $booking->booking_date,
                'consignee_name' => $data['consignee_name'] ?? 'Consignee At Destination',
                'consignee_phone' => $data['consignee_phone'] ?? '0300-0000000',
                'consignee_cnic_or_ntn' => $data['consignee_cnic_or_ntn'] ?? null,
                'consignee_address' => $data['destination_address'] ?? null,
                'consignee_city' => $data['destination_city'],
                'pickup_point' => $data['origin_city'],
                'delivery_point' => $data['destination_city'],
                'cargo_description' => $data['cargo_description'] ?? 'General Freight Cargo',
                'cargo_category' => $data['cargo_category'] ?? 'GENERAL_CARGO',
                'packaging_type' => $data['packaging_type'] ?? Consignment::PACKAGING_BAGS,
                'declared_value' => $data['declared_value'] ?? null,
                'gross_weight_kg' => round($weightTons * 1000, 2),
                'chargeable_weight_kg' => round($weightTons * 1000, 2),
                'volume_cbm' => $data['volume_cbm'] ?? null,
                'total_quantity' => $quantity,
                'dispatched_quantity' => 0,
                'delivered_quantity' => 0,
                'remaining_quantity' => $quantity,
                'freight_rate' => $totalFreight,
                'freight_charge' => $totalFreight,
                'handling_charge' => $additionalCharges,
                'insurance_charge' => 0.00,
                'total_charges' => $netAmount,
                'payment_mode' => $booking->payment_terms === 'paid' ? 'paid' : 'to_pay',
                'delivery_instructions' => $data['special_instructions'] ?? null,
                'status' => Consignment::STATUS_BOOKED,
            ]);

            $this->auditLog->log('booking_created', $booking, null, $booking->toArray(), 'Direct cargo booking registered.');

            return $booking;
        });
    }

    /**
     * Record a customer cash or banking advance without revenue double-counting.
     */
    public function recordAdvance(
        Booking $booking,
        float $amount,
        string $paymentMethod,
        ?string $reference = null
    ): void {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Advance amount must be greater than zero.');
        }

        $booking->advance_received += $amount;
        $booking->balance_amount = max(0.00, round($booking->net_amount - $booking->advance_received, 2));
        $booking->advance_payment_method = $paymentMethod;
        $booking->advance_receipt_reference = $reference;
        $booking->advance_recorded_at = now();
        $booking->save();

        $this->auditLog->log('booking_advance_received', $booking, null, [
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'reference' => $reference,
            'balance_amount' => $booking->balance_amount,
        ], 'Customer advance received and credited.');
    }

    /**
     * Cancel a booking securely ensuring no active trips/dispatches are impacted.
     */
    public function cancelBooking(Booking $booking, string $reason): void
    {
        if (!$booking->canBeCancelled()) {
            throw new \InvalidArgumentException('Cannot cancel booking: active dispatches or completed deliveries already exist.');
        }

        DB::transaction(function () use ($booking, $reason) {
            $booking->status = Booking::STATUS_CANCELLED;
            $booking->cancelled_at = now();
            $booking->cancellation_reason = $reason;
            $booking->save();

            // Cancel any open un-dispatched consignments
            $booking->consignments()
                ->where('status', Consignment::STATUS_BOOKED)
                ->update(['status' => Consignment::STATUS_CANCELLED]);

            $this->auditLog->log('booking_cancelled', $booking, null, [
                'reason' => $reason,
                'status' => 'cancelled',
            ], 'Booking cancelled.');
        });
    }
}

<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Company;
use App\Models\Consignment;
use App\Models\CustomerContract;
use App\Models\DocumentSequence;
use App\Models\Quotation;
use Illuminate\Support\Facades\DB;

class QuotationService
{
    public function __construct(
        protected DocumentSequenceService $sequenceService,
        protected FreightCalculationService $freightCalc,
        protected AuditLogService $auditLog
    ) {}

    /**
     * Create a formal freight quotation with atomic numbering and fee computation.
     */
    public function createQuotation(Company $company, array $data, ?int $userId = null): Quotation
    {
        $contract = !empty($data['customer_contract_id'])
            ? CustomerContract::find($data['customer_contract_id'])
            : null;

        $calc = $this->freightCalc->calculateFreight(
            $data['rate_type'] ?? 'fixed',
            (float) ($data['base_rate'] ?? 0.00),
            [
                'weight_tons' => (float) ($data['weight_tons'] ?? 0.0),
                'distance_km' => (float) ($data['distance_km'] ?? 0.0),
                'quantity' => (int) ($data['package_count'] ?? 1),
            ],
            [
                'loading' => (float) ($data['loading_charges'] ?? 0.0),
                'unloading' => (float) ($data['unloading_charges'] ?? 0.0),
                'toll' => (float) ($data['toll_charges'] ?? 0.0),
                'detention' => (float) ($data['detention_charges'] ?? 0.0),
                'other' => (float) ($data['other_charges'] ?? 0.0),
                'flat_discount' => (float) ($data['flat_discount'] ?? 0.0),
            ],
            (float) ($data['discount_percentage'] ?? 0.0),
            (float) ($data['tax_percentage'] ?? 0.0),
            $contract,
            $data['origin_city'] ?? null,
            $data['destination_city'] ?? null
        );

        return DB::transaction(function () use ($company, $data, $calc, $userId) {
            $quotationNumber = $this->sequenceService->getNextSequenceNumber(
                DocumentSequence::TYPE_QUOTATION,
                null,
                5,
                $company->id,
                $data['branch_id'] ?? null
            );

            $quotation = Quotation::create([
                'company_id' => $company->id,
                'branch_id' => $data['branch_id'] ?? null,
                'customer_id' => $data['customer_id'],
                'customer_contract_id' => $data['customer_contract_id'] ?? null,
                'created_by' => $userId ?? auth()->id() ?? 1,
                'quotation_number' => $quotationNumber,
                'quotation_date' => $data['quotation_date'] ?? now()->toDateString(),
                'valid_until' => $data['valid_until'] ?? now()->addDays(15)->toDateString(),
                'origin_city' => $data['origin_city'],
                'destination_city' => $data['destination_city'],
                'origin_address' => $data['origin_address'] ?? null,
                'destination_address' => $data['destination_address'] ?? null,
                'cargo_description' => $data['cargo_description'],
                'cargo_category' => $data['cargo_category'] ?? 'GENERAL_CARGO',
                'weight_tons' => $data['weight_tons'] ?? 0.00,
                'volume_cbm' => $data['volume_cbm'] ?? null,
                'package_count' => $data['package_count'] ?? 1,
                'required_vehicle_category' => $data['required_vehicle_category'] ?? 'HCV',
                'required_vehicle_type' => $data['required_vehicle_type'] ?? 'TRAILER_22W',
                'rate_type' => $calc['rate_type'],
                'base_rate' => $calc['applied_base_rate'],
                'distance_km' => $data['distance_km'] ?? null,
                'freight_amount' => $calc['freight_amount'],
                'loading_charges' => $calc['loading_charges'],
                'unloading_charges' => $calc['unloading_charges'],
                'toll_charges' => $calc['toll_charges'],
                'detention_charges' => $calc['detention_charges'],
                'other_charges' => $calc['other_charges'],
                'discount_amount' => $calc['discount_amount'],
                'tax_percentage' => $calc['tax_percentage'],
                'tax_amount' => $calc['tax_amount'],
                'net_total_amount' => $calc['net_total_amount'],
                'status' => Quotation::STATUS_DRAFT,
                'terms_and_conditions' => $data['terms_and_conditions'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->auditLog->log('quotation_created', $quotation, null, $quotation->toArray(), 'Freight quotation created.');

            return $quotation;
        });
    }

    /**
     * Authorize and approve a freight quotation.
     */
    public function approveQuotation(Quotation $quotation, ?int $approverId = null): void
    {
        $approver = $approverId ?? auth()->id() ?? 1;

        $quotation->status = Quotation::STATUS_APPROVED;
        $quotation->approved_at = now();
        $quotation->approved_by = $approver;
        $quotation->save();

        $this->auditLog->log('quotation_approved', $quotation, null, ['status' => 'approved'], 'Quotation approved.');
    }

    /**
     * Convert an approved or sent quotation into a confirmed Booking.
     * Preserves exact original quoted rates and terms.
     */
    public function convertToBooking(Quotation $quotation, array $overrides = [], ?int $userId = null): Booking
    {
        if ($quotation->status === Quotation::STATUS_CONVERTED) {
            throw new \InvalidArgumentException('Quotation has already been converted into a booking.');
        }

        if ($quotation->status === Quotation::STATUS_REJECTED) {
            throw new \InvalidArgumentException('Cannot convert a rejected quotation.');
        }

        return DB::transaction(function () use ($quotation, $overrides, $userId) {
            $user = $userId ?? auth()->id() ?? 1;

            $bookingNumber = $this->sequenceService->getNextSequenceNumber(
                DocumentSequence::TYPE_BOOKING,
                null,
                5,
                $quotation->company_id,
                $quotation->branch_id
            );

            $advanceReceived = (float) ($overrides['advance_received'] ?? 0.00);
            $additionalCharges = $quotation->loading_charges + $quotation->unloading_charges + $quotation->toll_charges + $quotation->detention_charges + $quotation->other_charges;
            $netAmount = $quotation->net_total_amount;
            $balanceAmount = max(0.00, round($netAmount - $advanceReceived, 2));

            // 1. Create Booking preserving exact quoted pricing terms
            $booking = Booking::create([
                'company_id' => $quotation->company_id,
                'branch_id' => $quotation->branch_id,
                'customer_id' => $quotation->customer_id,
                'quotation_id' => $quotation->id,
                'customer_contract_id' => $quotation->customer_contract_id,
                'created_by' => $user,
                'booking_number' => $bookingNumber,
                'booking_date' => $overrides['booking_date'] ?? now()->toDateString(),
                'origin_city' => $quotation->origin_city,
                'destination_city' => $quotation->destination_city,
                'origin_address' => $quotation->origin_address,
                'destination_address' => $quotation->destination_address,
                'expected_dispatch_date' => $overrides['expected_dispatch_date'] ?? now()->addDay()->toDateString(),
                'expected_delivery_date' => $overrides['expected_delivery_date'] ?? now()->addDays(3)->toDateString(),
                'payment_terms' => $overrides['payment_terms'] ?? Booking::TERMS_TO_PAY,
                'total_freight' => $quotation->freight_amount,
                'additional_charges' => $additionalCharges,
                'discount_amount' => $quotation->discount_amount,
                'tax_amount' => $quotation->tax_amount,
                'net_amount' => $netAmount,
                'advance_received' => $advanceReceived,
                'advance_payment_method' => $overrides['advance_payment_method'] ?? null,
                'advance_receipt_reference' => $overrides['advance_receipt_reference'] ?? null,
                'advance_recorded_at' => $advanceReceived > 0 ? now() : null,
                'balance_amount' => $balanceAmount,
                'total_consignments_count' => 1,
                'total_packages_count' => $quotation->package_count,
                'total_weight_tons' => $quotation->weight_tons,
                'special_instructions' => $overrides['special_instructions'] ?? $quotation->notes,
                'status' => Booking::STATUS_CONFIRMED,
            ]);

            // 2. Automatically generate the primary Consignment (Bilty) under this booking
            $consignmentNumber = $this->sequenceService->getNextSequenceNumber(
                DocumentSequence::TYPE_CONSIGNMENT,
                null,
                5,
                $quotation->company_id,
                $quotation->branch_id
            );

            $customer = $quotation->customer;

            Consignment::create([
                'company_id' => $quotation->company_id,
                'branch_id' => $quotation->branch_id,
                'booking_id' => $booking->id,
                'customer_id' => $quotation->customer_id,
                'created_by' => $user,
                'consignment_number' => $consignmentNumber,
                'consignment_date' => $booking->booking_date,
                'consignee_name' => $overrides['consignee_name'] ?? $customer->name,
                'consignee_phone' => $overrides['consignee_phone'] ?? $customer->phone,
                'consignee_cnic_or_ntn' => $overrides['consignee_cnic_or_ntn'] ?? $customer->tax_ntn,
                'consignee_address' => $quotation->destination_address ?? $customer->billing_address,
                'consignee_city' => $quotation->destination_city,
                'pickup_point' => $quotation->origin_city,
                'delivery_point' => $quotation->destination_city,
                'cargo_description' => $quotation->cargo_description,
                'cargo_category' => $quotation->cargo_category,
                'packaging_type' => $overrides['packaging_type'] ?? Consignment::PACKAGING_BAGS,
                'declared_value' => $overrides['declared_value'] ?? null,
                'gross_weight_kg' => round($quotation->weight_tons * 1000, 2),
                'chargeable_weight_kg' => round($quotation->weight_tons * 1000, 2),
                'volume_cbm' => $quotation->volume_cbm,
                'total_quantity' => $quotation->package_count,
                'dispatched_quantity' => 0,
                'delivered_quantity' => 0,
                'remaining_quantity' => $quotation->package_count,
                'freight_rate' => $quotation->base_rate,
                'freight_charge' => $quotation->freight_amount,
                'handling_charge' => $additionalCharges,
                'insurance_charge' => 0.00,
                'total_charges' => $netAmount,
                'payment_mode' => $booking->payment_terms === 'paid' ? 'paid' : 'to_pay',
                'delivery_instructions' => $overrides['delivery_instructions'] ?? null,
                'status' => Consignment::STATUS_BOOKED,
            ]);

            // 3. Mark quotation as converted
            $quotation->update([
                'status' => Quotation::STATUS_CONVERTED,
                'converted_booking_id' => $booking->id,
            ]);

            $this->auditLog->log('quotation_converted', $quotation, null, [
                'converted_booking_id' => $booking->id,
                'booking_number' => $booking->booking_number,
            ], 'Quotation converted into confirmed booking.');

            return $booking;
        });
    }
}


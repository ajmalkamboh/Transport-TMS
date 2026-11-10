<?php

namespace App\Services;

use App\Models\CustomerContract;

class FreightCalculationService
{
    /**
     * Compute comprehensive freight calculation with additional charges, discounts, and taxes.
     *
     * @param string $rateType 'fixed', 'per_ton', 'per_km', 'per_vehicle', 'per_quantity', 'route_based', 'negotiated'
     * @param float $baseRate Flat rate, rate per ton, per km, or per unit
     * @param array $metrics e.g. ['weight_tons' => 30.0, 'distance_km' => 1250, 'quantity' => 500, 'vehicle_count' => 1]
     * @param array $additionalCharges e.g. ['loading' => 2000, 'unloading' => 2000, 'toll' => 5000, 'detention' => 0, 'other' => 0]
     * @param float $discountPercentage
     * @param float $taxPercentage
     * @param CustomerContract|null $contract
     * @param string|null $origin
     * @param string|null $destination
     * @return array
     */
    public function calculateFreight(
        string $rateType,
        float $baseRate,
        array $metrics = [],
        array $additionalCharges = [],
        float $discountPercentage = 0.0,
        float $taxPercentage = 0.0,
        ?CustomerContract $contract = null,
        ?string $origin = null,
        ?string $destination = null
    ): array {
        $weightTons = (float) ($metrics['weight_tons'] ?? 0.0);
        $distanceKm = (float) ($metrics['distance_km'] ?? 0.0);
        $quantity = (int) ($metrics['quantity'] ?? 1);
        $vehicleCount = (int) ($metrics['vehicle_count'] ?? 1);

        $appliedRate = $baseRate;
        $freightAmount = 0.00;

        // 1. Resolve Contract Tariff if available and rate type is route_based or contract-backed
        if ($contract && $origin && $destination && $rateType === 'route_based') {
            $appliedRate = $contract->getRateForRoute($origin, $destination);
        }

        // 2. Compute Base Freight based on Rate Type
        switch ($rateType) {
            case 'per_ton':
                $freightAmount = round($appliedRate * max(0.01, $weightTons), 2);
                break;

            case 'per_km':
                $freightAmount = round($appliedRate * max(1, $distanceKm), 2);
                break;

            case 'per_quantity':
                $freightAmount = round($appliedRate * max(1, $quantity), 2);
                break;

            case 'per_vehicle':
                $freightAmount = round($appliedRate * max(1, $vehicleCount), 2);
                break;

            case 'route_based':
            case 'fixed':
            case 'negotiated':
            default:
                $freightAmount = round($appliedRate, 2);
                break;
        }

        // 3. Additional Surcharges
        $loading = (float) ($additionalCharges['loading'] ?? ($contract ? (float) $contract->loading_charges : 0.00));
        $unloading = (float) ($additionalCharges['unloading'] ?? ($contract ? (float) $contract->unloading_charges : 0.00));
        $toll = (float) ($additionalCharges['toll'] ?? 0.00);
        $detention = (float) ($additionalCharges['detention'] ?? 0.00);
        $other = (float) ($additionalCharges['other'] ?? 0.00);

        $totalAdditionalCharges = round($loading + $unloading + $toll + $detention + $other, 2);
        $subtotal = round($freightAmount + $totalAdditionalCharges, 2);

        // 4. Discount
        $discountAmount = 0.00;
        if ($discountPercentage > 0) {
            $discountAmount = round($subtotal * ($discountPercentage / 100), 2);
        } elseif (!empty($additionalCharges['flat_discount'])) {
            $discountAmount = min($subtotal, (float) $additionalCharges['flat_discount']);
        }

        $afterDiscount = max(0.00, round($subtotal - $discountAmount, 2));

        // 5. Taxes (GST / Provincial Sales Tax)
        $taxAmount = 0.00;
        if ($taxPercentage > 0) {
            $taxAmount = round($afterDiscount * ($taxPercentage / 100), 2);
        }

        $netTotalAmount = round($afterDiscount + $taxAmount, 2);

        return [
            'rate_type' => $rateType,
            'applied_base_rate' => $appliedRate,
            'freight_amount' => $freightAmount,
            'loading_charges' => $loading,
            'unloading_charges' => $unloading,
            'toll_charges' => $toll,
            'detention_charges' => $detention,
            'other_charges' => $other,
            'total_additional_charges' => $totalAdditionalCharges,
            'subtotal' => $subtotal,
            'discount_percentage' => $discountPercentage,
            'discount_amount' => $discountAmount,
            'tax_percentage' => $taxPercentage,
            'tax_amount' => $taxAmount,
            'net_total_amount' => $netTotalAmount,
        ];
    }
}

<?php

namespace App\Services;

use App\Models\OwnerContract;

class OwnerContractService
{
    /**
     * Calculate gross freight hire, contract deductions, and net payable for an owner contract.
     *
     * @param OwnerContract $contract
     * @param float $freightAmount Total freight billed or customer quotation amount
     * @param float $kilometers Actual distance covered (for per_km model)
     * @param array $deductions Optional deductions: ['fuel' => 15000, 'toll' => 2000, 'advances' => 10000, 'other' => 500]
     * @return array
     */
    public function calculateOwnerPayable(
        OwnerContract $contract,
        float $freightAmount,
        float $kilometers = 0.0,
        array $deductions = []
    ): array {
        $grossHire = 0.00;

        switch ($contract->payment_model) {
            case OwnerContract::MODEL_PERCENTAGE_OF_FREIGHT:
                // Owner receives configured % of freight (e.g. 85%)
                $pct = (float) $contract->freight_percentage;
                $grossHire = round($freightAmount * ($pct / 100), 2);
                break;

            case OwnerContract::MODEL_PER_KM:
                // Rate per kilometer
                $grossHire = round($kilometers * (float) $contract->rate_amount, 2);
                break;

            case OwnerContract::MODEL_MONTHLY_RENTAL:
            case OwnerContract::MODEL_FIXED_PER_TRIP:
            case OwnerContract::MODEL_ROUTE_SPECIFIC:
            default:
                // Flat rate amount
                $grossHire = (float) $contract->rate_amount;
                break;
        }

        // Calculate Deductions
        $deductionsBreakdown = [];
        $totalDeductions = 0.00;

        // 1. Company Commission (if applicable)
        if ((float) $contract->commission_percentage > 0) {
            $commAmount = round($freightAmount * ((float) $contract->commission_percentage / 100), 2);
            $deductionsBreakdown['company_commission'] = $commAmount;
            $totalDeductions += $commAmount;
        }

        // 2. Fuel deduction
        if ($contract->deduct_fuel && !empty($deductions['fuel'])) {
            $fuelDeduction = (float) $deductions['fuel'];
            $deductionsBreakdown['fuel_provided'] = $fuelDeduction;
            $totalDeductions += $fuelDeduction;
        }

        // 3. Toll Taxes deduction
        if ($contract->deduct_toll_taxes && !empty($deductions['toll'])) {
            $tollDeduction = (float) $deductions['toll'];
            $deductionsBreakdown['toll_taxes'] = $tollDeduction;
            $totalDeductions += $tollDeduction;
        }

        // 4. Trip Cash Advances to Driver
        if ($contract->deduct_advances && !empty($deductions['advances'])) {
            $advanceDeduction = (float) $deductions['advances'];
            $deductionsBreakdown['driver_advances'] = $advanceDeduction;
            $totalDeductions += $advanceDeduction;
        }

        // 5. Other custom penalties or charges
        if (!empty($deductions['other'])) {
            $otherDeduction = (float) $deductions['other'];
            $deductionsBreakdown['other_deductions'] = $otherDeduction;
            $totalDeductions += $otherDeduction;
        }

        $netPayable = max(0.00, round($grossHire - $totalDeductions, 2));

        return [
            'payment_model' => $contract->payment_model,
            'freight_amount' => $freightAmount,
            'gross_hire' => $grossHire,
            'deductions' => $deductionsBreakdown,
            'total_deductions' => $totalDeductions,
            'net_payable' => $netPayable,
        ];
    }
}


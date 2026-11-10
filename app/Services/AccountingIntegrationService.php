<?php

namespace App\Services;

use App\Models\FuelEntry;
use App\Models\JobCard;
use App\Models\TripExpense;
use Illuminate\Support\Str;

class AccountingIntegrationService
{
    public function __construct(
        protected AuditLogService $auditLog
    ) {}

    /**
     * Build financial transaction payload for fuel purchase & consumption.
     */
    public function buildFuelAccountingPayload(FuelEntry $entry): array
    {
        return [
            'idempotency_key' => "FUEL-{$entry->id}-{$entry->entry_number}",
            'transaction_type' => 'FUEL_EXPENSE',
            'company_id' => $entry->company_id,
            'branch_id' => $entry->branch_id,
            'reference_number' => $entry->entry_number,
            'transaction_date' => $entry->entry_date->toDateString(),
            'total_amount' => (float) $entry->total_amount,
            'fuel_liters' => (float) $entry->quantity_liters,
            'rate_per_liter' => (float) $entry->rate_per_liter,
            'vehicle_id' => $entry->vehicle_id,
            'trip_id' => $entry->trip_id,
            'entries' => [
                [
                    'account_type' => 'EXPENSE',
                    'account_code' => '5100-DIRECT-FUEL',
                    'description' => "Diesel/Fuel Consumption - Vehicle {$entry->vehicle?->registration_number}",
                    'debit' => (float) $entry->total_amount,
                    'credit' => 0.00,
                ],
                [
                    'account_type' => $entry->payment_method === 'credit' ? 'LIABILITY' : 'ASSET',
                    'account_code' => $entry->payment_method === 'credit'
                        ? '2100-ACCOUNTS-PAYABLE-FUEL'
                        : '1010-CASH-OR-BANK',
                    'description' => "Settlement via {$entry->payment_method}",
                    'debit' => 0.00,
                    'credit' => (float) $entry->total_amount,
                ],
            ],
        ];
    }

    /**
     * Build financial transaction payload for posted trip expense voucher.
     */
    public function buildTripExpenseAccountingPayload(TripExpense $expense): array
    {
        return [
            'idempotency_key' => "EXPENSE-{$expense->id}-{$expense->voucher_number}",
            'transaction_type' => 'TRIP_OPERATIONAL_EXPENSE',
            'company_id' => $expense->company_id,
            'branch_id' => $expense->branch_id,
            'reference_number' => $expense->voucher_number,
            'transaction_date' => $expense->expense_date->toDateString(),
            'total_amount' => (float) $expense->amount,
            'expense_category' => $expense->expense_category,
            'trip_id' => $expense->trip_id,
            'vehicle_id' => $expense->vehicle_id,
            'entries' => [
                [
                    'account_type' => 'EXPENSE',
                    'account_code' => "5200-TRIP-{$expense->expense_category}",
                    'description' => ucfirst(str_replace('_', ' ', $expense->expense_category)),
                    'debit' => (float) $expense->amount,
                    'credit' => 0.00,
                ],
                [
                    'account_type' => $expense->payment_method === 'driver_advance' ? 'ASSET_CLEARING' : 'ASSET',
                    'account_code' => $expense->payment_method === 'driver_advance'
                        ? '1150-DRIVER-ADVANCE-CLEARING'
                        : '1010-CASH-IN-HAND',
                    'description' => "Payment via {$expense->payment_method}",
                    'debit' => 0.00,
                    'credit' => (float) $expense->amount,
                ],
            ],
        ];
    }

    /**
     * Build financial transaction payload for completed maintenance job card.
     */
    public function buildMaintenanceAccountingPayload(JobCard $jobCard): array
    {
        return [
            'idempotency_key' => "MAINT-{$jobCard->id}-{$jobCard->job_card_number}",
            'transaction_type' => 'VEHICLE_MAINTENANCE_EXPENSE',
            'company_id' => $jobCard->company_id,
            'branch_id' => $jobCard->branch_id,
            'reference_number' => $jobCard->job_card_number,
            'transaction_date' => $jobCard->completed_at ? $jobCard->completed_at->toDateString() : now()->toDateString(),
            'vehicle_id' => $jobCard->vehicle_id,
            'labour_charges' => (float) $jobCard->labour_charges,
            'parts_cost' => (float) $jobCard->parts_total_cost,
            'total_cost' => (float) $jobCard->total_cost,
            'entries' => [
                [
                    'account_type' => 'EXPENSE',
                    'account_code' => '5300-VEHICLE-REPAIR-MAINTENANCE',
                    'description' => "Parts & Repairs - Vehicle {$jobCard->vehicle?->registration_number}",
                    'debit' => (float) $jobCard->parts_total_cost,
                    'credit' => 0.00,
                ],
                [
                    'account_type' => 'EXPENSE',
                    'account_code' => '5310-WORKSHOP-LABOUR-CHARGES',
                    'description' => "Technician / Workshop Labour - Vehicle {$jobCard->vehicle?->registration_number}",
                    'debit' => (float) $jobCard->labour_charges,
                    'credit' => 0.00,
                ],
                [
                    'account_type' => $jobCard->workshop_type === 'third_party' ? 'LIABILITY' : 'ASSET',
                    'account_code' => $jobCard->workshop_type === 'third_party'
                        ? '2120-ACCOUNTS-PAYABLE-WORKSHOPS'
                        : '1010-CASH-OR-INVENTORY',
                    'description' => "Settlement to {$jobCard->workshop_name}",
                    'debit' => 0.00,
                    'credit' => (float) $jobCard->total_cost,
                ],
            ],
        ];
    }
}

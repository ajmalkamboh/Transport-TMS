<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\FuelEntry;
use App\Models\JobCard;
use App\Models\JournalVoucher;
use App\Models\OwnerPayment;
use App\Models\OwnerSettlement;
use App\Models\TripExpense;
use App\Models\User;
use Illuminate\Support\Str;

class AccountingIntegrationService
{
    public function __construct(
        protected AuditLogService $auditLog,
        protected ?GeneralLedgerService $generalLedger = null,
        protected ?ChartOfAccountsService $coaService = null
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
     * Post Fuel Entry to General Ledger.
     */
    public function postFuelAccounting(FuelEntry $entry, ?User $user = null): ?JournalVoucher
    {
        if (!$this->generalLedger) {
            return null;
        }

        $payload = $this->buildFuelAccountingPayload($entry);
        $items = [];

        foreach ($payload['entries'] as $e) {
            $items[] = [
                'account_code' => strtok($e['account_code'], '-'),
                'debit' => $e['debit'],
                'credit' => $e['credit'],
                'description' => $e['description'],
                'vehicle_id' => $entry->vehicle_id,
                'trip_id' => $entry->trip_id,
                'fuel_supplier_id' => $entry->fuel_supplier_id,
            ];
        }

        return $this->generalLedger->postVoucher([
            'company_id' => $entry->company_id,
            'branch_id' => $entry->branch_id,
            'voucher_date' => $entry->entry_date->toDateString(),
            'voucher_type' => JournalVoucher::TYPE_JOURNAL,
            'source_module' => 'fuel_entry',
            'source_id' => $entry->id,
            'idempotency_key' => $payload['idempotency_key'],
            'reference_number' => $entry->entry_number,
            'narration' => "Fuel Purchase & Issue {$entry->entry_number} ({$entry->quantity_liters} Liters)",
            'trip_id' => $entry->trip_id,
            'vehicle_id' => $entry->vehicle_id,
            'items' => $items,
        ], $user);
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
                    'account_code' => $expense->expense_category === 'driver_allowance' ? '5210-DRIVER-ALLOWANCES' : '5200-TRIP-EXPENSES',
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
     * Post Trip Expense to General Ledger.
     */
    public function postTripExpenseAccounting(TripExpense $expense, ?User $user = null): ?JournalVoucher
    {
        if (!$this->generalLedger) {
            return null;
        }

        $payload = $this->buildTripExpenseAccountingPayload($expense);
        $items = [];

        foreach ($payload['entries'] as $e) {
            $items[] = [
                'account_code' => strtok($e['account_code'], '-'),
                'debit' => $e['debit'],
                'credit' => $e['credit'],
                'description' => $e['description'],
                'trip_id' => $expense->trip_id,
                'vehicle_id' => $expense->vehicle_id,
            ];
        }

        return $this->generalLedger->postVoucher([
            'company_id' => $expense->company_id,
            'branch_id' => $expense->branch_id,
            'voucher_date' => $expense->expense_date->toDateString(),
            'voucher_type' => JournalVoucher::TYPE_JOURNAL,
            'source_module' => 'trip_expense',
            'source_id' => $expense->id,
            'idempotency_key' => $payload['idempotency_key'],
            'reference_number' => $expense->voucher_number,
            'narration' => "Trip Expense {$expense->voucher_number} - {$expense->expense_category}",
            'trip_id' => $expense->trip_id,
            'vehicle_id' => $expense->vehicle_id,
            'items' => $items,
        ], $user);
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
                    'account_code' => '5400-VEHICLE-MAINTENANCE',
                    'description' => "Parts & Repairs - Vehicle {$jobCard->vehicle?->registration_number}",
                    'debit' => (float) $jobCard->parts_total_cost,
                    'credit' => 0.00,
                ],
                [
                    'account_type' => 'EXPENSE',
                    'account_code' => '5400-VEHICLE-MAINTENANCE',
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

    /**
     * Post Maintenance Job Card to General Ledger.
     */
    public function postMaintenanceAccounting(JobCard $jobCard, ?User $user = null): ?JournalVoucher
    {
        if (!$this->generalLedger) {
            return null;
        }

        $payload = $this->buildMaintenanceAccountingPayload($jobCard);
        $items = [];

        foreach ($payload['entries'] as $e) {
            if ($e['debit'] > 0 || $e['credit'] > 0) {
                $items[] = [
                    'account_code' => strtok($e['account_code'], '-'),
                    'debit' => $e['debit'],
                    'credit' => $e['credit'],
                    'description' => $e['description'],
                    'vehicle_id' => $jobCard->vehicle_id,
                ];
            }
        }

        return $this->generalLedger->postVoucher([
            'company_id' => $jobCard->company_id,
            'branch_id' => $jobCard->branch_id,
            'voucher_date' => $payload['transaction_date'],
            'voucher_type' => JournalVoucher::TYPE_JOURNAL,
            'source_module' => 'maintenance_job_card',
            'source_id' => $jobCard->id,
            'idempotency_key' => $payload['idempotency_key'],
            'reference_number' => $jobCard->job_card_number,
            'narration' => "Maintenance Workshop Job Card {$jobCard->job_card_number}",
            'vehicle_id' => $jobCard->vehicle_id,
            'items' => $items,
        ], $user);
    }

    /**
     * Build financial transaction payload for attached vehicle owner settlement.
     * Enforces strict financial separation between customer freight revenue and owner freight expense.
     */
    public function buildOwnerSettlementAccountingPayload(OwnerSettlement $settlement): array
    {
        $grossCost = (float) $settlement->agreed_owner_payable + (float) $settlement->additional_charges;

        $entries = [
            [
                'account_type' => 'EXPENSE',
                'account_code' => '5300-ATTACHED-TRUCK-HIRE-EXPENSE',
                'description' => "Owner Freight & Hire - Vehicle {$settlement->vehicle?->registration_number} (Trip: {$settlement->trip?->trip_number})",
                'debit' => $grossCost,
                'credit' => 0.00,
            ],
        ];

        if ($settlement->fuel_deduction > 0) {
            $entries[] = [
                'account_type' => 'ASSET_CONTRA',
                'account_code' => '1160-FUEL-ADVANCE-CLEARING',
                'description' => "Fuel supplied by company deduction",
                'debit' => 0.00,
                'credit' => (float) $settlement->fuel_deduction,
            ];
        }

        if ($settlement->repair_deduction > 0) {
            $entries[] = [
                'account_type' => 'EXPENSE_RECOVERY',
                'account_code' => '5400-VEHICLE-MAINTENANCE',
                'description' => "Repairs borne by company recovery",
                'debit' => 0.00,
                'credit' => (float) $settlement->repair_deduction,
            ];
        }

        if ($settlement->advance_deduction > 0) {
            $entries[] = [
                'account_type' => 'ASSET_CONTRA',
                'account_code' => '1150-DRIVER-ADVANCE-CLEARING',
                'description' => "Trip advance recovery",
                'debit' => 0.00,
                'credit' => (float) $settlement->advance_deduction,
            ];
        }

        if ($settlement->penalty_deduction > 0) {
            $entries[] = [
                'account_type' => 'INCOME',
                'account_code' => '4900-PENALTIES-INCOME',
                'description' => "Contract penalty deduction",
                'debit' => 0.00,
                'credit' => (float) $settlement->penalty_deduction,
            ];
        }

        if ($settlement->other_deductions > 0) {
            $entries[] = [
                'account_type' => 'LIABILITY_CLEARING',
                'account_code' => '2100-AP-CLEARING',
                'description' => "Other approved deductions",
                'debit' => 0.00,
                'credit' => (float) $settlement->other_deductions,
            ];
        }

        // Net Payable Liability
        $entries[] = [
            'account_type' => 'LIABILITY',
            'account_code' => '2150-ACCOUNTS-PAYABLE-VEHICLE-OWNER',
            'description' => "Net Payable to Owner {$settlement->owner?->name}",
            'debit' => 0.00,
            'credit' => (float) $settlement->net_payable,
        ];

        return [
            'idempotency_key' => "OWNER-SETTLE-{$settlement->id}-{$settlement->settlement_number}",
            'transaction_type' => 'OWNER_SETTLEMENT',
            'company_id' => $settlement->company_id,
            'branch_id' => $settlement->branch_id,
            'reference_number' => $settlement->settlement_number,
            'transaction_date' => $settlement->settlement_date->toDateString(),
            'vehicle_owner_id' => $settlement->vehicle_owner_id,
            'vehicle_id' => $settlement->vehicle_id,
            'trip_id' => $settlement->trip_id,
            'customer_freight_reference' => (float) $settlement->customer_freight_total,
            'agreed_gross_payable' => (float) $settlement->agreed_owner_payable,
            'total_deductions' => (float) $settlement->total_deductions,
            'additional_charges' => (float) $settlement->additional_charges,
            'net_payable' => (float) $settlement->net_payable,
            'entries' => $entries,
        ];
    }

    /**
     * Post Owner Settlement to General Ledger.
     */
    public function postOwnerSettlementAccounting(OwnerSettlement $settlement, ?User $user = null): ?JournalVoucher
    {
        if (!$this->generalLedger) {
            return null;
        }

        $payload = $this->buildOwnerSettlementAccountingPayload($settlement);
        $items = [];

        foreach ($payload['entries'] as $e) {
            if ($e['debit'] > 0 || $e['credit'] > 0) {
                $items[] = [
                    'account_code' => strtok($e['account_code'], '-'),
                    'debit' => $e['debit'],
                    'credit' => $e['credit'],
                    'description' => $e['description'],
                    'vehicle_owner_id' => $settlement->vehicle_owner_id,
                    'vehicle_id' => $settlement->vehicle_id,
                    'trip_id' => $settlement->trip_id,
                ];
            }
        }

        return $this->generalLedger->postVoucher([
            'company_id' => $settlement->company_id,
            'branch_id' => $settlement->branch_id,
            'voucher_date' => $settlement->settlement_date->toDateString(),
            'voucher_type' => JournalVoucher::TYPE_JOURNAL,
            'source_module' => 'owner_settlement',
            'source_id' => $settlement->id,
            'idempotency_key' => $payload['idempotency_key'],
            'reference_number' => $settlement->settlement_number,
            'narration' => "Vehicle Owner Settlement {$settlement->settlement_number} - Owner: {$settlement->owner?->name}",
            'trip_id' => $settlement->trip_id,
            'vehicle_id' => $settlement->vehicle_id,
            'items' => $items,
        ], $user);
    }

    /**
     * Build financial transaction payload for owner payment.
     */
    public function buildOwnerPaymentAccountingPayload(OwnerPayment $payment): array
    {
        return [
            'idempotency_key' => "OWNER-PAY-{$payment->id}-{$payment->payment_number}",
            'transaction_type' => 'OWNER_PAYMENT',
            'company_id' => $payment->company_id,
            'branch_id' => $payment->branch_id,
            'reference_number' => $payment->payment_number,
            'transaction_date' => $payment->payment_date->toDateString(),
            'vehicle_owner_id' => $payment->vehicle_owner_id,
            'amount' => (float) $payment->amount,
            'payment_method' => $payment->payment_method,
            'payment_reference' => $payment->payment_reference ?? $payment->cheque_number,
            'entries' => [
                [
                    'account_type' => 'LIABILITY',
                    'account_code' => '2150-ACCOUNTS-PAYABLE-VEHICLE-OWNER',
                    'description' => "Payment to Owner {$payment->owner?->name}",
                    'debit' => (float) $payment->amount,
                    'credit' => 0.00,
                ],
                [
                    'account_type' => 'ASSET',
                    'account_code' => $payment->payment_method === 'cash' ? '1010-CASH-IN-HAND' : '1020-BANK-ACCOUNTS',
                    'description' => "Disbursement via {$payment->payment_method} ({$payment->payment_reference})",
                    'debit' => 0.00,
                    'credit' => (float) $payment->amount,
                ],
            ],
        ];
    }

    /**
     * Post Owner Payment to General Ledger.
     */
    public function postOwnerPaymentAccounting(OwnerPayment $payment, ?User $user = null): ?JournalVoucher
    {
        if (!$this->generalLedger) {
            return null;
        }

        $payload = $this->buildOwnerPaymentAccountingPayload($payment);
        $items = [];

        foreach ($payload['entries'] as $e) {
            $items[] = [
                'account_code' => strtok($e['account_code'], '-'),
                'debit' => $e['debit'],
                'credit' => $e['credit'],
                'description' => $e['description'],
                'vehicle_owner_id' => $payment->vehicle_owner_id,
            ];
        }

        $voucherType = $payment->payment_method === 'cash' ? JournalVoucher::TYPE_CASH_PAYMENT : JournalVoucher::TYPE_BANK_PAYMENT;

        return $this->generalLedger->postVoucher([
            'company_id' => $payment->company_id,
            'branch_id' => $payment->branch_id,
            'voucher_date' => $payment->payment_date->toDateString(),
            'voucher_type' => $voucherType,
            'source_module' => 'owner_payment',
            'source_id' => $payment->id,
            'idempotency_key' => $payload['idempotency_key'],
            'reference_number' => $payment->payment_number,
            'narration' => "Disbursement to Vehicle Owner {$payment->owner?->name} - Ref: {$payment->payment_number}",
            'items' => $items,
        ], $user);
    }
}

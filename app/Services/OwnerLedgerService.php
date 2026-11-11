<?php

namespace App\Services;

use App\Models\Company;
use App\Models\OwnerLedger;
use App\Models\VehicleOwner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OwnerLedgerService
{
    /**
     * Record a financial transaction in the owner ledger with atomic running balance calculation.
     */
    public function recordEntry(
        int $companyId,
        int $vehicleOwnerId,
        string $transactionType,
        string $entryType,
        float $amount,
        string $description,
        ?string $transactionDate = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $referenceNumber = null,
        ?int $tripId = null,
        ?int $branchId = null,
        ?int $userId = null
    ): OwnerLedger {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Ledger transaction amount cannot be negative.');
        }

        $transactionDate = $transactionDate ? Carbon::parse($transactionDate)->toDateString() : now()->toDateString();

        return DB::transaction(function () use (
            $companyId,
            $vehicleOwnerId,
            $transactionType,
            $entryType,
            $amount,
            $description,
            $transactionDate,
            $referenceType,
            $referenceId,
            $referenceNumber,
            $tripId,
            $branchId,
            $userId
        ) {
            // Pessimistic lock on last ledger record for this owner to maintain accurate running balance
            $lastEntry = OwnerLedger::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('vehicle_owner_id', $vehicleOwnerId)
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();

            $previousBalance = $lastEntry ? (float) $lastEntry->running_balance : 0.00;

            // In owner liability accounting:
            // Credit increases amount payable to owner
            // Debit decreases amount payable to owner
            if ($entryType === OwnerLedger::ENTRY_CREDIT) {
                $runningBalance = round($previousBalance + $amount, 2);
            } else {
                $runningBalance = round($previousBalance - $amount, 2);
            }

            return OwnerLedger::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'vehicle_owner_id' => $vehicleOwnerId,
                'trip_id' => $tripId,
                'transaction_date' => $transactionDate,
                'transaction_type' => $transactionType,
                'entry_type' => $entryType,
                'amount' => $amount,
                'running_balance' => $runningBalance,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reference_number' => $referenceNumber,
                'description' => $description,
                'created_by' => $userId,
            ]);
        });
    }

    /**
     * Record opening balance for a vehicle owner.
     */
    public function recordOpeningBalance(
        VehicleOwner $owner,
        float $amount,
        string $entryType = OwnerLedger::ENTRY_CREDIT,
        ?string $date = null,
        ?int $userId = null
    ): OwnerLedger {
        return $this->recordEntry(
            $owner->company_id,
            $owner->id,
            OwnerLedger::TYPE_OPENING_BALANCE,
            $entryType,
            abs($amount),
            'Opening Balance carry-forward',
            $date,
            null,
            null,
            'OB-' . $owner->id,
            null,
            null,
            $userId
        );
    }

    /**
     * Record adjustment transaction (e.g. credit note or debit note).
     */
    public function recordAdjustment(
        VehicleOwner $owner,
        string $entryType,
        float $amount,
        string $reason,
        ?string $referenceNumber = null,
        ?int $userId = null
    ): OwnerLedger {
        $transactionType = $entryType === OwnerLedger::ENTRY_CREDIT
            ? OwnerLedger::TYPE_ADJUSTMENT_CREDIT
            : OwnerLedger::TYPE_ADJUSTMENT_DEBIT;

        return $this->recordEntry(
            $owner->company_id,
            $owner->id,
            $transactionType,
            $entryType,
            $amount,
            'Adjustment: ' . $reason,
            null,
            null,
            null,
            $referenceNumber ?? ('ADJ-' . strtoupper(uniqid())),
            null,
            null,
            $userId
        );
    }

    /**
     * Get owner ledger summary with opening and closing balances for a given date range.
     */
    public function getOwnerLedgerSummary(
        VehicleOwner $owner,
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {
        $query = OwnerLedger::where('company_id', $owner->company_id)
            ->where('vehicle_owner_id', $owner->id)
            ->orderBy('transaction_date')
            ->orderBy('id');

        $openingBalance = 0.00;

        if ($fromDate) {
            $priorLastEntry = OwnerLedger::withoutGlobalScopes()
                ->where('company_id', $owner->company_id)
                ->where('vehicle_owner_id', $owner->id)
                ->where('transaction_date', '<', $fromDate)
                ->orderByDesc('id')
                ->first();

            $openingBalance = $priorLastEntry ? (float) $priorLastEntry->running_balance : 0.00;
            $query->where('transaction_date', '>=', $fromDate);
        } else {
            $obEntry = OwnerLedger::withoutGlobalScopes()
                ->where('company_id', $owner->company_id)
                ->where('vehicle_owner_id', $owner->id)
                ->where('transaction_type', OwnerLedger::TYPE_OPENING_BALANCE)
                ->first();

            if ($obEntry) {
                $openingBalance = (float) $obEntry->amount;
            }
        }

        if ($toDate) {
            $query->where('transaction_date', '<=', $toDate);
        }

        $entries = $query->get();

        $totalPayables = (float) $entries->where('transaction_type', OwnerLedger::TYPE_TRIP_PAYABLE)->sum('amount');
        $totalDeductions = (float) $entries->where('transaction_type', OwnerLedger::TYPE_DEDUCTION)->sum('amount');
        $totalAdditional = (float) $entries->where('transaction_type', OwnerLedger::TYPE_ADDITIONAL_CHARGE)->sum('amount');
        $totalAdvances = (float) $entries->where('transaction_type', OwnerLedger::TYPE_ADVANCE)->sum('amount');
        $totalPayments = (float) $entries->where('transaction_type', OwnerLedger::TYPE_PAYMENT)->sum('amount');
        $totalAdjustmentsCredit = (float) $entries->where('transaction_type', OwnerLedger::TYPE_ADJUSTMENT_CREDIT)->sum('amount');
        $totalAdjustmentsDebit = (float) $entries->where('transaction_type', OwnerLedger::TYPE_ADJUSTMENT_DEBIT)->sum('amount');
        $totalReversals = (float) $entries->where('transaction_type', OwnerLedger::TYPE_REVERSAL)->sum('amount');

        $lastEntry = $entries->last();
        $closingBalance = $lastEntry ? (float) $lastEntry->running_balance : $openingBalance;

        return [
            'owner_id' => $owner->id,
            'owner_name' => $owner->name,
            'company_name' => $owner->company_name,
            'opening_balance' => $openingBalance,
            'total_trip_payables' => $totalPayables,
            'total_deductions' => $totalDeductions,
            'total_additional_charges' => $totalAdditional,
            'total_advances' => $totalAdvances,
            'total_payments' => $totalPayments,
            'total_adjustments_credit' => $totalAdjustmentsCredit,
            'total_adjustments_debit' => $totalAdjustmentsDebit,
            'total_reversals' => $totalReversals,
            'closing_balance' => $closingBalance,
            'entries_count' => $entries->count(),
            'entries' => $entries,
        ];
    }
}


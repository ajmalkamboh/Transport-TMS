<?php

namespace App\Services;

use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\Trip;
use App\Models\TripExpense;
use Illuminate\Support\Facades\DB;

class TripExpenseService
{
    public function __construct(
        protected DocumentSequenceService $sequenceService,
        protected AuditLogService $auditLog
    ) {}

    /**
     * Record a new trip or operational expense voucher.
     */
    public function recordExpense(Company $company, array $data, ?int $userId = null): TripExpense
    {
        return DB::transaction(function () use ($company, $data, $userId) {
            $voucherNumber = $this->sequenceService->getNextSequenceNumber(
                DocumentSequence::TYPE_EXPENSE_VOUCHER,
                null,
                5,
                $company->id,
                $data['branch_id'] ?? null
            );

            $expense = TripExpense::create([
                'company_id' => $company->id,
                'branch_id' => $data['branch_id'] ?? null,
                'voucher_number' => $voucherNumber,
                'expense_date' => $data['expense_date'] ?? now()->toDateString(),
                'trip_id' => $data['trip_id'] ?? null,
                'vehicle_id' => $data['vehicle_id'] ?? null,
                'driver_id' => $data['driver_id'] ?? null,
                'expense_category' => $data['expense_category'],
                'amount' => (float) $data['amount'],
                'payment_method' => $data['payment_method'] ?? 'cash',
                'supporting_document_path' => $data['supporting_document_path'] ?? null,
                'status' => $data['status'] ?? TripExpense::STATUS_DRAFT,
                'recorded_by' => $userId ?? auth()->id() ?? 1,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->auditLog->log('expense_voucher_created', $expense, null, [
                'voucher' => $voucherNumber,
                'amount' => $expense->amount,
                'category' => $expense->expense_category,
            ], 'New trip expense voucher recorded.');

            return $expense;
        });
    }

    /**
     * Submit draft expense voucher for managerial approval.
     *
     * @throws \InvalidArgumentException
     */
    public function submitExpense(TripExpense $expense): TripExpense
    {
        if (!in_array($expense->status, [TripExpense::STATUS_DRAFT, TripExpense::STATUS_REJECTED])) {
            throw new \InvalidArgumentException(
                sprintf('Expense [%s] cannot be submitted from status [%s].', $expense->voucher_number, $expense->status)
            );
        }

        $expense->status = TripExpense::STATUS_SUBMITTED;
        $expense->rejection_reason = null;
        $expense->save();

        $this->auditLog->log('expense_submitted', $expense, null, [], 'Expense submitted for verification.');

        return $expense;
    }

    /**
     * Approve submitted expense voucher.
     *
     * @throws \InvalidArgumentException
     */
    public function approveExpense(TripExpense $expense, ?int $approvedById = null): TripExpense
    {
        if ($expense->status !== TripExpense::STATUS_SUBMITTED) {
            throw new \InvalidArgumentException(
                sprintf('Expense [%s] must be submitted before it can be approved.', $expense->voucher_number)
            );
        }

        $expense->status = TripExpense::STATUS_APPROVED;
        $expense->approved_by = $approvedById ?? auth()->id() ?? 1;
        $expense->approved_at = now();
        $expense->save();

        $this->auditLog->log('expense_approved', $expense, null, [
            'approved_by' => $expense->approved_by,
        ], 'Expense voucher approved.');

        return $expense;
    }

    /**
     * Reject submitted expense voucher.
     *
     * @throws \InvalidArgumentException
     */
    public function rejectExpense(TripExpense $expense, string $reason, ?int $rejectedById = null): TripExpense
    {
        if ($expense->status !== TripExpense::STATUS_SUBMITTED) {
            throw new \InvalidArgumentException(
                sprintf('Expense [%s] must be submitted before it can be rejected.', $expense->voucher_number)
            );
        }

        $expense->status = TripExpense::STATUS_REJECTED;
        $expense->rejection_reason = $reason;
        $expense->save();

        $this->auditLog->log('expense_rejected', $expense, null, [
            'reason' => $reason,
        ], 'Expense voucher rejected.');

        return $expense;
    }

    /**
     * Post approved expense voucher to general ledger / trip settlement.
     * Enforces strict idempotency and duplicate posting prevention.
     *
     * @throws \LogicException|\InvalidArgumentException
     */
    public function postExpense(TripExpense $expense, ?int $postedById = null): TripExpense
    {
        return DB::transaction(function () use ($expense, $postedById) {
            // Lock record to prevent concurrent double-posting
            $lockedExpense = TripExpense::where('id', $expense->id)->lockForUpdate()->firstOrFail();

            if ($lockedExpense->isPosted()) {
                throw new \LogicException(
                    sprintf('Duplicate Posting Prevented: Expense Voucher [%s] has already been posted to ledger at [%s].', $lockedExpense->voucher_number, $lockedExpense->posted_at)
                );
            }

            if ($lockedExpense->status !== TripExpense::STATUS_APPROVED) {
                throw new \InvalidArgumentException(
                    sprintf('Expense Voucher [%s] cannot be posted because its status is [%s]. Only approved vouchers can be posted.', $lockedExpense->voucher_number, $lockedExpense->status)
                );
            }

            $lockedExpense->status = TripExpense::STATUS_POSTED;
            $lockedExpense->posted_by = $postedById ?? auth()->id() ?? 1;
            $lockedExpense->posted_at = now();
            $lockedExpense->save();

            $this->auditLog->log('expense_posted', $lockedExpense, null, [
                'posted_at' => $lockedExpense->posted_at->toIso8601String(),
                'posted_by' => $lockedExpense->posted_by,
                'amount' => $lockedExpense->amount,
            ], 'Expense voucher posted to financial ledger.');

            return $lockedExpense;
        });
    }

    /**
     * Get categorized expense and fuel summary for a trip.
     */
    public function getTripExpenseSummary(Trip $trip): array
    {
        $expenses = TripExpense::where('trip_id', $trip->id)->get();
        $fuelEntries = $trip->fuelEntries()->get();

        $fuelCost = (float) $fuelEntries->sum('total_amount');
        $fuelLiters = (float) $fuelEntries->sum('quantity_liters');

        $totalOtherExpenses = (float) $expenses->where('status', TripExpense::STATUS_POSTED)->sum('amount');
        if ($totalOtherExpenses == 0) {
            // If none posted yet, show approved/all
            $totalOtherExpenses = (float) $expenses->whereIn('status', [TripExpense::STATUS_APPROVED, TripExpense::STATUS_POSTED])->sum('amount');
        }

        $byCategory = [];
        foreach ($expenses as $exp) {
            $cat = $exp->expense_category;
            $byCategory[$cat] = ($byCategory[$cat] ?? 0.00) + (float) $exp->amount;
        }

        return [
            'total_fuel_cost' => $fuelCost,
            'total_fuel_liters' => $fuelLiters,
            'total_other_expenses' => $totalOtherExpenses,
            'grand_total_trip_cost' => $fuelCost + $totalOtherExpenses,
            'expenses_by_category' => $byCategory,
            'posted_count' => $expenses->where('status', TripExpense::STATUS_POSTED)->count(),
            'pending_approval_count' => $expenses->where('status', TripExpense::STATUS_SUBMITTED)->count(),
        ];
    }
}

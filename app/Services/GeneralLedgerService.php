<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\DocumentSequence;
use App\Models\FinancialYear;
use App\Models\JournalVoucher;
use App\Models\JournalVoucherItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GeneralLedgerService
{
    public function __construct(
        protected FinancialPeriodService $financialPeriodService,
        protected DocumentSequenceService $sequenceService,
        protected AuditLogService $auditLog,
        protected TenantContextService $tenantContext
    ) {}

    /**
     * Post a balanced journal voucher into the General Ledger.
     * Enforces strict double-entry balance, financial period validation,
     * atomic account balance updates, and idempotency protection.
     *
     * @param array $data Voucher header and line items
     * @param User|null $user Authenticated acting user
     * @return JournalVoucher
     */
    public function postVoucher(array $data, ?User $user = null): JournalVoucher
    {
        $companyId = $data['company_id'] ?? $this->tenantContext->getCompanyId();

        if (!$companyId) {
            throw new InvalidArgumentException('Company context is required to post a voucher.');
        }

        $voucherDate = $data['voucher_date'] ?? now()->toDateString();

        // 1. Validate transaction date against open financial year and period
        $this->financialPeriodService->validateTransactionDate($companyId, $voucherDate);

        // 2. Check Idempotency Key to prevent duplicate postings
        if (!empty($data['idempotency_key'])) {
            $existing = JournalVoucher::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        // 3. Resolve Financial Year and Accounting Period
        $financialYear = FinancialYear::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereDate('start_date', '<=', $voucherDate)
            ->whereDate('end_date', '>=', $voucherDate)
            ->first();

        $accountingPeriod = null;
        if ($financialYear) {
            $accountingPeriod = AccountingPeriod::withoutGlobalScopes()
                ->where('financial_year_id', $financialYear->id)
                ->whereDate('start_date', '<=', $voucherDate)
                ->whereDate('end_date', '>=', $voucherDate)
                ->first();
        }

        // 4. Validate double-entry balance
        $items = $data['items'] ?? [];
        if (empty($items)) {
            throw new InvalidArgumentException('Voucher must contain at least two entries.');
        }

        $totalDebit = 0.00;
        $totalCredit = 0.00;

        foreach ($items as $item) {
            $debit = round((float) ($item['debit'] ?? 0), 2);
            $credit = round((float) ($item['credit'] ?? 0), 2);

            if ($debit < 0 || $credit < 0) {
                throw new InvalidArgumentException('Negative amounts are not permitted in voucher line entries.');
            }

            if ($debit > 0 && $credit > 0) {
                throw new InvalidArgumentException('A single line entry cannot contain both debit and credit amounts.');
            }

            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        $totalDebit = round($totalDebit, 2);
        $totalCredit = round($totalCredit, 2);

        if (abs($totalDebit - $totalCredit) >= 0.01 || $totalDebit <= 0) {
            throw new InvalidArgumentException(
                sprintf('Voucher is not balanced! Total Debits [%0.2f] must equal Total Credits [%0.2f] and be greater than 0.00.', $totalDebit, $totalCredit)
            );
        }

        // 5. Atomic database transaction
        return DB::transaction(function () use ($companyId, $data, $voucherDate, $financialYear, $accountingPeriod, $items, $totalDebit, $totalCredit, $user) {
            $voucherType = $data['voucher_type'] ?? JournalVoucher::TYPE_JOURNAL;

            // Generate voucher number if not supplied
            $voucherNumber = $data['voucher_number'] ?? null;
            if (!$voucherNumber) {
                $docType = match ($voucherType) {
                    JournalVoucher::TYPE_CASH_RECEIPT, JournalVoucher::TYPE_BANK_RECEIPT => DocumentSequence::TYPE_RECEIPT,
                    JournalVoucher::TYPE_CASH_PAYMENT, JournalVoucher::TYPE_BANK_PAYMENT => DocumentSequence::TYPE_PAYMENT,
                    JournalVoucher::TYPE_CONTRA => DocumentSequence::TYPE_CONTRA_VOUCHER,
                    default => DocumentSequence::TYPE_JOURNAL_VOUCHER,
                };

                $prefix = match ($voucherType) {
                    JournalVoucher::TYPE_CASH_RECEIPT => 'CR',
                    JournalVoucher::TYPE_CASH_PAYMENT => 'CP',
                    JournalVoucher::TYPE_BANK_RECEIPT => 'BR',
                    JournalVoucher::TYPE_BANK_PAYMENT => 'BP',
                    JournalVoucher::TYPE_CONTRA => 'CV',
                    JournalVoucher::TYPE_ADJUSTMENT => 'ADJ',
                    JournalVoucher::TYPE_OPENING => 'OB',
                    default => 'JV',
                };

                $voucherNumber = $this->sequenceService->getNextSequenceNumber(
                    documentType: $docType,
                    prefix: $prefix,
                    explicitCompanyId: $companyId,
                    explicitBranchId: $data['branch_id'] ?? null
                );
            }

            /** @var JournalVoucher $voucher */
            $voucher = JournalVoucher::create([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'] ?? null,
                'financial_year_id' => $financialYear?->id,
                'accounting_period_id' => $accountingPeriod?->id,
                'voucher_number' => $voucherNumber,
                'voucher_date' => $voucherDate,
                'voucher_type' => $voucherType,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'status' => JournalVoucher::STATUS_POSTED,
                'source_module' => $data['source_module'] ?? 'general_ledger',
                'source_id' => $data['source_id'] ?? null,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'trip_id' => $data['trip_id'] ?? null,
                'vehicle_id' => $data['vehicle_id'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'narration' => $data['narration'] ?? $data['description'] ?? 'General Journal Entry',
                'created_by' => $user?->id ?? $data['created_by'] ?? null,
                'posted_by' => $user?->id ?? $data['posted_by'] ?? null,
                'posted_at' => now(),
            ]);

            // Create items and atomically update Account current_balances
            foreach ($items as $itemData) {
                $accountId = $itemData['chart_of_account_id'] ?? null;

                // Resolve account if given by code
                if (!$accountId && !empty($itemData['account_code'])) {
                    $cleanCode = strtok($itemData['account_code'], '-');
                    $account = ChartOfAccount::withoutGlobalScopes()
                        ->where('company_id', $companyId)
                        ->where('account_code', $cleanCode)
                        ->first();

                    if ($account) {
                        $accountId = $account->id;
                    }
                }

                if (!$accountId) {
                    throw new InvalidArgumentException('Each line item must have a valid chart_of_account_id or registered account_code.');
                }

                /** @var ChartOfAccount $account */
                $account = ChartOfAccount::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('id', $accountId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $debit = round((float) ($itemData['debit'] ?? 0), 2);
                $credit = round((float) ($itemData['credit'] ?? 0), 2);
                $entryType = $debit > 0 ? 'debit' : 'credit';

                JournalVoucherItem::create([
                    'company_id' => $companyId,
                    'journal_voucher_id' => $voucher->id,
                    'chart_of_account_id' => $account->id,
                    'entry_type' => $entryType,
                    'debit' => $debit,
                    'credit' => $credit,
                    'description' => $itemData['description'] ?? $voucher->narration,
                    'customer_id' => $itemData['customer_id'] ?? null,
                    'vehicle_owner_id' => $itemData['vehicle_owner_id'] ?? null,
                    'driver_id' => $itemData['driver_id'] ?? null,
                    'fuel_supplier_id' => $itemData['fuel_supplier_id'] ?? null,
                    'vehicle_id' => $itemData['vehicle_id'] ?? $voucher->vehicle_id,
                    'trip_id' => $itemData['trip_id'] ?? $voucher->trip_id,
                ]);

                // Update ChartOfAccount current balance according to normal balance nature
                if ($account->isDebitNature()) {
                    // Normal debit balance (Assets, Expenses): Debit increases, Credit decreases
                    $account->current_balance = round((float) $account->current_balance + $debit - $credit, 2);
                } else {
                    // Normal credit balance (Liabilities, Equity, Revenue): Credit increases, Debit decreases
                    $account->current_balance = round((float) $account->current_balance + $credit - $debit, 2);
                }

                $account->save();
            }

            $this->auditLog->log(
                action: 'voucher_posted',
                model: $voucher,
                oldValues: [],
                newValues: [
                    'voucher_number' => $voucher->voucher_number,
                    'voucher_type' => $voucher->voucher_type,
                    'total_debit' => $voucher->total_debit,
                    'total_credit' => $voucher->total_credit,
                ],
                description: "Voucher [{$voucher->voucher_number}] posted"
            );

            return $voucher->load('items.account');
        });
    }

    /**
     * Reverse a posted journal voucher with an offsetting reversal voucher.
     * Enforces audit immutability — original voucher is retained and marked reversed.
     */
    public function reverseVoucher(
        JournalVoucher $voucher,
        string $reason,
        ?User $user = null,
        ?string $reversalDate = null
    ): JournalVoucher {
        if (!$voucher->isPosted()) {
            throw new InvalidArgumentException("Only posted vouchers can be reversed. Current status: [{$voucher->status}].");
        }

        if ($voucher->isReversed()) {
            throw new InvalidArgumentException("Voucher [{$voucher->voucher_number}] has already been reversed.");
        }

        $reversalDate = $reversalDate ?? now()->toDateString();
        $this->financialPeriodService->validateTransactionDate($voucher->company_id, $reversalDate);

        return DB::transaction(function () use ($voucher, $reason, $user, $reversalDate) {
            // Build offsetting entries (swap debits and credits)
            $reversingItems = [];
            foreach ($voucher->items as $item) {
                $reversingItems[] = [
                    'chart_of_account_id' => $item->chart_of_account_id,
                    'debit' => (float) $item->credit,
                    'credit' => (float) $item->debit,
                    'description' => "Reversal of {$voucher->voucher_number}: " . ($item->description ?? ''),
                    'customer_id' => $item->customer_id,
                    'vehicle_owner_id' => $item->vehicle_owner_id,
                    'driver_id' => $item->driver_id,
                    'fuel_supplier_id' => $item->fuel_supplier_id,
                    'vehicle_id' => $item->vehicle_id,
                    'trip_id' => $item->trip_id,
                ];
            }

            // Post reversing journal voucher
            $reversingVoucher = $this->postVoucher([
                'company_id' => $voucher->company_id,
                'branch_id' => $voucher->branch_id,
                'voucher_date' => $reversalDate,
                'voucher_type' => JournalVoucher::TYPE_ADJUSTMENT,
                'source_module' => 'voucher_reversal',
                'source_id' => $voucher->id,
                'reference_number' => "REV-{$voucher->voucher_number}",
                'narration' => "Reversal of [{$voucher->voucher_number}]: {$reason}",
                'trip_id' => $voucher->trip_id,
                'vehicle_id' => $voucher->vehicle_id,
                'items' => $reversingItems,
            ], $user);

            // Mark original voucher as reversed
            $voucher->update([
                'status' => JournalVoucher::STATUS_REVERSED,
                'reversed_by' => $user?->id,
                'reversed_at' => now(),
                'reversal_reason' => $reason,
            ]);

            $this->auditLog->log(
                action: 'voucher_reversed',
                model: $voucher,
                oldValues: ['status' => JournalVoucher::STATUS_POSTED],
                newValues: [
                    'status' => JournalVoucher::STATUS_REVERSED,
                    'reversing_voucher_id' => $reversingVoucher->id,
                    'reversal_reason' => $reason,
                ],
                description: "Reversal of voucher [{$voucher->voucher_number}]: {$reason}"
            );

            return $reversingVoucher;
        });
    }

    /**
     * Retrieve a voucher by ID with tenant isolation.
     */
    public function getVoucher(int $voucherId, ?int $companyId = null): ?JournalVoucher
    {
        $resolvedCompanyId = $companyId ?? $this->tenantContext->getCompanyId();
        
        $query = JournalVoucher::with(['items.account', 'branch', 'financialYear', 'accountingPeriod']);
        if ($resolvedCompanyId) {
            $query->where('company_id', $resolvedCompanyId);
        }

        return $query->find($voucherId);
    }
}

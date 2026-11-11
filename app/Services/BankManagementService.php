<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\ChartOfAccount;
use App\Models\DocumentSequence;
use App\Models\JournalVoucher;
use App\Models\JournalVoucherItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BankManagementService
{
    public function __construct(
        protected GeneralLedgerService $generalLedger,
        protected DocumentSequenceService $sequenceService,
        protected ChartOfAccountsService $coaService,
        protected AuditLogService $auditLog,
        protected TenantContextService $tenantContext
    ) {}

    /**
     * Create a new Bank or Cash Account for a tenant company.
     */
    public function createBankAccount(array $data, ?User $user = null): BankAccount
    {
        $companyId = $data['company_id'] ?? $this->tenantContext->getCompanyId();
        if (!$companyId) {
            throw new InvalidArgumentException('Company context is required.');
        }

        return DB::transaction(function () use ($companyId, $data, $user) {
            // Find or associate with ChartOfAccount
            $coaId = $data['chart_of_account_id'] ?? null;
            if (!$coaId) {
                $code = ($data['account_type'] ?? 'bank') === 'bank' ? ChartOfAccount::CODE_BANK_ACCOUNTS : ChartOfAccount::CODE_CASH_IN_HAND;
                $coa = $this->coaService->getAccountByCode($companyId, $code);
                if (!$coa) {
                    $this->coaService->seedDefaultChartOfAccounts(\App\Models\Company::findOrFail($companyId));
                    $coa = $this->coaService->getAccountByCode($companyId, $code);
                }
                $coaId = $coa?->id;
            }

            $opening = round((float) ($data['opening_balance'] ?? 0), 2);

            /** @var BankAccount $bankAccount */
            $bankAccount = BankAccount::create([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'] ?? null,
                'chart_of_account_id' => $coaId,
                'account_name' => $data['account_name'],
                'account_type' => $data['account_type'] ?? BankAccount::TYPE_BANK,
                'bank_name' => $data['bank_name'] ?? null,
                'branch_name' => $data['branch_name'] ?? null,
                'account_number' => $data['account_number'] ?? null,
                'iban' => $data['iban'] ?? null,
                'currency' => $data['currency'] ?? 'PKR',
                'opening_balance' => $opening,
                'current_balance' => $opening,
                'is_active' => $data['is_active'] ?? true,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->auditLog->log(
                action: 'bank_account_created',
                model: $bankAccount,
                oldValues: [],
                newValues: [
                    'account_name' => $bankAccount->account_name,
                    'account_type' => $bankAccount->account_type,
                    'current_balance' => $bankAccount->current_balance,
                ],
                description: "Bank account [{$bankAccount->account_name}] created"
            );

            return $bankAccount;
        });
    }

    /**
     * Record an inter-account bank/cash transfer via Contra Voucher.
     * Posts Contra Voucher (CV):
     * Dr. Destination Account COA (Receiving account increases)
     * Cr. Source Account COA (Sending account decreases)
     */
    public function recordTransfer(
        BankAccount $fromAccount,
        BankAccount $toAccount,
        float $amount,
        string $transferDate,
        ?string $reference = null,
        ?User $user = null
    ): JournalVoucher {
        if ($fromAccount->company_id !== $toAccount->company_id) {
            throw new InvalidArgumentException('Inter-tenant funds transfer is strictly prohibited.');
        }

        if ($fromAccount->id === $toAccount->id) {
            throw new InvalidArgumentException('Source and destination accounts cannot be identical.');
        }

        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Transfer amount must be greater than 0.');
        }

        $companyId = $fromAccount->company_id;

        return DB::transaction(function () use ($fromAccount, $toAccount, $amount, $transferDate, $reference, $companyId, $user) {
            $fromCoaId = $fromAccount->chart_of_account_id;
            $toCoaId = $toAccount->chart_of_account_id;

            if (!$fromCoaId || !$toCoaId) {
                $bankCoa = $this->coaService->getAccountByCode($companyId, ChartOfAccount::CODE_BANK_ACCOUNTS);
                $cashCoa = $this->coaService->getAccountByCode($companyId, ChartOfAccount::CODE_CASH_IN_HAND);
                $fromCoaId = $fromCoaId ?? ($fromAccount->account_type === 'cash_in_hand' ? $cashCoa?->id : $bankCoa?->id);
                $toCoaId = $toCoaId ?? ($toAccount->account_type === 'cash_in_hand' ? $cashCoa?->id : $bankCoa?->id);
            }

            // Post Contra Voucher (CV)
            $voucher = $this->generalLedger->postVoucher([
                'company_id' => $companyId,
                'branch_id' => $fromAccount->branch_id,
                'voucher_date' => $transferDate,
                'voucher_type' => JournalVoucher::TYPE_CONTRA,
                'source_module' => 'bank_transfer',
                'reference_number' => $reference ?? "TRF-{$fromAccount->id}-{$toAccount->id}",
                'narration' => "Funds transfer from [{$fromAccount->account_name}] to [{$toAccount->account_name}]",
                'items' => [
                    [
                        'chart_of_account_id' => $toCoaId,
                        'debit' => $amount,
                        'credit' => 0.00,
                        'description' => "Deposit from {$fromAccount->account_name}",
                    ],
                    [
                        'chart_of_account_id' => $fromCoaId,
                        'debit' => 0.00,
                        'credit' => $amount,
                        'description' => "Withdrawal to {$toAccount->account_name}",
                    ],
                ],
            ], $user);

            // Update BankAccount current balances atomically
            $fromAccount->decrement('current_balance', $amount);
            $toAccount->increment('current_balance', $amount);

            $this->auditLog->log(
                action: 'bank_transfer_completed',
                model: $fromAccount,
                oldValues: ['current_balance' => $fromAccount->current_balance + $amount],
                newValues: [
                    'to_account_id' => $toAccount->id,
                    'amount' => $amount,
                    'voucher_id' => $voucher->id,
                ],
                description: "Inter-account transfer of PKR {$amount} to [{$toAccount->account_name}]"
            );

            return $voucher;
        });
    }

    /**
     * Conduct and finalize a Bank Reconciliation for an account period.
     */
    public function reconcileBankStatement(
        BankAccount $bankAccount,
        array $data,
        ?User $user = null
    ): BankReconciliation {
        $companyId = $bankAccount->company_id;
        $statementDate = $data['statement_date'] ?? now()->toDateString();
        $statementEndingBalance = round((float) ($data['statement_ending_balance'] ?? 0), 2);
        $bookBalance = round((float) $bankAccount->current_balance, 2);

        $clearedItemIds = $data['cleared_item_ids'] ?? [];

        return DB::transaction(function () use ($companyId, $bankAccount, $statementDate, $statementEndingBalance, $bookBalance, $clearedItemIds, $data, $user) {
            // Mark items as cleared
            if (!empty($clearedItemIds)) {
                JournalVoucherItem::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->whereIn('id', $clearedItemIds)
                    ->update([
                        'reconciled' => true,
                        'reconciled_at' => now(),
                    ]);
            }

            // Calculate reconciled uncleared items
            $unclearedDeposits = round((float) ($data['uncleared_deposits'] ?? 0), 2);
            $unclearedCheques = round((float) ($data['uncleared_cheques'] ?? 0), 2);
            $reconciledBookBalance = round($statementEndingBalance + $unclearedDeposits - $unclearedCheques, 2);
            $diff = round($reconciledBookBalance - $bookBalance, 2);

            /** @var BankReconciliation $recon */
            $recon = BankReconciliation::create([
                'company_id' => $companyId,
                'bank_account_id' => $bankAccount->id,
                'statement_date' => $statementDate,
                'statement_balance' => $statementEndingBalance,
                'book_balance' => $bookBalance,
                'reconciled_balance' => $reconciledBookBalance,
                'difference' => $diff,
                'status' => abs($diff) < 0.01 ? BankReconciliation::STATUS_RECONCILED : BankReconciliation::STATUS_DRAFT,
                'reconciled_by' => $user?->id,
                'reconciled_at' => abs($diff) < 0.01 ? now() : null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->auditLog->log(
                action: 'bank_reconciliation_performed',
                model: $recon,
                oldValues: [],
                newValues: [
                    'bank_account_id' => $bankAccount->id,
                    'statement_ending_balance' => $statementEndingBalance,
                    'book_balance' => $bookBalance,
                    'difference' => $diff,
                    'status' => $recon->status,
                ],
                description: "Bank reconciliation for [{$bankAccount->account_name}]"
            );

            return $recon;
        });
    }
}

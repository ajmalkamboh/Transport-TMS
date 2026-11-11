<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\DocumentSequence;
use App\Models\Driver;
use App\Models\DriverSettlement;
use App\Models\JournalVoucher;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DriverAccountService
{
    public function __construct(
        protected GeneralLedgerService $generalLedger,
        protected DocumentSequenceService $sequenceService,
        protected ChartOfAccountsService $coaService,
        protected AuditLogService $auditLog,
        protected TenantContextService $tenantContext
    ) {}

    /**
     * Prepare a draft Driver Voyage Settlement for a trip.
     * Reconciles advances disbursed vs agreed trip allowance and out-of-pocket expenses.
     */
    public function createDriverSettlement(array $data, ?User $user = null): DriverSettlement
    {
        $companyId = $data['company_id'] ?? $this->tenantContext->getCompanyId();
        if (!$companyId) {
            throw new InvalidArgumentException('Company context is required to create a driver settlement.');
        }

        $driverId = $data['driver_id'];
        $tripId = $data['trip_id'] ?? null;

        return DB::transaction(function () use ($companyId, $data, $driverId, $tripId, $user) {
            $settlementNumber = $data['settlement_number'] ?? $this->sequenceService->getNextSequenceNumber(
                documentType: DocumentSequence::TYPE_DRIVER_SETTLEMENT,
                prefix: 'DS',
                explicitCompanyId: $companyId,
                explicitBranchId: $data['branch_id'] ?? null
            );

            $advances = round((float) ($data['total_advances'] ?? 0), 2);
            $allowance = round((float) ($data['driver_allowance'] ?? 0), 2);
            $outOfPocket = round((float) ($data['out_of_pocket_expenses'] ?? 0), 2);

            /** @var DriverSettlement $settlement */
            $settlement = DriverSettlement::create([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'] ?? null,
                'driver_id' => $driverId,
                'trip_id' => $tripId,
                'settlement_number' => $settlementNumber,
                'settlement_date' => $data['settlement_date'] ?? now()->toDateString(),
                'total_advances' => $advances,
                'driver_allowance' => $allowance,
                'out_of_pocket_expenses' => $outOfPocket,
                'status' => DriverSettlement::STATUS_DRAFT,
                'notes' => $data['notes'] ?? null,
            ]);

            $settlement->recalculateTotals();

            $this->auditLog->log(
                action: 'driver_settlement_created',
                model: $settlement,
                oldValues: [],
                newValues: [
                    'settlement_number' => $settlement->settlement_number,
                    'driver_id' => $settlement->driver_id,
                    'net_payable' => $settlement->net_payable,
                    'net_recoverable' => $settlement->net_recoverable,
                ],
                description: "Driver settlement [{$settlement->settlement_number}] created"
            );

            return $settlement->load(['driver', 'trip']);
        });
    }

    /**
     * Post Driver Settlement to General Ledger.
     * Prevents double-counting:
     * Dr. 5210 Driver Allowances (Allowance)
     * Dr. 5200 Trip Operational Expenses (Out-of-pocket expenses)
     * Cr. 1150 Driver Advance Clearing (Disbursed advances)
     * Cr. 2300 Salaries / Allowances Payable (or 1010 Cash if net payable)
     * Or Dr. 1010 Cash / Advance balance (if net recoverable)
     */
    public function postDriverSettlement(DriverSettlement $settlement, ?User $user = null): DriverSettlement
    {
        if (in_array($settlement->status, [DriverSettlement::STATUS_POSTED, DriverSettlement::STATUS_SETTLED])) {
            throw new InvalidArgumentException("Driver settlement [{$settlement->settlement_number}] is already posted.");
        }

        return DB::transaction(function () use ($settlement, $user) {
            $companyId = $settlement->company_id;

            $allowanceAcc = $this->coaService->getAccountByCode($companyId, ChartOfAccount::CODE_DRIVER_ALLOWANCES);
            $expenseAcc = $this->coaService->getAccountByCode($companyId, ChartOfAccount::CODE_TRIP_EXPENSES);
            $advanceAcc = $this->coaService->getAccountByCode($companyId, ChartOfAccount::CODE_DRIVER_ADVANCE_CLEARING);
            $payableAcc = $this->coaService->getAccountByCode($companyId, ChartOfAccount::CODE_SALARIES_PAYABLE);
            $cashAcc = $this->coaService->getAccountByCode($companyId, ChartOfAccount::CODE_CASH_IN_HAND);

            if (!$allowanceAcc || !$expenseAcc || !$advanceAcc || !$payableAcc) {
                $this->coaService->seedDefaultChartOfAccounts($settlement->company);
                $allowanceAcc = $this->coaService->getAccountByCode($companyId, ChartOfAccount::CODE_DRIVER_ALLOWANCES);
                $expenseAcc = $this->coaService->getAccountByCode($companyId, ChartOfAccount::CODE_TRIP_EXPENSES);
                $advanceAcc = $this->coaService->getAccountByCode($companyId, ChartOfAccount::CODE_DRIVER_ADVANCE_CLEARING);
                $payableAcc = $this->coaService->getAccountByCode($companyId, ChartOfAccount::CODE_SALARIES_PAYABLE);
                $cashAcc = $this->coaService->getAccountByCode($companyId, ChartOfAccount::CODE_CASH_IN_HAND);
            }

            $voucherItems = [];

            // 1. Dr. Driver Allowance Expense
            if ((float) $settlement->driver_allowance > 0) {
                $voucherItems[] = [
                    'chart_of_account_id' => $allowanceAcc->id,
                    'debit' => (float) $settlement->driver_allowance,
                    'credit' => 0.00,
                    'description' => "Trip Allowance - Settlement {$settlement->settlement_number}",
                    'driver_id' => $settlement->driver_id,
                    'trip_id' => $settlement->trip_id,
                ];
            }

            // 2. Dr. Trip Out-of-pocket Expenses
            if ((float) $settlement->out_of_pocket_expenses > 0) {
                $voucherItems[] = [
                    'chart_of_account_id' => $expenseAcc->id,
                    'debit' => (float) $settlement->out_of_pocket_expenses,
                    'credit' => 0.00,
                    'description' => "Driver Out-of-pocket Reimbursements - {$settlement->settlement_number}",
                    'driver_id' => $settlement->driver_id,
                    'trip_id' => $settlement->trip_id,
                ];
            }

            // 3. Cr. Driver Advance Clearing
            if ((float) $settlement->total_advances > 0) {
                $voucherItems[] = [
                    'chart_of_account_id' => $advanceAcc->id,
                    'debit' => 0.00,
                    'credit' => (float) $settlement->total_advances,
                    'description' => "Clearance of Voyage Advances - {$settlement->settlement_number}",
                    'driver_id' => $settlement->driver_id,
                    'trip_id' => $settlement->trip_id,
                ];
            }

            // 4. Balance settlement line (Net Payable vs Net Recoverable)
            if ((float) $settlement->net_payable > 0) {
                // Company owes driver -> Credit Salaries/Allowances Payable
                $voucherItems[] = [
                    'chart_of_account_id' => $payableAcc->id,
                    'debit' => 0.00,
                    'credit' => (float) $settlement->net_payable,
                    'description' => "Net Payable to Driver - Settlement {$settlement->settlement_number}",
                    'driver_id' => $settlement->driver_id,
                    'trip_id' => $settlement->trip_id,
                ];
            } elseif ((float) $settlement->net_recoverable > 0) {
                // Driver owes company back unspent advances -> Dr. Cash / Driver Clearing
                $voucherItems[] = [
                    'chart_of_account_id' => $cashAcc ? $cashAcc->id : $advanceAcc->id,
                    'debit' => (float) $settlement->net_recoverable,
                    'credit' => 0.00,
                    'description' => "Unspent Advance Recovery from Driver - {$settlement->settlement_number}",
                    'driver_id' => $settlement->driver_id,
                    'trip_id' => $settlement->trip_id,
                ];
            }

            // Post Voucher
            $voucher = $this->generalLedger->postVoucher([
                'company_id' => $companyId,
                'branch_id' => $settlement->branch_id,
                'voucher_date' => $settlement->settlement_date->toDateString(),
                'voucher_type' => JournalVoucher::TYPE_JOURNAL,
                'source_module' => 'driver_settlement',
                'source_id' => $settlement->id,
                'idempotency_key' => "DRV-SETTLE-{$settlement->id}-{$settlement->settlement_number}",
                'reference_number' => $settlement->settlement_number,
                'narration' => "Driver Voyage Settlement {$settlement->settlement_number} - Driver: {$settlement->driver?->name}",
                'trip_id' => $settlement->trip_id,
                'items' => $voucherItems,
            ], $user);

            $settlement->update([
                'status' => DriverSettlement::STATUS_POSTED,
                'journal_voucher_id' => $voucher->id,
                'settled_by' => $user?->id,
                'settled_at' => now(),
            ]);

            return $settlement->fresh(['voucher', 'driver', 'trip']);
        });
    }
}

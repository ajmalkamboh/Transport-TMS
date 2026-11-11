<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\CustomerPayment;
use App\Models\DriverSettlement;
use App\Models\FuelEntry;
use App\Models\JournalVoucher;
use App\Models\JournalVoucherItem;
use App\Models\OwnerSettlement;
use App\Models\Trip;
use App\Models\TripExpense;
use Illuminate\Support\Facades\DB;

class FinancialReportService
{
    /**
     * Generate Trial Balance.
     * Enforces double-entry equality: Total Debits must equal Total Credits.
     */
    public function getTrialBalance(int $companyId, ?string $asOfDate = null, ?int $branchId = null): array
    {
        $asOfDate = $asOfDate ?? now()->toDateString();

        $accounts = ChartOfAccount::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get();

        $rows = [];
        $totalDebits = 0.00;
        $totalCredits = 0.00;

        foreach ($accounts as $account) {
            $query = JournalVoucherItem::withoutGlobalScopes()
                ->join('journal_vouchers', 'journal_voucher_items.journal_voucher_id', '=', 'journal_vouchers.id')
                ->where('journal_voucher_items.company_id', $companyId)
                ->where('journal_voucher_items.chart_of_account_id', $account->id)
                ->where('journal_vouchers.status', JournalVoucher::STATUS_POSTED)
                ->whereDate('journal_vouchers.voucher_date', '<=', $asOfDate);

            if ($branchId) {
                $query->where('journal_vouchers.branch_id', $branchId);
            }

            $totals = $query->select(
                DB::raw('COALESCE(SUM(journal_voucher_items.debit), 0) as total_debit'),
                DB::raw('COALESCE(SUM(journal_voucher_items.credit), 0) as total_credit')
            )->first();

            $debitMovement = (float) $totals->total_debit;
            $creditMovement = (float) $totals->total_credit;

            // Include opening balance
            $openingBalance = (float) $account->opening_balance;
            if ($account->isDebitNature()) {
                $debitMovement += $openingBalance;
            } else {
                $creditMovement += $openingBalance;
            }

            $debitBalance = 0.00;
            $creditBalance = 0.00;

            if ($account->isDebitNature()) {
                $net = $debitMovement - $creditMovement;
                if ($net >= 0) {
                    $debitBalance = round($net, 2);
                } else {
                    $creditBalance = round(abs($net), 2);
                }
            } else {
                $net = $creditMovement - $debitMovement;
                if ($net >= 0) {
                    $creditBalance = round($net, 2);
                } else {
                    $debitBalance = round(abs($net), 2);
                }
            }

            if ($debitBalance > 0 || $creditBalance > 0 || $openingBalance > 0) {
                $rows[] = [
                    'account_id' => $account->id,
                    'account_code' => $account->account_code,
                    'account_name' => $account->account_name,
                    'account_type' => $account->account_type,
                    'account_group' => $account->account_group,
                    'normal_balance' => $account->normal_balance,
                    'debit' => $debitBalance,
                    'credit' => $creditBalance,
                ];

                $totalDebits += $debitBalance;
                $totalCredits += $creditBalance;
            }
        }

        $totalDebits = round($totalDebits, 2);
        $totalCredits = round($totalCredits, 2);
        $diff = round(abs($totalDebits - $totalCredits), 2);

        return [
            'as_of_date' => $asOfDate,
            'accounts' => $rows,
            'total_debits' => $totalDebits,
            'total_credits' => $totalCredits,
            'difference' => $diff,
            'is_balanced' => $diff < 0.01,
        ];
    }

    /**
     * Generate Profit and Loss (Income Statement).
     * Categorizes Operating Revenue, Direct Operational (Voyage) Expenses, Gross Margin,
     * Terminal Administrative Overheads, and Net Operating Profit.
     */
    public function getProfitAndLoss(int $companyId, string $fromDate, string $toDate, ?int $branchId = null): array
    {
        $accounts = ChartOfAccount::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('account_type', [ChartOfAccount::TYPE_REVENUE, ChartOfAccount::TYPE_EXPENSE])
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get();

        $revenues = [];
        $directCosts = [];
        $adminExpenses = [];

        $totalRevenue = 0.00;
        $totalDirectCosts = 0.00;
        $totalAdminExpenses = 0.00;

        foreach ($accounts as $account) {
            $query = JournalVoucherItem::withoutGlobalScopes()
                ->join('journal_vouchers', 'journal_voucher_items.journal_voucher_id', '=', 'journal_vouchers.id')
                ->where('journal_voucher_items.company_id', $companyId)
                ->where('journal_voucher_items.chart_of_account_id', $account->id)
                ->where('journal_vouchers.status', JournalVoucher::STATUS_POSTED)
                ->whereDate('journal_vouchers.voucher_date', '>=', $fromDate)
                ->whereDate('journal_vouchers.voucher_date', '<=', $toDate);

            if ($branchId) {
                $query->where('journal_vouchers.branch_id', $branchId);
            }

            $totals = $query->select(
                DB::raw('COALESCE(SUM(journal_voucher_items.debit), 0) as total_debit'),
                DB::raw('COALESCE(SUM(journal_voucher_items.credit), 0) as total_credit')
            )->first();

            $debit = (float) $totals->total_debit;
            $credit = (float) $totals->total_credit;

            if ($account->account_type === ChartOfAccount::TYPE_REVENUE) {
                $netRevenue = round($credit - $debit, 2);
                if (abs($netRevenue) > 0.001) {
                    $revenues[] = [
                        'account_code' => $account->account_code,
                        'account_name' => $account->account_name,
                        'amount' => $netRevenue,
                    ];
                    $totalRevenue += $netRevenue;
                }
            } else {
                $netExpense = round($debit - $credit, 2);
                if (abs($netExpense) > 0.001) {
                    $item = [
                        'account_code' => $account->account_code,
                        'account_name' => $account->account_name,
                        'amount' => $netExpense,
                    ];

                    if ($account->account_group === 'direct_operating_cost' || str_starts_with($account->account_code, '5')) {
                        $directCosts[] = $item;
                        $totalDirectCosts += $netExpense;
                    } else {
                        $adminExpenses[] = $item;
                        $totalAdminExpenses += $netExpense;
                    }
                }
            }
        }

        $totalRevenue = round($totalRevenue, 2);
        $totalDirectCosts = round($totalDirectCosts, 2);
        $grossMargin = round($totalRevenue - $totalDirectCosts, 2);
        $grossMarginPercentage = $totalRevenue > 0 ? round(($grossMargin / $totalRevenue) * 100, 2) : 0.00;

        $totalAdminExpenses = round($totalAdminExpenses, 2);
        $netProfit = round($grossMargin - $totalAdminExpenses, 2);
        $netProfitPercentage = $totalRevenue > 0 ? round(($netProfit / $totalRevenue) * 100, 2) : 0.00;

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'revenues' => $revenues,
            'total_revenue' => $totalRevenue,
            'direct_costs' => $directCosts,
            'total_direct_costs' => $totalDirectCosts,
            'gross_margin' => $grossMargin,
            'gross_margin_percentage' => $grossMarginPercentage,
            'admin_expenses' => $adminExpenses,
            'total_admin_expenses' => $totalAdminExpenses,
            'net_profit' => $netProfit,
            'net_profit_percentage' => $netProfitPercentage,
        ];
    }

    /**
     * Generate Balance Sheet as of a specified date.
     * Enforces fundamental accounting equation: Assets = Liabilities + Equity.
     */
    public function getBalanceSheet(int $companyId, string $asOfDate, ?int $branchId = null): array
    {
        $accounts = ChartOfAccount::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('account_type', [
                ChartOfAccount::TYPE_ASSET,
                ChartOfAccount::TYPE_LIABILITY,
                ChartOfAccount::TYPE_EQUITY,
            ])
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get();

        $currentAssets = [];
        $nonCurrentAssets = [];
        $totalAssets = 0.00;

        $currentLiabilities = [];
        $nonCurrentLiabilities = [];
        $totalLiabilities = 0.00;

        $equityItems = [];
        $totalEquity = 0.00;

        foreach ($accounts as $account) {
            $query = JournalVoucherItem::withoutGlobalScopes()
                ->join('journal_vouchers', 'journal_voucher_items.journal_voucher_id', '=', 'journal_vouchers.id')
                ->where('journal_voucher_items.company_id', $companyId)
                ->where('journal_voucher_items.chart_of_account_id', $account->id)
                ->where('journal_vouchers.status', JournalVoucher::STATUS_POSTED)
                ->whereDate('journal_vouchers.voucher_date', '<=', $asOfDate);

            if ($branchId) {
                $query->where('journal_vouchers.branch_id', $branchId);
            }

            $totals = $query->select(
                DB::raw('COALESCE(SUM(journal_voucher_items.debit), 0) as total_debit'),
                DB::raw('COALESCE(SUM(journal_voucher_items.credit), 0) as total_credit')
            )->first();

            $debit = (float) $totals->total_debit;
            $credit = (float) $totals->total_credit;
            $opening = (float) $account->opening_balance;

            $item = [
                'account_code' => $account->account_code,
                'account_name' => $account->account_name,
                'account_group' => $account->account_group,
            ];

            if ($account->account_type === ChartOfAccount::TYPE_ASSET) {
                // Asset balance = (debit + opening) - credit
                $balance = $account->isDebitNature()
                    ? round(($debit + $opening) - $credit, 2)
                    : round($credit - ($debit + $opening), 2); // contra asset e.g. accumulated depreciation

                $item['amount'] = $balance;
                if ($account->account_group === 'non_current_asset' || $account->account_code === '1500' || $account->account_code === '1590') {
                    $nonCurrentAssets[] = $item;
                } else {
                    $currentAssets[] = $item;
                }
                $totalAssets += ($account->isDebitNature() ? $balance : -$balance);
            } elseif ($account->account_type === ChartOfAccount::TYPE_LIABILITY) {
                // Liability balance = (credit + opening) - debit
                $balance = round(($credit + $opening) - $debit, 2);
                $item['amount'] = $balance;
                if ($account->account_group === 'non_current_liability') {
                    $nonCurrentLiabilities[] = $item;
                } else {
                    $currentLiabilities[] = $item;
                }
                $totalLiabilities += $balance;
            } elseif ($account->account_type === ChartOfAccount::TYPE_EQUITY) {
                // Equity balance = (credit + opening) - debit
                $balance = round(($credit + $opening) - $debit, 2);
                $item['amount'] = $balance;
                $equityItems[] = $item;
                $totalEquity += $balance;
            }
        }

        // Calculate Current Period Retained Earnings from Revenue and Expense movements up to asOfDate
        $pnl = $this->getProfitAndLoss($companyId, '2000-01-01', $asOfDate, $branchId);
        $currentPeriodNetProfit = $pnl['net_profit'];

        if (abs($currentPeriodNetProfit) > 0.001) {
            $equityItems[] = [
                'account_code' => 'P&L-CURR',
                'account_name' => 'Current Period Retained Earnings / (Loss)',
                'account_group' => 'equity',
                'amount' => $currentPeriodNetProfit,
            ];
            $totalEquity += $currentPeriodNetProfit;
        }

        $totalAssets = round($totalAssets, 2);
        $totalLiabilities = round($totalLiabilities, 2);
        $totalEquity = round($totalEquity, 2);
        $totalLiabilitiesAndEquity = round($totalLiabilities + $totalEquity, 2);

        $difference = round(abs($totalAssets - $totalLiabilitiesAndEquity), 2);

        return [
            'as_of_date' => $asOfDate,
            'current_assets' => $currentAssets,
            'non_current_assets' => $nonCurrentAssets,
            'total_assets' => $totalAssets,
            'current_liabilities' => $currentLiabilities,
            'non_current_liabilities' => $nonCurrentLiabilities,
            'total_liabilities' => $totalLiabilities,
            'equity' => $equityItems,
            'total_equity' => $totalEquity,
            'total_liabilities_and_equity' => $totalLiabilitiesAndEquity,
            'difference' => $difference,
            'is_balanced' => $difference < 0.01,
        ];
    }

    /**
     * Generate General Ledger for a specific account.
     */
    public function getGeneralLedger(
        int $companyId,
        int $accountId,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?int $branchId = null
    ): array {
        $account = ChartOfAccount::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->findOrFail($accountId);

        $fromDate = $fromDate ?? now()->startOfYear()->toDateString();
        $toDate = $toDate ?? now()->toDateString();

        // 1. Calculate opening balance before $fromDate
        $priorQuery = JournalVoucherItem::withoutGlobalScopes()
            ->join('journal_vouchers', 'journal_voucher_items.journal_voucher_id', '=', 'journal_vouchers.id')
            ->where('journal_voucher_items.company_id', $companyId)
            ->where('journal_voucher_items.chart_of_account_id', $account->id)
            ->where('journal_vouchers.status', JournalVoucher::STATUS_POSTED)
            ->whereDate('journal_vouchers.voucher_date', '<', $fromDate);

        if ($branchId) {
            $priorQuery->where('journal_vouchers.branch_id', $branchId);
        }

        $priorTotals = $priorQuery->select(
            DB::raw('COALESCE(SUM(journal_voucher_items.debit), 0) as prior_debit'),
            DB::raw('COALESCE(SUM(journal_voucher_items.credit), 0) as prior_credit')
        )->first();

        $openingBalance = (float) $account->opening_balance;
        if ($account->isDebitNature()) {
            $openingBalance += ((float) $priorTotals->prior_debit - (float) $priorTotals->prior_credit);
        } else {
            $openingBalance += ((float) $priorTotals->prior_credit - (float) $priorTotals->prior_debit);
        }
        $openingBalance = round($openingBalance, 2);

        // 2. Fetch period transactions
        $itemsQuery = JournalVoucherItem::withoutGlobalScopes()
            ->join('journal_vouchers', 'journal_voucher_items.journal_voucher_id', '=', 'journal_vouchers.id')
            ->where('journal_voucher_items.company_id', $companyId)
            ->where('journal_voucher_items.chart_of_account_id', $account->id)
            ->where('journal_vouchers.status', JournalVoucher::STATUS_POSTED)
            ->whereDate('journal_vouchers.voucher_date', '>=', $fromDate)
            ->whereDate('journal_vouchers.voucher_date', '<=', $toDate)
            ->orderBy('journal_vouchers.voucher_date')
            ->orderBy('journal_vouchers.id')
            ->select('journal_voucher_items.*', 'journal_vouchers.voucher_number', 'journal_vouchers.voucher_date', 'journal_vouchers.voucher_type', 'journal_vouchers.reference_number');

        if ($branchId) {
            $itemsQuery->where('journal_vouchers.branch_id', $branchId);
        }

        $items = $itemsQuery->get();

        $runningBalance = $openingBalance;
        $ledgerEntries = [];
        $totalPeriodDebit = 0.00;
        $totalPeriodCredit = 0.00;

        foreach ($items as $item) {
            $debit = (float) $item->debit;
            $credit = (float) $item->credit;

            $totalPeriodDebit += $debit;
            $totalPeriodCredit += $credit;

            if ($account->isDebitNature()) {
                $runningBalance += ($debit - $credit);
            } else {
                $runningBalance += ($credit - $debit);
            }

            $ledgerEntries[] = [
                'id' => $item->id,
                'date' => $item->voucher_date,
                'voucher_number' => $item->voucher_number,
                'voucher_type' => $item->voucher_type,
                'reference' => $item->reference_number,
                'description' => $item->description,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => round($runningBalance, 2),
            ];
        }

        return [
            'account' => $account,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'opening_balance' => $openingBalance,
            'entries' => $ledgerEntries,
            'total_debit' => round($totalPeriodDebit, 2),
            'total_credit' => round($totalPeriodCredit, 2),
            'closing_balance' => round($runningBalance, 2),
        ];
    }

    /**
     * Generate Customer Ledger / Statement of Account.
     */
    public function getCustomerStatement(
        int $companyId,
        int $customerId,
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {
        $customer = Customer::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->findOrFail($customerId);

        $fromDate = $fromDate ?? now()->startOfYear()->toDateString();
        $toDate = $toDate ?? now()->toDateString();

        // 1. Invoices
        $invoices = CustomerInvoice::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->whereIn('status', [CustomerInvoice::STATUS_POSTED, CustomerInvoice::STATUS_PARTIALLY_PAID, CustomerInvoice::STATUS_PAID])
            ->whereDate('invoice_date', '>=', $fromDate)
            ->whereDate('invoice_date', '<=', $toDate)
            ->orderBy('invoice_date')
            ->get();

        // 2. Payments
        $payments = CustomerPayment::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->where('status', CustomerPayment::STATUS_POSTED)
            ->whereDate('receipt_date', '>=', $fromDate)
            ->whereDate('receipt_date', '<=', $toDate)
            ->orderBy('receipt_date')
            ->get();

        $timeline = [];

        foreach ($invoices as $inv) {
            $timeline[] = [
                'date' => $inv->invoice_date->toDateString(),
                'type' => 'INVOICE',
                'reference' => $inv->invoice_number,
                'description' => "Freight Charges - Invoice {$inv->invoice_number}",
                'debit' => (float) $inv->total_amount,
                'credit' => 0.00,
                'timestamp' => $inv->invoice_date->timestamp,
            ];
        }

        foreach ($payments as $pay) {
            $timeline[] = [
                'date' => $pay->receipt_date->toDateString(),
                'type' => 'RECEIPT',
                'reference' => $pay->receipt_number,
                'description' => "Money Received ({$pay->payment_method}) - {$pay->receipt_number}",
                'debit' => 0.00,
                'credit' => (float) $pay->amount,
                'timestamp' => $pay->receipt_date->timestamp,
            ];
        }

        usort($timeline, fn ($a, $b) => $a['timestamp'] <=> $b['timestamp']);

        $runningBalance = 0.00;
        $totalInvoiced = 0.00;
        $totalReceived = 0.00;

        foreach ($timeline as &$entry) {
            $runningBalance += ($entry['debit'] - $entry['credit']);
            $entry['balance'] = round($runningBalance, 2);
            $totalInvoiced += $entry['debit'];
            $totalReceived += $entry['credit'];
        }

        return [
            'customer' => $customer,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'timeline' => $timeline,
            'total_invoiced' => round($totalInvoiced, 2),
            'total_received' => round($totalReceived, 2),
            'outstanding_balance' => round($runningBalance, 2),
        ];
    }

    /**
     * Compute comprehensive Voyage & Trip Profitability.
     * Evaluates gross freight revenue vs voyage operational expenses (fuel, tolls,
     * allowances, attached truck hire, workshop repairs) and isolates net voyage margin.
     */
    public function getTripProfitability(int $companyId, int $tripId): array
    {
        /** @var Trip $trip */
        $trip = Trip::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->with(['resources.vehicle', 'resources.driver', 'branch'])
            ->findOrFail($tripId);

        // 1. Freight Revenue (from Customer Invoices or Consignments)
        $invoices = CustomerInvoice::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('trip_id', $tripId)
            ->whereIn('status', [CustomerInvoice::STATUS_POSTED, CustomerInvoice::STATUS_PARTIALLY_PAID, CustomerInvoice::STATUS_PAID])
            ->get();

        $freightRevenue = (float) $invoices->sum('total_amount');

        // Fallback to consignments if un-invoiced
        if ($freightRevenue <= 0) {
            $freightRevenue = (float) DB::table('trip_consignments')
                ->join('consignments', 'trip_consignments.consignment_id', '=', 'consignments.id')
                ->where('trip_consignments.trip_id', $tripId)
                ->sum('consignments.total_charges');
        }

        // 2. Direct Fuel Expenses
        $fuelEntries = FuelEntry::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('trip_id', $tripId)
            ->get();

        $fuelCost = (float) $fuelEntries->sum('total_amount');
        $fuelLiters = (float) $fuelEntries->sum('quantity_liters');

        // 3. Trip Operational Expenses & Tolls
        $expenses = TripExpense::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('trip_id', $tripId)
            ->get();

        $tollsAndExpenses = 0.00;
        $driverAllowances = 0.00;

        foreach ($expenses as $exp) {
            if ($exp->expense_category === 'driver_allowance') {
                $driverAllowances += (float) $exp->amount;
            } else {
                $tollsAndExpenses += (float) $exp->amount;
            }
        }

        // 4. Attached Vehicle Owner Freight Cost
        $settlements = OwnerSettlement::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('trip_id', $tripId)
            ->get();

        $ownerFreightCost = (float) $settlements->sum('agreed_owner_payable');

        // 5. Driver Settlement Allowances (if separate)
        $driverSettlements = DriverSettlement::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('trip_id', $tripId)
            ->get();

        if ($driverSettlements->isNotEmpty()) {
            $driverAllowances = (float) $driverSettlements->sum('driver_allowance');
            $tollsAndExpenses += (float) $driverSettlements->sum('out_of_pocket_expenses');
        }

        $totalDirectCosts = round($fuelCost + $tollsAndExpenses + $driverAllowances + $ownerFreightCost, 2);
        $netVoyageMargin = round($freightRevenue - $totalDirectCosts, 2);
        $marginPercentage = $freightRevenue > 0 ? round(($netVoyageMargin / $freightRevenue) * 100, 2) : 0.00;

        return [
            'trip' => $trip,
            'freight_revenue' => round($freightRevenue, 2),
            'fuel_cost' => round($fuelCost, 2),
            'fuel_liters' => round($fuelLiters, 2),
            'tolls_and_expenses' => round($tollsAndExpenses, 2),
            'driver_allowances' => round($driverAllowances, 2),
            'owner_freight_cost' => round($ownerFreightCost, 2),
            'total_direct_costs' => $totalDirectCosts,
            'net_voyage_margin' => $netVoyageMargin,
            'margin_percentage' => $marginPercentage,
            'is_profitable' => $netVoyageMargin >= 0,
        ];
    }
}

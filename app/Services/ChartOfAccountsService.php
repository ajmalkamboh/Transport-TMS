<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\Company;
use Illuminate\Support\Facades\DB;

class ChartOfAccountsService
{
    /**
     * Seed a comprehensive default Chart of Accounts for a transport company tenant.
     */
    public function seedDefaultChartOfAccounts(Company $company): void
    {
        $defaultAccounts = [
            // ASSETS (1000 - 1999)
            [
                'account_code' => ChartOfAccount::CODE_CASH_IN_HAND,
                'account_name' => 'Cash in Hand (Central Operations)',
                'account_type' => ChartOfAccount::TYPE_ASSET,
                'account_group' => 'current_asset',
                'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
                'is_system' => true,
                'is_reconcilable' => false,
                'description' => 'Petty cash and cash on hand across operational terminals',
            ],
            [
                'account_code' => ChartOfAccount::CODE_BANK_ACCOUNTS,
                'account_name' => 'Main Operational Bank Accounts',
                'account_type' => ChartOfAccount::TYPE_ASSET,
                'account_group' => 'current_asset',
                'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
                'is_system' => true,
                'is_reconcilable' => true,
                'description' => 'Commercial bank deposits and checking accounts',
            ],
            [
                'account_code' => ChartOfAccount::CODE_ACCOUNTS_RECEIVABLE,
                'account_name' => 'Accounts Receivable (Customer Freight)',
                'account_type' => ChartOfAccount::TYPE_ASSET,
                'account_group' => 'current_asset',
                'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
                'is_system' => true,
                'is_reconcilable' => false,
                'description' => 'Unpaid freight invoices owed by commercial clients',
            ],
            [
                'account_code' => ChartOfAccount::CODE_DRIVER_ADVANCE_CLEARING,
                'account_name' => 'Driver Voyage Advances & Clearing',
                'account_type' => ChartOfAccount::TYPE_ASSET,
                'account_group' => 'current_asset',
                'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
                'is_system' => true,
                'is_reconcilable' => false,
                'description' => 'Temporary advance disbursements given to drivers for en-route voyage expenses',
            ],
            [
                'account_code' => ChartOfAccount::CODE_FUEL_ADVANCE_CLEARING,
                'account_name' => 'Fuel Card Clearing & Advances',
                'account_type' => ChartOfAccount::TYPE_ASSET,
                'account_group' => 'current_asset',
                'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
                'is_system' => true,
                'is_reconcilable' => false,
                'description' => 'Fuel card balances and fuel issued to attached fleet for future recovery',
            ],
            [
                'account_code' => ChartOfAccount::CODE_FLEET_VEHICLES,
                'account_name' => 'Company-Owned Fleet Vehicles',
                'account_type' => ChartOfAccount::TYPE_ASSET,
                'account_group' => 'non_current_asset',
                'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
                'is_system' => true,
                'is_reconcilable' => false,
                'description' => 'Capitalized cost of company-owned prime movers, trucks, and trailers',
            ],
            [
                'account_code' => ChartOfAccount::CODE_ACCUMULATED_DEPRECIATION,
                'account_name' => 'Accumulated Depreciation - Fleet Vehicles',
                'account_type' => ChartOfAccount::TYPE_ASSET,
                'account_group' => 'non_current_asset',
                'normal_balance' => ChartOfAccount::BALANCE_CREDIT, // Contra-Asset
                'is_system' => true,
                'is_reconcilable' => false,
                'description' => 'Cumulative depreciation of transport fleet',
            ],

            // LIABILITIES (2000 - 2999)
            [
                'account_code' => ChartOfAccount::CODE_AP_FUEL_SUPPLIERS,
                'account_name' => 'Accounts Payable - Fuel Suppliers',
                'account_type' => ChartOfAccount::TYPE_LIABILITY,
                'account_group' => 'current_liability',
                'normal_balance' => ChartOfAccount::BALANCE_CREDIT,
                'is_system' => true,
                'is_reconcilable' => false,
                'description' => 'Trade payables owed to PSO, Shell, Total, and credit fuel stations',
            ],
            [
                'account_code' => ChartOfAccount::CODE_AP_WORKSHOPS,
                'account_name' => 'Accounts Payable - Workshops & Spare Parts',
                'account_type' => ChartOfAccount::TYPE_LIABILITY,
                'account_group' => 'current_liability',
                'normal_balance' => ChartOfAccount::BALANCE_CREDIT,
                'is_system' => true,
                'is_reconcilable' => false,
                'description' => 'Payables for third-party vehicle repairs, parts vendors, and tyre suppliers',
            ],
            [
                'account_code' => ChartOfAccount::CODE_AP_VEHICLE_OWNERS,
                'account_name' => 'Accounts Payable - Attached Vehicle Owners',
                'account_type' => ChartOfAccount::TYPE_LIABILITY,
                'account_group' => 'current_liability',
                'normal_balance' => ChartOfAccount::BALANCE_CREDIT,
                'is_system' => true,
                'is_reconcilable' => false,
                'description' => 'Net freight hire settlements payable to third-party truck owners',
            ],
            [
                'account_code' => ChartOfAccount::CODE_CUSTOMER_ADVANCES,
                'account_name' => 'Customer Advances & Deposits',
                'account_type' => ChartOfAccount::TYPE_LIABILITY,
                'account_group' => 'current_liability',
                'normal_balance' => ChartOfAccount::BALANCE_CREDIT,
                'is_system' => true,
                'is_reconcilable' => false,
                'description' => 'Unapplied freight booking advances collected prior to invoice generation',
            ],
            [
                'account_code' => ChartOfAccount::CODE_SALARIES_PAYABLE,
                'account_name' => 'Driver Allowances & Salaries Payable',
                'account_type' => ChartOfAccount::TYPE_LIABILITY,
                'account_group' => 'current_liability',
                'normal_balance' => ChartOfAccount::BALANCE_CREDIT,
                'is_system' => true,
                'is_reconcilable' => false,
                'description' => 'Accrued driver allowances, overtime, and staff payroll payables',
            ],

            // EQUITY (3000 - 3999)
            [
                'account_code' => ChartOfAccount::CODE_OWNERS_CAPITAL,
                'account_name' => 'Share Capital / Proprietor Equity',
                'account_type' => ChartOfAccount::TYPE_EQUITY,
                'account_group' => 'equity',
                'normal_balance' => ChartOfAccount::BALANCE_CREDIT,
                'is_system' => true,
                'is_reconcilable' => false,
                'description' => 'Invested initial capital and owner equity',
            ],
            [
                'account_code' => ChartOfAccount::CODE_RETAINED_EARNINGS,
                'account_name' => 'Retained Earnings',
                'account_type' => ChartOfAccount::TYPE_EQUITY,
                'account_group' => 'equity',
                'normal_balance' => ChartOfAccount::BALANCE_CREDIT,
                'is_system' => true,
                'is_reconcilable' => false,
                'description' => 'Cumulative net profit / loss carried forward from previous fiscal years',
            ],

            // REVENUE (4000 - 4999)
            [
                'account_code' => ChartOfAccount::CODE_FREIGHT_REVENUE,
                'account_name' => 'Commercial Freight Revenue',
                'account_type' => ChartOfAccount::TYPE_REVENUE,
                'account_group' => 'operating_revenue',
                'normal_balance' => ChartOfAccount::BALANCE_CREDIT,
                'is_system' => true,
                'is_reconcilable' => false,
                'description' => 'Primary operating freight charged to shippers and customers',
            ],
            [
                'account_code' => ChartOfAccount::CODE_DEMURRAGE_INCOME,
                'account_name' => 'Demurrage & Detention Charges Income',
                'account_type' => ChartOfAccount::TYPE_REVENUE,
                'account_group' => 'operating_revenue',
                'normal_balance' => ChartOfAccount::BALANCE_CREDIT,
                'is_system' => true,
                'is_reconcilable' => false,
                'description' => 'Detention compensation billed for delayed loading or unloading',
            ],
            [
                'account_code' => ChartOfAccount::CODE_PENALTIES_INCOME,
                'account_name' => 'Contract Penalties & Claims Recovery',
                'account_type' => ChartOfAccount::TYPE_REVENUE,
                'account_group' => 'other_income',
                'normal_balance' => ChartOfAccount::BALANCE_CREDIT,
                'is_system' => true,
                'is_reconcilable' => false,
                'description' => 'Penalties deducted from attached trucks for transit breach or shortage',
            ],

            // DIRECT OPERATIONAL EXPENSES (5000 - 5999)
            [
                'account_code' => ChartOfAccount::CODE_DIRECT_FUEL,
                'account_name' => 'Diesel & Engine Fuel Consumption',
                'account_type' => ChartOfAccount::TYPE_EXPENSE,
                'account_group' => 'direct_operating_cost',
                'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
                'is_system' => true,
                'is_reconcilable' => false,
                'description' => 'Direct fuel expense consumed during goods transport voyages',
            ],
            [
                'account_code' => ChartOfAccount::CODE_TRIP_EXPENSES,
                'account_name' => 'Trip Operational Expenses & Tolls',
                'account_type' => ChartOfAccount::TYPE_EXPENSE,
                'account_group' => 'direct_operating_cost',
                'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
                'is_system' => true,
                'is_reconcilable' => false,
                'description' => 'Motorway toll taxes, weight-bridge, loading labor, and parking fees',
            ],
            [
                'account_code' => ChartOfAccount::CODE_DRIVER_ALLOWANCES,
                'account_name' => 'Driver Voyage Trip Allowances',
                'account_type' => ChartOfAccount::TYPE_EXPENSE,
                'account_group' => 'direct_operating_cost',
                'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
                'is_system' => true,
                'is_reconcilable' => false,
                'description' => 'Per-trip duty allowances, lodging, and meals paid to drivers',
            ],
            [
                'account_code' => ChartOfAccount::CODE_ATTACHED_TRUCK_HIRE,
                'account_name' => 'Attached Vehicle Freight & Hire Cost',
                'account_type' => ChartOfAccount::TYPE_EXPENSE,
                'account_group' => 'direct_operating_cost',
                'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
                'is_system' => true,
                'is_reconcilable' => false,
                'description' => 'Direct operating freight payable to third-party market attached truck owners',
            ],
            [
                'account_code' => ChartOfAccount::CODE_VEHICLE_MAINTENANCE,
                'account_name' => 'Fleet Maintenance, Workshop & Tyres',
                'account_type' => ChartOfAccount::TYPE_EXPENSE,
                'account_group' => 'direct_operating_cost',
                'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
                'is_system' => true,
                'is_reconcilable' => false,
                'description' => 'Vehicle maintenance repairs, lubricants, filter changes, and tyres',
            ],

            // ADMINISTRATIVE & OVERHEAD EXPENSES (6000 - 6999)
            [
                'account_code' => ChartOfAccount::CODE_DEPRECIATION_EXPENSE,
                'account_name' => 'Depreciation Expense - Transport Fleet',
                'account_type' => ChartOfAccount::TYPE_EXPENSE,
                'account_group' => 'admin_expense',
                'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
                'is_system' => true,
                'is_reconcilable' => false,
                'description' => 'Monthly / annual straight-line vehicle depreciation expense',
            ],
            [
                'account_code' => ChartOfAccount::CODE_ADMIN_EXPENSES,
                'account_name' => 'General Terminal & Administrative Overheads',
                'account_type' => ChartOfAccount::TYPE_EXPENSE,
                'account_group' => 'admin_expense',
                'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
                'is_system' => true,
                'is_reconcilable' => false,
                'description' => 'Terminal office rent, utility bills, management salaries, and software subscriptions',
            ],
        ];

        DB::transaction(function () use ($company, $defaultAccounts) {
            foreach ($defaultAccounts as $data) {
                ChartOfAccount::withoutGlobalScopes()->firstOrCreate(
                    [
                        'company_id' => $company->id,
                        'account_code' => $data['account_code'],
                    ],
                    $data
                );
            }
        });
    }

    /**
     * Retrieve an account by code for a company.
     */
    public function getAccountByCode(int $companyId, string $code): ?ChartOfAccount
    {
        return ChartOfAccount::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('account_code', $code)
            ->first();
    }
}

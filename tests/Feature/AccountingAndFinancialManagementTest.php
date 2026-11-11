<?php

namespace Tests\Feature;

use App\Livewire\Finance\BankManager;
use App\Livewire\Finance\ChartOfAccountsManager;
use App\Livewire\Finance\CustomerBillingManager;
use App\Livewire\Finance\DriverSettlementManager;
use App\Livewire\Finance\FinancialReportManager;
use App\Livewire\Finance\GeneralLedgerManager;
use App\Models\AccountingPeriod;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\CustomerPayment;
use App\Models\CustomerPaymentAllocation;
use App\Models\Driver;
use App\Models\DriverSettlement;
use App\Models\FinancialYear;
use App\Models\FuelEntry;
use App\Models\JobCard;
use App\Models\JournalVoucher;
use App\Models\JournalVoucherItem;
use App\Models\OwnerPayment;
use App\Models\OwnerSettlement;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\Trip;
use App\Models\TripExpense;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleOwner;
use App\Services\AccountingIntegrationService;
use App\Services\BankManagementService;
use App\Services\BillingAndReceivableService;
use App\Services\ChartOfAccountsService;
use App\Services\DriverAccountService;
use App\Services\FinancialPeriodService;
use App\Services\FinancialReportService;
use App\Services\GeneralLedgerService;
use App\Services\TenantContextService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class AccountingAndFinancialManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected Company $companyB;
    protected User $userA;
    protected User $userB;
    protected Branch $branchA;
    protected Branch $branchB;
    protected FinancialYear $fyA;
    protected FinancialYear $fyB;
    protected Customer $customerA;
    protected Driver $driverA;
    protected Vehicle $vehicleA;
    protected Trip $tripA;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Roles & Plan
        $adminRole = Role::create([
            'company_id' => null,
            'name' => 'Tenant Administrator',
            'slug' => 'tenant_admin',
            'description' => 'Administrator for tenant organization',
            'is_system' => true,
        ]);

        $plan = SubscriptionPlan::create([
            'name' => 'Enterprise Logistics',
            'slug' => 'enterprise-logistics',
            'monthly_price' => 25000,
            'max_vehicles' => 100,
            'max_users' => 50,
            'max_branches' => 10,
            'features' => ['accounting', 'billing', 'driver_settlements', 'bank_reconciliation'],
            'is_active' => true,
        ]);

        // 2. Companies
        $this->companyA = Company::create([
            'name' => 'Premier Freight Carriers Ltd',
            'legal_name' => 'Premier Freight Carriers Private Limited',
            'slug' => 'premier-freight',
            'code' => 'PFC',
            'email' => 'finance@premierfreight.com',
            'phone' => '+92-21-3990011',
            'status' => 'active',
            'plan_id' => $plan->id,
            'onboarding_completed_at' => now(),
            'onboarding_step' => 6,
        ]);

        $this->companyB = Company::create([
            'name' => 'Falcon Global Cargo',
            'legal_name' => 'Falcon Global Cargo SMC Pvt Ltd',
            'slug' => 'falcon-cargo',
            'code' => 'FGC',
            'email' => 'finance@falcon.com',
            'phone' => '+92-42-3880022',
            'status' => 'active',
            'plan_id' => $plan->id,
            'onboarding_completed_at' => now(),
            'onboarding_step' => 6,
        ]);

        // 3. Branches
        $this->branchA = Branch::create([
            'company_id' => $this->companyA->id,
            'name' => 'Karachi Central Terminal',
            'code' => 'KHI-01',
            'city' => 'Karachi',
            'is_head_office' => true,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->branchB = Branch::create([
            'company_id' => $this->companyB->id,
            'name' => 'Lahore Thokar Terminal',
            'code' => 'LHE-01',
            'city' => 'Lahore',
            'is_head_office' => true,
            'is_default' => true,
            'is_active' => true,
        ]);

        // 4. Users
        $this->userA = User::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'role_id' => $adminRole->id,
            'name' => 'Finance Controller A',
            'email' => 'finance@premierfreight.com',
            'password' => Hash::make('Secret123!'),
            'status' => 'active',
            'is_super_admin' => false,
        ]);
        $this->companyA->update(['owner_id' => $this->userA->id]);

        $this->userB = User::create([
            'company_id' => $this->companyB->id,
            'branch_id' => $this->branchB->id,
            'role_id' => $adminRole->id,
            'name' => 'Finance Controller B',
            'email' => 'finance@falcon.com',
            'password' => Hash::make('Secret123!'),
            'status' => 'active',
            'is_super_admin' => false,
        ]);
        $this->companyB->update(['owner_id' => $this->userB->id]);

        // 5. Open Financial Years with Accounting Periods (FinancialPeriodService)
        $fps = app(FinancialPeriodService::class);
        $this->fyA = $fps->createFinancialYearWithPeriods(
            company: $this->companyA,
            name: 'FY 2026',
            startDate: now()->startOfYear()->toDateString(),
            endDate: now()->endOfYear()->toDateString(),
            makeCurrent: true
        );

        $this->fyB = $fps->createFinancialYearWithPeriods(
            company: $this->companyB,
            name: 'FY 2026',
            startDate: now()->startOfYear()->toDateString(),
            endDate: now()->endOfYear()->toDateString(),
            makeCurrent: true
        );

        // 6. Seed Default Chart of Accounts for both tenants
        $coaService = app(ChartOfAccountsService::class);
        $coaService->seedDefaultChartOfAccounts($this->companyA);
        $coaService->seedDefaultChartOfAccounts($this->companyB);

        // 7. Core Operational entities for Company A
        $this->customerA = Customer::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'customer_type' => 'corporate',
            'name' => 'Unilever Pakistan Limited',
            'code' => 'CUST-UNI',
            'phone' => '+92-300-1112233',
            'email' => 'logistics@unilever.com.pk',
            'credit_limit' => 2000000,
            'is_active' => true,
        ]);

        $this->vehicleA = Vehicle::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'registration_number' => 'LES-9988',
            'fleet_number' => 'TRK-01',
            'ownership_type' => Vehicle::OWNERSHIP_COMPANY,
            'vehicle_category' => 'heavy_commercial',
            'vehicle_type' => '10_wheeler_truck',
            'make' => 'Hino',
            'model' => '500 Series Prime Mover',
            'manufacturing_year' => 2021,
            'chassis_number' => 'CH-LES9988',
            'engine_number' => 'ENG-LES9988',
            'payload_capacity_tons' => 25.00,
            'current_odometer_km' => 120000,
            'status' => Vehicle::STATUS_AVAILABLE,
        ]);

        $this->driverA = Driver::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'name' => 'Muhammad Aslam',
            'cnic_number' => '35201-1234567-8',
            'license_number' => 'LHR-HTV-9988',
            'license_category' => 'HTV',
            'license_expiry_date' => now()->addYears(2),
            'phone' => '+92-301-5551234',
            'status' => 'available',
        ]);

        $this->tripA = Trip::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'trip_number' => 'TR-2026-0001',
            'trip_date' => now()->toDateString(),
            'origin_branch_id' => $this->branchA->id,
            'destination_branch_id' => $this->branchA->id,
            'origin_city' => 'Karachi',
            'destination_city' => 'Lahore',
            'total_distance_km' => 1250,
            'status' => 'dispatched',
        ]);

        \App\Models\TripResource::create([
            'company_id' => $this->companyA->id,
            'trip_id' => $this->tripA->id,
            'resource_type' => 'vehicle',
            'vehicle_id' => $this->vehicleA->id,
            'role' => 'primary_vehicle',
            'assigned_at' => now()->subDays(2),
            'is_active' => true,
        ]);

        \App\Models\TripResource::create([
            'company_id' => $this->companyA->id,
            'trip_id' => $this->tripA->id,
            'resource_type' => 'driver',
            'driver_id' => $this->driverA->id,
            'role' => 'primary_driver',
            'assigned_at' => now()->subDays(2),
            'is_active' => true,
        ]);
    }

    /**
     * Test 1: Default Chart of Accounts Seeding & Idempotency.
     */
    public function test_default_chart_of_accounts_seeding_and_idempotency(): void
    {
        $coaService = app(ChartOfAccountsService::class);
        $countBefore = ChartOfAccount::withoutGlobalScopes()->where('company_id', $this->companyA->id)->count();

        $this->assertGreaterThanOrEqual(15, $countBefore);

        // Core accounts must exist
        $cash = $coaService->getAccountByCode($this->companyA->id, ChartOfAccount::CODE_CASH_IN_HAND);
        $bank = $coaService->getAccountByCode($this->companyA->id, ChartOfAccount::CODE_BANK_ACCOUNTS);
        $ar = $coaService->getAccountByCode($this->companyA->id, ChartOfAccount::CODE_ACCOUNTS_RECEIVABLE);
        $rev = $coaService->getAccountByCode($this->companyA->id, ChartOfAccount::CODE_FREIGHT_REVENUE);
        $fuel = $coaService->getAccountByCode($this->companyA->id, ChartOfAccount::CODE_DIRECT_FUEL);
        $advances = $coaService->getAccountByCode($this->companyA->id, ChartOfAccount::CODE_CUSTOMER_ADVANCES);

        $this->assertNotNull($cash);
        $this->assertNotNull($bank);
        $this->assertNotNull($ar);
        $this->assertNotNull($rev);
        $this->assertNotNull($fuel);
        $this->assertNotNull($advances);

        $this->assertEquals(ChartOfAccount::BALANCE_DEBIT, $cash->normal_balance);
        $this->assertEquals(ChartOfAccount::BALANCE_CREDIT, $advances->normal_balance);
        $this->assertEquals(ChartOfAccount::TYPE_LIABILITY, $advances->account_type);

        // Re-seeding must be strictly idempotent
        $coaService->seedDefaultChartOfAccounts($this->companyA);
        $countAfter = ChartOfAccount::withoutGlobalScopes()->where('company_id', $this->companyA->id)->count();
        $this->assertEquals($countBefore, $countAfter);
    }

    /**
     * Test 2: Double-Entry Enforcement — Strict debit/credit balance, rejecting unbalanced vouchers.
     */
    public function test_general_ledger_enforces_strict_double_entry_balance(): void
    {
        $glService = app(GeneralLedgerService::class);
        $coaService = app(ChartOfAccountsService::class);

        $cash = $coaService->getAccountByCode($this->companyA->id, ChartOfAccount::CODE_CASH_IN_HAND);
        $capital = $coaService->getAccountByCode($this->companyA->id, ChartOfAccount::CODE_OWNERS_CAPITAL);

        // 1. Unbalanced voucher must throw InvalidArgumentException
        $this->expectException(InvalidArgumentException::class);
        $glService->postVoucher([
            'company_id' => $this->companyA->id,
            'voucher_date' => now()->toDateString(),
            'voucher_type' => JournalVoucher::TYPE_JOURNAL,
            'narration' => 'Unbalanced Capital Injection',
            'items' => [
                ['chart_of_account_id' => $cash->id, 'debit' => 500000.00, 'credit' => 0.00],
                ['chart_of_account_id' => $capital->id, 'debit' => 0.00, 'credit' => 450000.00], // 50,000 difference!
            ],
        ], $this->userA);
    }

    /**
     * Test 3: Balanced Voucher Posting atomically updates account balances.
     */
    public function test_balanced_voucher_posting_updates_account_balances(): void
    {
        $glService = app(GeneralLedgerService::class);
        $coaService = app(ChartOfAccountsService::class);

        $cash = $coaService->getAccountByCode($this->companyA->id, ChartOfAccount::CODE_CASH_IN_HAND);
        $capital = $coaService->getAccountByCode($this->companyA->id, ChartOfAccount::CODE_OWNERS_CAPITAL);

        $initialCashBalance = (float) $cash->current_balance;
        $initialCapitalBalance = (float) $capital->current_balance;

        $voucher = $glService->postVoucher([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'voucher_date' => now()->toDateString(),
            'voucher_type' => JournalVoucher::TYPE_OPENING,
            'narration' => 'Initial Owners Capital Injection',
            'items' => [
                ['chart_of_account_id' => $cash->id, 'debit' => 1000000.00, 'credit' => 0.00],
                ['chart_of_account_id' => $capital->id, 'debit' => 0.00, 'credit' => 1000000.00],
            ],
        ], $this->userA);

        $this->assertNotNull($voucher);
        $this->assertTrue($voucher->isBalanced());
        $this->assertTrue($voucher->isPosted());
        $this->assertEquals(1000000.00, (float) $voucher->total_debit);
        $this->assertEquals(1000000.00, (float) $voucher->total_credit);

        // Verify atomic account balances
        $cash->refresh();
        $capital->refresh();
        $this->assertEquals($initialCashBalance + 1000000.00, (float) $cash->current_balance);
        $this->assertEquals($initialCapitalBalance + 1000000.00, (float) $capital->current_balance);
    }

    /**
     * Test 4: Financial Period Enforcement — Transaction in closed financial year/period is rejected.
     */
    public function test_transactions_in_closed_financial_period_are_rejected(): void
    {
        $glService = app(GeneralLedgerService::class);
        $coaService = app(ChartOfAccountsService::class);
        $fps = app(FinancialPeriodService::class);

        // Close the financial year
        $fps->closeFinancialYear($this->fyA, 'Year-end financial audits completed.');

        $cash = $coaService->getAccountByCode($this->companyA->id, ChartOfAccount::CODE_CASH_IN_HAND);
        $capital = $coaService->getAccountByCode($this->companyA->id, ChartOfAccount::CODE_OWNERS_CAPITAL);

        $this->expectException(AuthorizationException::class);
        $glService->postVoucher([
            'company_id' => $this->companyA->id,
            'voucher_date' => now()->toDateString(),
            'voucher_type' => JournalVoucher::TYPE_JOURNAL,
            'narration' => 'Posting into closed year',
            'items' => [
                ['chart_of_account_id' => $cash->id, 'debit' => 1000.00, 'credit' => 0.00],
                ['chart_of_account_id' => $capital->id, 'debit' => 0.00, 'credit' => 1000.00],
            ],
        ], $this->userA);
    }

    /**
     * Test 5: Reversal of Posted Voucher creates offsetting entries and restores account balances.
     */
    public function test_reversal_of_posted_voucher_restores_balances(): void
    {
        $glService = app(GeneralLedgerService::class);
        $coaService = app(ChartOfAccountsService::class);

        $cash = $coaService->getAccountByCode($this->companyA->id, ChartOfAccount::CODE_CASH_IN_HAND);
        $admin = $coaService->getAccountByCode($this->companyA->id, ChartOfAccount::CODE_ADMIN_EXPENSES);

        $originalCash = (float) $cash->current_balance;
        $originalAdmin = (float) $admin->current_balance;

        // Post original expense
        $voucher = $glService->postVoucher([
            'company_id' => $this->companyA->id,
            'voucher_date' => now()->toDateString(),
            'voucher_type' => JournalVoucher::TYPE_CASH_PAYMENT,
            'narration' => 'Office stationary purchase',
            'items' => [
                ['chart_of_account_id' => $admin->id, 'debit' => 25000.00, 'credit' => 0.00],
                ['chart_of_account_id' => $cash->id, 'debit' => 0.00, 'credit' => 25000.00],
            ],
        ], $this->userA);

        $cash->refresh();
        $admin->refresh();
        $this->assertEquals($originalCash - 25000.00, (float) $cash->current_balance);
        $this->assertEquals($originalAdmin + 25000.00, (float) $admin->current_balance);

        // Reverse the voucher
        $reversalVoucher = $glService->reverseVoucher(
            voucher: $voucher,
            reason: 'Erroneous billing entry',
            user: $this->userA
        );

        $this->assertNotNull($reversalVoucher);
        $voucher->refresh();
        $this->assertTrue($voucher->isReversed());
        $this->assertEquals('Erroneous billing entry', $voucher->reversal_reason);

        // Account balances must be completely restored
        $cash->refresh();
        $admin->refresh();
        $this->assertEquals($originalCash, (float) $cash->current_balance);
        $this->assertEquals($originalAdmin, (float) $admin->current_balance);
    }

    /**
     * Test 6: Customer Invoicing and Automatic Accounts Receivable (AR) Posting.
     */
    public function test_customer_invoicing_and_ar_posting(): void
    {
        $billingService = app(BillingAndReceivableService::class);
        $coaService = app(ChartOfAccountsService::class);

        $arAccount = $coaService->getAccountByCode($this->companyA->id, ChartOfAccount::CODE_ACCOUNTS_RECEIVABLE);
        $revAccount = $coaService->getAccountByCode($this->companyA->id, ChartOfAccount::CODE_FREIGHT_REVENUE);
        $initialAR = (float) $arAccount->current_balance;
        $initialRev = (float) $revAccount->current_balance;

        // 1. Create Invoice Draft
        $invoice = $billingService->createInvoice([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'customer_id' => $this->customerA->id,
            'trip_id' => $this->tripA->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'tax_rate_percent' => 5.0, // 5% sales tax
            'discount_amount' => 5000.00,
            'items' => [
                ['description' => 'Karachi to Lahore Full Truck Load Freight', 'quantity' => 1, 'unit_rate' => 100000.00, 'amount' => 100000.00],
            ],
        ], $this->userA);

        $this->assertEquals(CustomerInvoice::STATUS_DRAFT, $invoice->status);
        $this->assertEquals(100000.00, (float) $invoice->subtotal);
        $this->assertEquals(5000.00, (float) $invoice->tax_amount); // 5% of 100k
        $this->assertEquals(100000.00, (float) $invoice->total_amount); // 100k + 5k - 5k
        $this->assertEquals(100000.00, (float) $invoice->balance_due);

        // 2. Post Invoice to General Ledger
        $postedInvoice = $billingService->postInvoice($invoice, $this->userA);

        $this->assertTrue($postedInvoice->isPosted());
        $this->assertNotNull($postedInvoice->journal_voucher_id);

        $voucher = $postedInvoice->voucher;
        $this->assertNotNull($voucher);
        $this->assertTrue($voucher->isBalanced());

        // AR and Revenue balances updated
        $arAccount->refresh();
        $revAccount->refresh();
        $this->assertEquals($initialAR + 100000.00, (float) $arAccount->current_balance);
        $this->assertEquals($initialRev + 95000.00, (float) $revAccount->current_balance); // Net revenue
    }

    /**
     * Test 7: Direct Customer Payment settles Accounts Receivable and updates invoice status.
     */
    public function test_direct_customer_payment_settles_receivable(): void
    {
        $billingService = app(BillingAndReceivableService::class);
        $coaService = app(ChartOfAccountsService::class);

        $invoice = $billingService->createInvoice([
            'company_id' => $this->companyA->id,
            'customer_id' => $this->customerA->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'items' => [
                ['description' => 'Direct Goods Transportation', 'quantity' => 1, 'unit_rate' => 60000.00, 'amount' => 60000.00],
            ],
        ], $this->userA);
        $billingService->postInvoice($invoice, $this->userA);

        $cash = $coaService->getAccountByCode($this->companyA->id, ChartOfAccount::CODE_CASH_IN_HAND);
        $ar = $coaService->getAccountByCode($this->companyA->id, ChartOfAccount::CODE_ACCOUNTS_RECEIVABLE);

        $cashBefore = (float) $cash->current_balance;
        $arBefore = (float) $ar->current_balance;

        // Record Partial Payment of 40,000
        $payment1 = $billingService->recordCustomerPayment([
            'company_id' => $this->companyA->id,
            'customer_id' => $this->customerA->id,
            'customer_invoice_id' => $invoice->id,
            'amount' => 40000.00,
            'payment_type' => CustomerPayment::TYPE_INVOICE_PAYMENT,
            'payment_method' => CustomerPayment::METHOD_CASH,
            'receipt_date' => now()->toDateString(),
        ], $this->userA);

        $invoice->refresh();
        $this->assertEquals(CustomerInvoice::PAYMENT_PARTIALLY_PAID, $invoice->payment_status);
        $this->assertEquals(40000.00, (float) $invoice->paid_amount);
        $this->assertEquals(20000.00, (float) $invoice->balance_due);

        // Record Remaining Payment of 20,000
        $payment2 = $billingService->recordCustomerPayment([
            'company_id' => $this->companyA->id,
            'customer_id' => $this->customerA->id,
            'customer_invoice_id' => $invoice->id,
            'amount' => 20000.00,
            'payment_type' => CustomerPayment::TYPE_INVOICE_PAYMENT,
            'payment_method' => CustomerPayment::METHOD_CASH,
            'receipt_date' => now()->toDateString(),
        ], $this->userA);

        $invoice->refresh();
        $this->assertEquals(CustomerInvoice::PAYMENT_PAID, $invoice->payment_status);
        $this->assertEquals(CustomerInvoice::STATUS_PAID, $invoice->status);
        $this->assertEquals(60000.00, (float) $invoice->paid_amount);
        $this->assertEquals(0.00, (float) $invoice->balance_due);

        $cash->refresh();
        $ar->refresh();
        $this->assertEquals($cashBefore + 60000.00, (float) $cash->current_balance);
        $this->assertEquals($arBefore - 60000.00, (float) $ar->current_balance);
    }

    /**
     * Test 8: Customer Advance Treatment — Held as Liability (2200) until explicitly allocated to an invoice.
     */
    public function test_customer_advance_liability_treatment_and_subsequent_allocation(): void
    {
        $billingService = app(BillingAndReceivableService::class);
        $coaService = app(ChartOfAccountsService::class);

        $cash = $coaService->getAccountByCode($this->companyA->id, ChartOfAccount::CODE_CASH_IN_HAND);
        $advances = $coaService->getAccountByCode($this->companyA->id, ChartOfAccount::CODE_CUSTOMER_ADVANCES);
        $ar = $coaService->getAccountByCode($this->companyA->id, ChartOfAccount::CODE_ACCOUNTS_RECEIVABLE);

        $advancesBefore = (float) $advances->current_balance;

        // 1. Customer deposits 150,000 advance before any invoice is issued
        $advance = $billingService->recordCustomerPayment([
            'company_id' => $this->companyA->id,
            'customer_id' => $this->customerA->id,
            'amount' => 150000.00,
            'payment_type' => CustomerPayment::TYPE_CUSTOMER_ADVANCE,
            'payment_method' => CustomerPayment::METHOD_CASH,
            'receipt_date' => now()->toDateString(),
            'notes' => 'Advance booking deposit',
        ], $this->userA);

        $this->assertEquals(150000.00, (float) $advance->amount);
        $this->assertEquals(150000.00, (float) $advance->unapplied_amount);
        $this->assertTrue($advance->isAdvance());

        // Advances Liability must have increased by 150,000
        $advances->refresh();
        $this->assertEquals($advancesBefore + 150000.00, (float) $advances->current_balance);

        // 2. Later, an invoice of 100,000 is issued to customer
        $invoice = $billingService->createInvoice([
            'company_id' => $this->companyA->id,
            'customer_id' => $this->customerA->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'items' => [
                ['description' => 'Freight Delivery Services', 'quantity' => 1, 'unit_rate' => 100000.00, 'amount' => 100000.00],
            ],
        ], $this->userA);
        $billingService->postInvoice($invoice, $this->userA);

        $ar->refresh();
        $arBeforeAlloc = (float) $ar->current_balance;

        // 3. Apply 100,000 from the advance deposit to the invoice
        $allocation = $billingService->applyCustomerAdvance(
            advance: $advance,
            invoice: $invoice,
            amount: 100000.00,
            user: $this->userA
        );

        $this->assertNotNull($allocation);
        $this->assertEquals(100000.00, (float) $allocation->allocated_amount);

        // Advance unapplied amount decreased
        $advance->refresh();
        $this->assertEquals(50000.00, (float) $advance->unapplied_amount);

        // Invoice is now fully paid
        $invoice->refresh();
        $this->assertEquals(CustomerInvoice::PAYMENT_PAID, $invoice->payment_status);
        $this->assertEquals(0.00, (float) $invoice->balance_due);

        // Advances liability decreased by 100,000
        $advances->refresh();
        $this->assertEquals($advancesBefore + 50000.00, (float) $advances->current_balance);

        // AR decreased by 100,000
        $ar->refresh();
        $this->assertEquals($arBeforeAlloc - 100000.00, (float) $ar->current_balance);
    }

    /**
     * Test 9: Driver Voyage Settlement — Reconciles advances vs allowance & out-of-pocket expenses.
     */
    public function test_driver_settlement_reconciliation_and_gl_posting(): void
    {
        $driverService = app(DriverAccountService::class);
        $coaService = app(ChartOfAccountsService::class);

        $allowanceAcc = $coaService->getAccountByCode($this->companyA->id, ChartOfAccount::CODE_DRIVER_ALLOWANCES);
        $advanceAcc = $coaService->getAccountByCode($this->companyA->id, ChartOfAccount::CODE_DRIVER_ADVANCE_CLEARING);
        $payableAcc = $coaService->getAccountByCode($this->companyA->id, ChartOfAccount::CODE_SALARIES_PAYABLE);

        // Driver given 20,000 advance; Earned 25,000 allowance + 5,000 toll reimbursements
        // Net Payable to driver = (25,000 + 5,000) - 20,000 = 10,000
        $settlement = $driverService->createDriverSettlement([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'driver_id' => $this->driverA->id,
            'trip_id' => $this->tripA->id,
            'settlement_date' => now()->toDateString(),
            'total_advances' => 20000.00,
            'driver_allowance' => 25000.00,
            'out_of_pocket_expenses' => 5000.00,
            'notes' => 'Karachi to Lahore voyage complete settlement',
        ], $this->userA);

        $this->assertEquals(10000.00, (float) $settlement->net_payable);
        $this->assertEquals(0.00, (float) $settlement->net_recoverable);

        // Post Driver Settlement
        $postedSettlement = $driverService->postDriverSettlement($settlement, $this->userA);

        $this->assertEquals(DriverSettlement::STATUS_POSTED, $postedSettlement->status);
        $this->assertNotNull($postedSettlement->journal_voucher_id);

        $voucher = $postedSettlement->voucher;
        $this->assertTrue($voucher->isBalanced());
        $this->assertEquals(30000.00, (float) $voucher->total_debit);
        $this->assertEquals(30000.00, (float) $voucher->total_credit);
    }

    /**
     * Test 10: Bank Management — Inter-Account Transfer (Contra Voucher) and Bank Reconciliation.
     */
    public function test_bank_transfer_contra_voucher_and_reconciliation(): void
    {
        $bankService = app(BankManagementService::class);

        // 1. Create two bank accounts
        $hbl = $bankService->createBankAccount([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'account_name' => 'HBL Corporate Checking',
            'account_type' => BankAccount::TYPE_BANK,
            'bank_name' => 'Habib Bank Limited',
            'opening_balance' => 500000.00,
        ], $this->userA);

        $mcb = $bankService->createBankAccount([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'account_name' => 'MCB Freight Operational',
            'account_type' => BankAccount::TYPE_BANK,
            'bank_name' => 'MCB Bank Ltd',
            'opening_balance' => 100000.00,
        ], $this->userA);

        // 2. Transfer 200,000 from HBL to MCB
        $voucher = $bankService->recordTransfer(
            fromAccount: $hbl,
            toAccount: $mcb,
            amount: 200000.00,
            transferDate: now()->toDateString(),
            reference: 'TRF-HBL-MCB-01',
            user: $this->userA
        );

        $this->assertNotNull($voucher);
        $this->assertEquals(JournalVoucher::TYPE_CONTRA, $voucher->voucher_type);
        $this->assertTrue($voucher->isBalanced());

        $hbl->refresh();
        $mcb->refresh();
        $this->assertEquals(300000.00, (float) $hbl->current_balance);
        $this->assertEquals(300000.00, (float) $mcb->current_balance);

        // 3. Bank Reconciliation
        $recon = $bankService->reconcileBankStatement(
            bankAccount: $hbl,
            data: [
                'statement_date' => now()->toDateString(),
                'statement_starting_balance' => 500000.00,
                'statement_ending_balance' => 300000.00,
                'uncleared_deposits' => 0.00,
                'uncleared_cheques' => 0.00,
            ],
            user: $this->userA
        );

        $this->assertEquals(BankReconciliation::STATUS_RECONCILED, $recon->status);
        $this->assertEquals(0.00, (float) $recon->difference);
    }

    /**
     * Test 11: Operational Transactions GL Integration — Fuel, Trip Expenses, Job Cards, Owner Settlements.
     */
    public function test_operational_transactions_gl_integration(): void
    {
        $integrationService = app(AccountingIntegrationService::class);

        // 1. Fuel purchase & issue
        $fuel = FuelEntry::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'vehicle_id' => $this->vehicleA->id,
            'driver_id' => $this->driverA->id,
            'trip_id' => $this->tripA->id,
            'entry_number' => 'FL-2026-0001',
            'entry_date' => now()->toDateString(),
            'fuel_type' => 'diesel',
            'payment_method' => 'credit',
            'quantity_liters' => 200,
            'rate_per_liter' => 280.00,
            'total_amount' => 56000.00,
            'odometer_km' => 125000,
        ]);

        $fuelVoucher = $integrationService->postFuelAccounting($fuel, $this->userA);
        $this->assertNotNull($fuelVoucher);
        $this->assertTrue($fuelVoucher->isBalanced());
        $this->assertEquals(56000.00, (float) $fuelVoucher->total_debit);

        // 2. Trip Expense
        $expense = TripExpense::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'trip_id' => $this->tripA->id,
            'voucher_number' => 'EXP-2026-0001',
            'expense_date' => now()->toDateString(),
            'expense_category' => 'toll_tax',
            'amount' => 8500.00,
            'payment_method' => 'cash',
            'status' => 'approved',
        ]);

        $expVoucher = $integrationService->postTripExpenseAccounting($expense, $this->userA);
        $this->assertNotNull($expVoucher);
        $this->assertTrue($expVoucher->isBalanced());
        $this->assertEquals(8500.00, (float) $expVoucher->total_debit);

        // 3. Workshop Job Card
        $jobCard = JobCard::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'vehicle_id' => $this->vehicleA->id,
            'job_card_number' => 'JC-2026-0001',
            'workshop_type' => 'in_house',
            'scheduled_date' => now()->toDateString(),
            'labour_charges' => 5000.00,
            'parts_total_cost' => 15000.00,
            'total_cost' => 20000.00,
            'odometer_km' => 125000,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $jcVoucher = $integrationService->postMaintenanceAccounting($jobCard, $this->userA);
        $this->assertNotNull($jcVoucher);
        $this->assertTrue($jcVoucher->isBalanced());
        $this->assertEquals(20000.00, (float) $jcVoucher->total_debit);
    }

    /**
     * Test 12: Comprehensive Financial Statements — Trial Balance, P&L, and Balance Sheet agreement.
     */
    public function test_financial_statements_agreement_and_balance(): void
    {
        $reportService = app(FinancialReportService::class);
        $glService = app(GeneralLedgerService::class);
        $coaService = app(ChartOfAccountsService::class);

        $cash = $coaService->getAccountByCode($this->companyA->id, ChartOfAccount::CODE_CASH_IN_HAND);
        $capital = $coaService->getAccountByCode($this->companyA->id, ChartOfAccount::CODE_OWNERS_CAPITAL);
        $revenue = $coaService->getAccountByCode($this->companyA->id, ChartOfAccount::CODE_FREIGHT_REVENUE);
        $fuel = $coaService->getAccountByCode($this->companyA->id, ChartOfAccount::CODE_DIRECT_FUEL);
        $admin = $coaService->getAccountByCode($this->companyA->id, ChartOfAccount::CODE_ADMIN_EXPENSES);

        // 1. Capital Injection: 1,000,000
        $glService->postVoucher([
            'company_id' => $this->companyA->id,
            'voucher_date' => now()->toDateString(),
            'voucher_type' => JournalVoucher::TYPE_OPENING,
            'narration' => 'Initial Capital',
            'items' => [
                ['chart_of_account_id' => $cash->id, 'debit' => 1000000.00, 'credit' => 0.00],
                ['chart_of_account_id' => $capital->id, 'debit' => 0.00, 'credit' => 1000000.00],
            ],
        ], $this->userA);

        // 2. Freight Revenue Earned in Cash: 300,000
        $glService->postVoucher([
            'company_id' => $this->companyA->id,
            'voucher_date' => now()->toDateString(),
            'voucher_type' => JournalVoucher::TYPE_CASH_RECEIPT,
            'narration' => 'Freight Revenue',
            'items' => [
                ['chart_of_account_id' => $cash->id, 'debit' => 300000.00, 'credit' => 0.00],
                ['chart_of_account_id' => $revenue->id, 'debit' => 0.00, 'credit' => 300000.00],
            ],
        ], $this->userA);

        // 3. Direct Fuel Cost Paid: 100,000
        $glService->postVoucher([
            'company_id' => $this->companyA->id,
            'voucher_date' => now()->toDateString(),
            'voucher_type' => JournalVoucher::TYPE_CASH_PAYMENT,
            'narration' => 'Diesel Fuel Cost',
            'items' => [
                ['chart_of_account_id' => $fuel->id, 'debit' => 100000.00, 'credit' => 0.00],
                ['chart_of_account_id' => $cash->id, 'debit' => 0.00, 'credit' => 100000.00],
            ],
        ], $this->userA);

        // 4. Admin Expenses Paid: 50,000
        $glService->postVoucher([
            'company_id' => $this->companyA->id,
            'voucher_date' => now()->toDateString(),
            'voucher_type' => JournalVoucher::TYPE_CASH_PAYMENT,
            'narration' => 'Office Overheads',
            'items' => [
                ['chart_of_account_id' => $admin->id, 'debit' => 50000.00, 'credit' => 0.00],
                ['chart_of_account_id' => $cash->id, 'debit' => 0.00, 'credit' => 50000.00],
            ],
        ], $this->userA);

        // Verify Trial Balance
        $tb = $reportService->getTrialBalance($this->companyA->id, now()->toDateString());
        $this->assertTrue($tb['is_balanced'], "Trial Balance is not balanced! Difference: {$tb['difference']}");
        $this->assertEquals($tb['total_debits'], $tb['total_credits']);

        // Verify Profit and Loss
        $pnl = $reportService->getProfitAndLoss($this->companyA->id, now()->startOfYear()->toDateString(), now()->toDateString());
        $this->assertEquals(300000.00, $pnl['total_revenue']);
        $this->assertEquals(100000.00, $pnl['total_direct_costs']);
        $this->assertEquals(200000.00, $pnl['gross_margin']); // 300k - 100k
        $this->assertEquals(50000.00, $pnl['total_admin_expenses']);
        $this->assertEquals(150000.00, $pnl['net_profit']); // 200k - 50k

        // Verify Balance Sheet equation (Assets = Liabilities + Equity)
        $bs = $reportService->getBalanceSheet($this->companyA->id, now()->toDateString());
        $this->assertTrue($bs['is_balanced'], "Balance Sheet is out of balance! Difference: {$bs['difference']}");
        $this->assertEquals($bs['total_assets'], $bs['total_liabilities_and_equity']);

        // Cash Asset = 1,000,000 + 300,000 - 100,000 - 50,000 = 1,150,000
        // Capital = 1,000,000 + Net Profit 150,000 = 1,150,000
        $this->assertEquals(1150000.00, $bs['total_assets']);
        $this->assertEquals(1150000.00, $bs['total_equity']);
    }

    /**
     * Test 13: Trip Profitability Calculation distinguishes gross revenue, voyage direct costs, and net margin.
     */
    public function test_trip_profitability_calculation(): void
    {
        $reportService = app(FinancialReportService::class);
        $billingService = app(BillingAndReceivableService::class);

        // 1. Create and post customer invoice for Trip A: 250,000
        $invoice = $billingService->createInvoice([
            'company_id' => $this->companyA->id,
            'customer_id' => $this->customerA->id,
            'trip_id' => $this->tripA->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'items' => [
                ['description' => 'Karachi to Lahore Voyage Freight', 'quantity' => 1, 'unit_rate' => 250000.00, 'amount' => 250000.00],
            ],
        ], $this->userA);
        $billingService->postInvoice($invoice, $this->userA);

        // 2. Direct Fuel on Trip A: 70,000
        FuelEntry::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'vehicle_id' => $this->vehicleA->id,
            'trip_id' => $this->tripA->id,
            'entry_number' => 'FL-TP-01',
            'entry_date' => now()->toDateString(),
            'fuel_type' => 'diesel',
            'payment_method' => 'cash',
            'quantity_liters' => 250,
            'rate_per_liter' => 280.00,
            'total_amount' => 70000.00,
            'odometer_km' => 126000,
        ]);

        // 3. Tolls on Trip A: 10,000
        TripExpense::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'trip_id' => $this->tripA->id,
            'voucher_number' => 'EXP-TP-01',
            'expense_date' => now()->toDateString(),
            'expense_category' => 'toll_tax',
            'amount' => 10000.00,
            'payment_method' => 'cash',
            'status' => 'approved',
        ]);

        // 4. Driver Allowance on Trip A: 20,000
        TripExpense::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'trip_id' => $this->tripA->id,
            'voucher_number' => 'EXP-TP-02',
            'expense_date' => now()->toDateString(),
            'expense_category' => 'driver_allowance',
            'amount' => 20000.00,
            'payment_method' => 'cash',
            'status' => 'approved',
        ]);

        $profitability = $reportService->getTripProfitability($this->companyA->id, $this->tripA->id);

        $this->assertEquals(250000.00, $profitability['freight_revenue']);
        $this->assertEquals(70000.00, $profitability['fuel_cost']);
        $this->assertEquals(10000.00, $profitability['tolls_and_expenses']);
        $this->assertEquals(20000.00, $profitability['driver_allowances']);
        $this->assertEquals(100000.00, $profitability['total_direct_costs']);
        $this->assertEquals(150000.00, $profitability['net_voyage_margin']); // 250k - 100k
        $this->assertEquals(60.0, $profitability['margin_percentage']);
        $this->assertTrue($profitability['is_profitable']);
    }

    /**
     * Test 14: Multi-Tenant Financial Isolation.
     */
    public function test_multi_tenant_financial_isolation(): void
    {
        $glService = app(GeneralLedgerService::class);
        $reportService = app(FinancialReportService::class);
        $coaService = app(ChartOfAccountsService::class);

        $cashA = $coaService->getAccountByCode($this->companyA->id, ChartOfAccount::CODE_CASH_IN_HAND);
        $capitalA = $coaService->getAccountByCode($this->companyA->id, ChartOfAccount::CODE_OWNERS_CAPITAL);

        $cashB = $coaService->getAccountByCode($this->companyB->id, ChartOfAccount::CODE_CASH_IN_HAND);
        $capitalB = $coaService->getAccountByCode($this->companyB->id, ChartOfAccount::CODE_OWNERS_CAPITAL);

        // Company A injects 500,000
        $glService->postVoucher([
            'company_id' => $this->companyA->id,
            'voucher_date' => now()->toDateString(),
            'voucher_type' => JournalVoucher::TYPE_OPENING,
            'narration' => 'Company A Capital',
            'items' => [
                ['chart_of_account_id' => $cashA->id, 'debit' => 500000.00, 'credit' => 0.00],
                ['chart_of_account_id' => $capitalA->id, 'debit' => 0.00, 'credit' => 500000.00],
            ],
        ], $this->userA);

        // Company B injects 100,000
        $glService->postVoucher([
            'company_id' => $this->companyB->id,
            'voucher_date' => now()->toDateString(),
            'voucher_type' => JournalVoucher::TYPE_OPENING,
            'narration' => 'Company B Capital',
            'items' => [
                ['chart_of_account_id' => $cashB->id, 'debit' => 100000.00, 'credit' => 0.00],
                ['chart_of_account_id' => $capitalB->id, 'debit' => 0.00, 'credit' => 100000.00],
            ],
        ], $this->userB);

        // Reports must be strictly isolated
        $tbA = $reportService->getTrialBalance($this->companyA->id, now()->toDateString());
        $tbB = $reportService->getTrialBalance($this->companyB->id, now()->toDateString());

        $this->assertEquals(500000.00, $tbA['total_debits']);
        $this->assertEquals(100000.00, $tbB['total_debits']);

        // Cross-tenant voucher retrieval must be rejected
        $vouchersA = JournalVoucher::withoutGlobalScopes()->where('company_id', $this->companyA->id)->first();
        $this->assertNotNull($vouchersA);

        $crossLookup = $glService->getVoucher($vouchersA->id, $this->companyB->id);
        $this->assertNull($crossLookup);
    }

    /**
     * Test 15: Livewire Component Rendering and Operations.
     */
    public function test_livewire_finance_components_render(): void
    {
        $this->actingAs($this->userA);

        Livewire::test(ChartOfAccountsManager::class)
            ->assertStatus(200)
            ->assertSee('Chart of Accounts');

        Livewire::test(GeneralLedgerManager::class)
            ->assertStatus(200)
            ->assertSee('General Ledger');

        Livewire::test(CustomerBillingManager::class)
            ->assertStatus(200)
            ->assertSee('Customer Invoicing');

        Livewire::test(BankManager::class)
            ->assertStatus(200)
            ->assertSee('Cash Management');

        Livewire::test(DriverSettlementManager::class)
            ->assertStatus(200)
            ->assertSee('Driver Voyage Settlements');

        Livewire::test(FinancialReportManager::class)
            ->assertStatus(200)
            ->assertSee('Trial Balance');
    }
}

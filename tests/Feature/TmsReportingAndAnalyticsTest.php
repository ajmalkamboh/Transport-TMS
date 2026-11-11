<?php

namespace Tests\Feature;

use App\Livewire\Reports\ReportCenter;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\CustomerPayment;
use App\Models\Driver;
use App\Models\DriverSettlement;
use App\Models\FuelEntry;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\JobCard;
use App\Models\JobCardItem;
use App\Models\OwnerSettlement;
use App\Models\Role;
use App\Models\StockLedgerEntry;
use App\Models\Trip;
use App\Models\TripExpense;
use App\Models\UnitOfMeasurement;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleOwner;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\BusinessIntelligenceService;
use App\Services\ChartOfAccountsService;
use App\Services\FinancialPeriodService;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TmsReportingAndAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected Company $companyB;
    protected Branch $branchKHI;
    protected Branch $branchLHR;
    protected User $userA;
    protected Vehicle $vehicleA;
    protected Driver $driverA;
    protected Customer $customerA;
    protected VehicleOwner $ownerA;
    protected Warehouse $warehouseKHI;
    protected InventoryItem $filterItem;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create([
            'company_id' => null,
            'name' => 'Tenant Administrator',
            'slug' => 'tenant_admin',
            'description' => 'Administrator for tenant organization',
            'is_system' => true,
        ]);

        // Tenant A
        $this->companyA = Company::create([
            'name' => 'Falcon Express Logistics',
            'legal_name' => 'Falcon Express Logistics Ltd',
            'slug' => 'falcon-express',
            'code' => 'FEL',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->branchKHI = Branch::create([
            'company_id' => $this->companyA->id,
            'name' => 'Karachi Central Depot',
            'code' => 'KHI-01',
            'city' => 'Karachi',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->branchLHR = Branch::create([
            'company_id' => $this->companyA->id,
            'name' => 'Lahore Terminal',
            'code' => 'LHE-01',
            'city' => 'Lahore',
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->userA = User::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchKHI->id,
            'role_id' => $role->id,
            'name' => 'Operations Director',
            'email' => 'ops@falconexpress.pk',
            'password' => bcrypt('Secret123!'),
            'status' => 'active',
            'is_super_admin' => false,
        ]);
        $this->companyA->update(['owner_id' => $this->userA->id]);

        // Tenant B (for isolation tests)
        $this->companyB = Company::create([
            'name' => 'Bismillah Cargo Services',
            'legal_name' => 'Bismillah Cargo Services Pvt Ltd',
            'slug' => 'bismillah-cargo',
            'code' => 'BCS',
            'status' => 'active',
            'is_active' => true,
        ]);

        // Set context & financial setup for Company A
        app(TenantContextService::class)->setTenantContext($this->companyA, $this->branchKHI);
        app(ChartOfAccountsService::class)->seedDefaultChartOfAccounts($this->companyA);
        app(FinancialPeriodService::class)->createFinancialYearWithPeriods(
            company: $this->companyA,
            name: 'FY 2026',
            startDate: now()->startOfYear()->toDateString(),
            endDate: now()->endOfYear()->toDateString(),
            makeCurrent: true
        );

        // Core Operational Fixtures for Company A
        $this->vehicleA = Vehicle::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchKHI->id,
            'registration_number' => 'TLR-7788',
            'fleet_number' => 'FL-778',
            'ownership_type' => Vehicle::OWNERSHIP_COMPANY,
            'vehicle_category' => 'HCV',
            'make' => 'Hino',
            'model' => '500 Series',
            'manufacturing_year' => 2022,
            'chassis_number' => 'CH-778801',
            'engine_number' => 'ENG-778801',
            'payload_capacity_tons' => 40.0,
            'fuel_type' => 'DIESEL',
            'current_odometer_km' => 120000,
            'status' => Vehicle::STATUS_AVAILABLE,
        ]);

        $this->driverA = Driver::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchKHI->id,
            'name' => 'Muhammad Aslam',
            'employee_code' => 'DRV-101',
            'cnic_number' => '42101-1234567-1',
            'phone' => '0300-1122334',
            'license_number' => 'HTV-KHI-9988',
            'license_expiry_date' => now()->addYears(2)->toDateString(),
            'status' => Driver::STATUS_AVAILABLE,
        ]);

        $this->customerA = Customer::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchKHI->id,
            'name' => 'Engro Polymer & Chemicals',
            'code' => 'CUST-ENGRO',
            'phone' => '021-35551122',
            'credit_limit' => 5000000.0,
            'is_active' => true,
        ]);

        $this->ownerA = VehicleOwner::create([
            'company_id' => $this->companyA->id,
            'name' => 'Haji Kareem Khan',
            'code' => 'OWN-KAR',
            'phone' => '0333-7766554',
            'is_active' => true,
        ]);

        $this->warehouseKHI = Warehouse::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchKHI->id,
            'code' => 'WH-KHI-01',
            'name' => 'Karachi Central Store',
            'is_default' => true,
            'is_active' => true,
        ]);

        $uom = UnitOfMeasurement::create([
            'company_id' => $this->companyA->id,
            'name' => 'Pieces',
            'code' => 'PCS',
            'is_active' => true,
        ]);

        $this->filterItem = InventoryItem::create([
            'company_id' => $this->companyA->id,
            'primary_uom_id' => $uom->id,
            'item_code' => 'FLT-FUEL-HD',
            'name' => 'Fuel Water Separator Filter',
            'reorder_level' => 5,
            'safety_stock' => 2,
            'average_cost' => 2500.0,
            'is_active' => true,
        ]);
    }

    /**
     * 1. Test Executive Dashboard Metrics & Calculations.
     */
    public function test_executive_dashboard_kpis_calculation(): void
    {
        $biService = app(BusinessIntelligenceService::class);

        // Create completed trip with freight revenue and expenses
        $trip = Trip::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchKHI->id,
            'trip_number' => 'TRP-KHI-001',
            'trip_date' => now()->toDateString(),
            'vehicle_id' => $this->vehicleA->id,
            'driver_id' => $this->driverA->id,
            'origin_city' => 'Karachi',
            'destination_city' => 'Lahore',
            'status' => Trip::STATUS_COMPLETED,
            'total_freight_amount' => 250000.0,
            'starting_odometer' => 120000,
            'closing_odometer' => 121300,
        ]);

        // En-route trip expense
        TripExpense::create([
            'company_id' => $this->companyA->id,
            'voucher_number' => 'EXP-001',
            'trip_id' => $trip->id,
            'vehicle_id' => $this->vehicleA->id,
            'driver_id' => $this->driverA->id,
            'expense_category' => 'toll_tax',
            'amount' => 15000.0,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'status' => TripExpense::STATUS_APPROVED,
        ]);

        // Fuel entry
        FuelEntry::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchKHI->id,
            'vehicle_id' => $this->vehicleA->id,
            'trip_id' => $trip->id,
            'fuel_station_name' => 'PSO Toll Plaza',
            'fuel_quantity_liters' => 350.0,
            'rate_per_liter' => 280.0,
            'total_cost' => 98000.0,
            'odometer_km' => 121300,
            'distance_km' => 1300.0,
            'entry_date' => now()->toDateString(),
        ]);

        // Customer Invoice with balance due
        CustomerInvoice::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchKHI->id,
            'customer_id' => $this->customerA->id,
            'invoice_number' => 'INV-ENG-001',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => 250000.0,
            'total_amount' => 250000.0,
            'paid_amount' => 100000.0,
            'balance_due' => 150000.0,
            'status' => CustomerInvoice::STATUS_PARTIAL,
        ]);

        // Owner Settlement with balance due
        OwnerSettlement::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchKHI->id,
            'settlement_number' => 'STL-KAR-001',
            'vehicle_owner_id' => $this->ownerA->id,
            'vehicle_id' => $this->vehicleA->id,
            'settlement_date' => now()->toDateString(),
            'agreed_owner_payable' => 180000.0,
            'approved_deductions' => 10000.0,
            'advances' => 20000.0,
            'net_payable' => 150000.0,
            'paid_amount' => 50000.0,
            'balance_due' => 100000.0,
            'status' => OwnerSettlement::STATUS_PARTIALLY_PAID,
        ]);

        $kpis = $biService->getExecutiveDashboardMetrics($this->companyA->id);

        $this->assertEquals(1, $kpis['total_fleet']);
        $this->assertEquals(1, $kpis['active_drivers']);
        $this->assertEquals(1, $kpis['completed_trips']);
        $this->assertEquals(250000.0, $kpis['monthly_freight_revenue']);
        $this->assertEquals(113000.0, $kpis['trip_expenses']); // 15,000 toll + 98,000 fuel
        $this->assertEquals(150000.0, $kpis['outstanding_receivables']);
        $this->assertEquals(100000.0, $kpis['outstanding_owner_payables']);
        $this->assertEquals(137000.0, $kpis['estimated_profit']); // 250,000 - 113,000
    }

    /**
     * 2. Test Fleet Utilization, Running Costs & Fuel Efficiency Reports.
     */
    public function test_fleet_running_costs_and_fuel_efficiency_report(): void
    {
        $biService = app(BusinessIntelligenceService::class);

        // Trip for Vehicle A
        Trip::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchKHI->id,
            'trip_number' => 'TRP-FLT-01',
            'trip_date' => now()->toDateString(),
            'origin_city' => 'Karachi',
            'destination_city' => 'Lahore',
            'vehicle_id' => $this->vehicleA->id,
            'trip_status' => Trip::STATUS_OPERATIONALLY_COMPLETED,
            'total_freight_amount' => 300000.0,
        ]);

        // Fuel refuels: 400 Liters, 1,400 KM, 112,000 PKR
        FuelEntry::create([
            'company_id' => $this->companyA->id,
            'vehicle_id' => $this->vehicleA->id,
            'fuel_quantity_liters' => 400.0,
            'total_cost' => 112000.0,
            'distance_km' => 1400.0,
            'entry_date' => now()->toDateString(),
            'odometer_km' => 121400,
        ]);

        // Maintenance Job Card: 20,000 PKR, 8 downtime hours
        JobCard::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchKHI->id,
            'job_card_number' => 'JC-FLT-01',
            'vehicle_id' => $this->vehicleA->id,
            'scheduled_date' => now()->toDateString(),
            'odometer_km' => 121400,
            'labour_charges' => 5000.0,
            'parts_total_cost' => 15000.0,
            'total_cost' => 20000.0,
            'downtime_hours' => 8.0,
            'status' => JobCard::STATUS_COMPLETED,
        ]);

        $reports = $biService->getFleetReports($this->companyA->id, null, now()->startOfMonth()->toDateString(), now()->toDateString());

        $this->assertCount(1, $reports);
        $veh = $reports[0];

        $this->assertEquals('TLR-7788', $veh['registration_number']);
        $this->assertEquals(1, $veh['trip_count']);
        $this->assertEquals(1400.0, $veh['distance_km']);
        $this->assertEquals(300000.0, $veh['freight_revenue']);
        $this->assertEquals(112000.0, $veh['fuel_cost']);
        $this->assertEquals(20000.0, $veh['maintenance_cost']);
        $this->assertEquals(8.0, $veh['downtime_hours']);
        $this->assertEquals(132000.0, $veh['total_running_cost']);
        $this->assertEquals(168000.0, $veh['net_profit']); // 300k - 132k
        $this->assertEquals(3.5, $veh['fuel_efficiency_kml']); // 1400 km / 400 L = 3.5 km/L
    }

    /**
     * 3. Test Driver Trip History, Advances & Performance Reports.
     */
    public function test_driver_advances_and_settlements_report(): void
    {
        $biService = app(BusinessIntelligenceService::class);

        // Trips for Driver A
        Trip::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchKHI->id,
            'trip_number' => 'TRP-DRV-01',
            'trip_date' => now()->toDateString(),
            'origin_city' => 'Karachi',
            'destination_city' => 'Multan',
            'driver_id' => $this->driverA->id,
            'trip_status' => Trip::STATUS_OPERATIONALLY_COMPLETED,
        ]);

        // Driver Voyage Settlement
        DriverSettlement::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchKHI->id,
            'driver_id' => $this->driverA->id,
            'settlement_number' => 'DS-101-01',
            'settlement_date' => now()->toDateString(),
            'advance_amount' => 25000.0,
            'approved_expenses_amount' => 30000.0,
            'net_balance_amount' => 5000.0, // 30k expense - 25k advance = 5k owed to driver
            'status' => DriverSettlement::STATUS_POSTED,
        ]);

        $reports = $biService->getDriverReports($this->companyA->id);

        $this->assertCount(1, $reports);
        $drv = $reports[0];

        $this->assertEquals('Muhammad Aslam', $drv['name']);
        $this->assertEquals(1, $drv['total_trips']);
        $this->assertEquals(1, $drv['completed_trips']);
        $this->assertEquals(25000.0, $drv['total_advances']);
        $this->assertEquals(30000.0, $drv['approved_expenses']);
        $this->assertEquals(5000.0, $drv['net_settlement_payable']);
    }

    /**
     * 4. Test Customer Aging Analysis (0-30, 31-60, 61-90, 90+ Days).
     */
    public function test_customer_aging_analysis_0_to_90_plus_days(): void
    {
        $biService = app(BusinessIntelligenceService::class);
        $today = now();

        // 1. Current Invoice (10 days old -> 0-30 bucket): 100,000 PKR
        CustomerInvoice::create([
            'company_id' => $this->companyA->id,
            'customer_id' => $this->customerA->id,
            'invoice_number' => 'INV-AGE-10',
            'invoice_date' => $today->copy()->subDays(10)->toDateString(),
            'due_date' => $today->copy()->addDays(20)->toDateString(),
            'total_amount' => 100000.0,
            'paid_amount' => 0.0,
            'balance_due' => 100000.0,
            'status' => CustomerInvoice::STATUS_UNPAID,
        ]);

        // 2. 45 days old invoice (31-60 bucket): 50,000 PKR
        CustomerInvoice::create([
            'company_id' => $this->companyA->id,
            'customer_id' => $this->customerA->id,
            'invoice_number' => 'INV-AGE-45',
            'invoice_date' => $today->copy()->subDays(45)->toDateString(),
            'due_date' => $today->copy()->subDays(15)->toDateString(),
            'total_amount' => 50000.0,
            'paid_amount' => 0.0,
            'balance_due' => 50000.0,
            'status' => CustomerInvoice::STATUS_UNPAID,
        ]);

        // 3. 75 days old invoice (61-90 bucket): 40,000 PKR
        CustomerInvoice::create([
            'company_id' => $this->companyA->id,
            'customer_id' => $this->customerA->id,
            'invoice_number' => 'INV-AGE-75',
            'invoice_date' => $today->copy()->subDays(75)->toDateString(),
            'due_date' => $today->copy()->subDays(45)->toDateString(),
            'total_amount' => 40000.0,
            'paid_amount' => 0.0,
            'balance_due' => 40000.0,
            'status' => CustomerInvoice::STATUS_UNPAID,
        ]);

        // 4. 110 days old invoice (90+ bucket): 30,000 PKR
        CustomerInvoice::create([
            'company_id' => $this->companyA->id,
            'customer_id' => $this->customerA->id,
            'invoice_number' => 'INV-AGE-110',
            'invoice_date' => $today->copy()->subDays(110)->toDateString(),
            'due_date' => $today->copy()->subDays(80)->toDateString(),
            'total_amount' => 30000.0,
            'paid_amount' => 0.0,
            'balance_due' => 30000.0,
            'status' => CustomerInvoice::STATUS_UNPAID,
        ]);

        $agingReports = $biService->getCustomerAgingAndRevenueReport($this->companyA->id, null, $today->toDateString());

        $this->assertCount(1, $agingReports);
        $cust = $agingReports[0];

        $this->assertEquals(220000.0, $cust['total_outstanding']);
        $this->assertEquals(100000.0, $cust['current_0_to_30']);
        $this->assertEquals(50000.0, $cust['days_31_to_60']);
        $this->assertEquals(40000.0, $cust['days_61_to_90']);
        $this->assertEquals(30000.0, $cust['days_90_plus']);
    }

    /**
     * 5. Test Route-Wise Operational Performance Aggregation.
     */
    public function test_route_wise_performance_aggregation(): void
    {
        $biService = app(BusinessIntelligenceService::class);

        // 2 trips on Karachi -> Lahore
        Trip::create([
            'company_id' => $this->companyA->id,
            'trip_number' => 'TRP-RT-01',
            'trip_date' => now()->toDateString(),
            'origin_city' => 'Karachi',
            'destination_city' => 'Lahore',
            'total_freight_amount' => 200000.0,
            'status' => Trip::STATUS_COMPLETED,
        ]);

        Trip::create([
            'company_id' => $this->companyA->id,
            'trip_number' => 'TRP-RT-02',
            'trip_date' => now()->toDateString(),
            'origin_city' => 'Karachi',
            'destination_city' => 'Lahore',
            'total_freight_amount' => 240000.0,
            'status' => Trip::STATUS_COMPLETED,
        ]);

        // 1 trip on Karachi -> Multan
        Trip::create([
            'company_id' => $this->companyA->id,
            'trip_number' => 'TRP-RT-03',
            'trip_date' => now()->toDateString(),
            'origin_city' => 'Karachi',
            'destination_city' => 'Multan',
            'total_freight_amount' => 160000.0,
            'status' => Trip::STATUS_COMPLETED,
        ]);

        $routes = $biService->getRoutePerformanceSummary($this->companyA->id);

        $this->assertCount(2, $routes);
        $this->assertEquals('Karachi -> Lahore', $routes[0]['route']);
        $this->assertEquals(2, $routes[0]['total_trips']);
        $this->assertEquals(440000.0, $routes[0]['total_freight']);
        $this->assertEquals(220000.0, $routes[0]['avg_freight_per_trip']);

        $this->assertEquals('Karachi -> Multan', $routes[1]['route']);
        $this->assertEquals(1, $routes[1]['total_trips']);
        $this->assertEquals(160000.0, $routes[1]['total_freight']);
    }

    /**
     * 6. Test Vehicle Owner Settlements & Profitability Report.
     */
    public function test_vehicle_owner_settlements_report(): void
    {
        $biService = app(BusinessIntelligenceService::class);

        OwnerSettlement::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchKHI->id,
            'vehicle_owner_id' => $this->ownerA->id,
            'vehicle_id' => $this->vehicleA->id,
            'settlement_number' => 'STL-002',
            'settlement_date' => now()->toDateString(),
            'agreed_owner_payable' => 120000.0,
            'approved_deductions' => 8000.0,
            'advances' => 15000.0,
            'net_payable' => 97000.0,
            'paid_amount' => 50000.0,
            'balance_due' => 47000.0,
            'status' => OwnerSettlement::STATUS_PARTIALLY_PAID,
        ]);

        $reports = $biService->getVehicleOwnerReports($this->companyA->id);

        $this->assertCount(1, $reports);
        $ow = $reports[0];

        $this->assertEquals('Haji Kareem Khan', $ow['name']);
        $this->assertEquals(120000.0, $ow['agreed_freight']);
        $this->assertEquals(8000.0, $ow['deductions']);
        $this->assertEquals(15000.0, $ow['advances']);
        $this->assertEquals(97000.0, $ow['net_payable']);
        $this->assertEquals(50000.0, $ow['paid_amount']);
        $this->assertEquals(47000.0, $ow['pending_balance']);
    }

    /**
     * 7. Test Statement of Cash Flows (Operating Inflows and Outflows).
     */
    public function test_statement_of_cash_flows(): void
    {
        $biService = app(BusinessIntelligenceService::class);

        // Inflow: Customer payment 300,000 PKR
        CustomerPayment::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchKHI->id,
            'customer_id' => $this->customerA->id,
            'receipt_number' => 'RCP-CF-01',
            'payment_date' => now()->toDateString(),
            'amount' => 300000.0,
            'payment_method' => 'bank_transfer',
        ]);

        // Outflow: Fuel refuel 80,000 PKR
        FuelEntry::create([
            'company_id' => $this->companyA->id,
            'vehicle_id' => $this->vehicleA->id,
            'fuel_quantity_liters' => 300.0,
            'total_cost' => 80000.0,
            'entry_date' => now()->toDateString(),
            'odometer_km' => 120500,
        ]);

        // Outflow: En-route Trip expense 20,000 PKR
        TripExpense::create([
            'company_id' => $this->companyA->id,
            'expense_category' => 'toll_tax',
            'amount' => 20000.0,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'status' => TripExpense::STATUS_APPROVED,
        ]);

        $cf = $biService->getCashFlowStatement($this->companyA->id);

        $this->assertEquals(300000.0, $cf['inflows']['customer_collections']);
        $this->assertEquals(300000.0, $cf['inflows']['total_inflows']);
        $this->assertEquals(80000.0, $cf['outflows']['diesel_fuel_payments']);
        $this->assertEquals(20000.0, $cf['outflows']['trip_en_route_expenses']);
        $this->assertEquals(100000.0, $cf['outflows']['total_outflows']);
        $this->assertEquals(200000.0, $cf['net_cash_flow']); // 300k - 100k
    }

    /**
     * 8. Test Inventory Stock Valuation & Spare Parts Maintenance Consumption Reports.
     */
    public function test_inventory_valuation_and_spares_consumption_report(): void
    {
        $biService = app(BusinessIntelligenceService::class);

        // Put 10 filters into warehouse @ 2,500 PKR = 25,000 PKR
        WarehouseStock::create([
            'company_id' => $this->companyA->id,
            'warehouse_id' => $this->warehouseKHI->id,
            'inventory_item_id' => $this->filterItem->id,
            'quantity_on_hand' => 10.0,
            'quantity_allocated' => 0.0,
            'average_unit_cost' => 2500.0,
            'total_valuation' => 25000.0,
        ]);

        // Spare parts issued to Job Card
        $jobCard = JobCard::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchKHI->id,
            'job_card_number' => 'JC-REP-01',
            'vehicle_id' => $this->vehicleA->id,
            'odometer_km' => 121000,
            'scheduled_date' => now()->toDateString(),
            'total_cost' => 5000.0,
            'status' => JobCard::STATUS_COMPLETED,
        ]);

        JobCardItem::create([
            'company_id' => $this->companyA->id,
            'job_card_id' => $jobCard->id,
            'inventory_item_id' => $this->filterItem->id,
            'part_name' => $this->filterItem->name,
            'part_number' => $this->filterItem->item_code,
            'quantity' => 2.0,
            'unit_cost' => 2500.0,
            'total_cost' => 5000.0,
            'is_inventory_item' => true,
        ]);

        $invReports = $biService->getInventoryReports($this->companyA->id);

        $this->assertEquals(25000.0, $invReports['total_stock_valuation']);
        $this->assertCount(1, $invReports['summary']);
        $this->assertEquals('FLT-FUEL-HD', $invReports['summary'][0]['item_code']);

        $this->assertCount(1, $invReports['parts_consumption']);
        $this->assertEquals('JC-REP-01', $invReports['parts_consumption'][0]['job_card_number']);
        $this->assertEquals(5000.0, (float) $invReports['parts_consumption'][0]['total_cost']);
    }

    /**
     * 9. Test Multi-Tenant & Multi-Branch Permission Isolation in Reporting.
     */
    public function test_tenant_and_branch_isolation_in_reporting(): void
    {
        $biService = app(BusinessIntelligenceService::class);

        // Vehicle in Company B
        Vehicle::create([
            'company_id' => $this->companyB->id,
            'registration_number' => 'BCS-9999',
            'fleet_number' => 'B-99',
            'ownership_type' => Vehicle::OWNERSHIP_COMPANY,
            'vehicle_category' => 'HCV',
            'make' => 'Master',
            'model' => 'Foton',
            'status' => Vehicle::STATUS_AVAILABLE,
        ]);

        // Metrics for Company A must NOT include Company B's vehicle
        $metricsA = $biService->getExecutiveDashboardMetrics($this->companyA->id);
        $this->assertEquals(1, $metricsA['total_fleet']);

        $metricsB = $biService->getExecutiveDashboardMetrics($this->companyB->id);
        $this->assertEquals(1, $metricsB['total_fleet']);

        // Branch Isolation: Vehicle in Lahore branch of Company A
        Vehicle::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchLHR->id,
            'registration_number' => 'LHR-5555',
            'fleet_number' => 'FL-555',
            'ownership_type' => Vehicle::OWNERSHIP_COMPANY,
            'vehicle_category' => 'MCV',
            'make' => 'Isuzu',
            'model' => 'Forward',
            'status' => Vehicle::STATUS_AVAILABLE,
        ]);

        // Company A overall has 2 vehicles
        $this->assertEquals(2, $biService->getExecutiveDashboardMetrics($this->companyA->id)['total_fleet']);

        // Karachi branch has only 1 vehicle
        $this->assertEquals(1, $biService->getExecutiveDashboardMetrics($this->companyA->id, $this->branchKHI->id)['total_fleet']);

        // Lahore branch has only 1 vehicle
        $this->assertEquals(1, $biService->getExecutiveDashboardMetrics($this->companyA->id, $this->branchLHR->id)['total_fleet']);
    }

    /**
     * 10. Test Livewire 3 Report Center & CSV Streaming Exports.
     */
    public function test_livewire_report_center_and_export_routes(): void
    {
        $this->actingAs($this->userA);

        // Test Livewire component initialization and tab switching
        Livewire::test(ReportCenter::class)
            ->assertStatus(200)
            ->assertSee('TMS Reports')
            ->set('activeTab', 'fleet')
            ->assertSee('Reg # / Fleet #')
            ->set('activeTab', 'customer')
            ->assertSee('Customer Aging (0-90+)')
            ->call('setPreset', 'this_month')
            ->assertHasNoErrors();

        // Test CSV Export Route
        $response = $this->get(route('reports.export', ['tab' => 'fleet']));
        $response->assertStatus(200);
        $this->assertEquals('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));

        // Test Printable Report Route
        $printResponse = $this->get(route('reports.print', ['tab' => 'executive']));
        $printResponse->assertStatus(200);
        $printResponse->assertSee('Executive Operational & Financial Summary');
    }
}


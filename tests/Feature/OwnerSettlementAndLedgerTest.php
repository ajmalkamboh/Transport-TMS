<?php

namespace Tests\Feature;

use App\Livewire\Finance\OwnerSettlementManager;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Consignment;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\FuelEntry;
use App\Models\FuelSupplier;
use App\Models\OwnerContract;
use App\Models\OwnerLedger;
use App\Models\OwnerPayment;
use App\Models\OwnerSettlement;
use App\Models\OwnerSettlementDeduction;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\Trip;
use App\Models\TripConsignment;
use App\Models\TripExpense;
use App\Models\TripResource;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleOwner;
use App\Services\AccountingIntegrationService;
use App\Services\OwnerLedgerService;
use App\Services\OwnerSettlementService;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class OwnerSettlementAndLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected Company $companyB;
    protected User $ownerA;
    protected User $ownerB;
    protected Branch $branchA;
    protected Branch $branchB;
    protected VehicleOwner $truckOwnerA;
    protected VehicleOwner $truckOwnerB;
    protected OwnerContract $contractFixed;
    protected OwnerContract $contractPercent;
    protected OwnerContract $contractPerKm;
    protected Vehicle $vehicleAttachedA;
    protected Driver $driverA;
    protected Trip $tripA1;
    protected Customer $customerA;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create([
            'company_id' => null,
            'name' => 'Tenant Administrator',
            'slug' => 'tenant_admin',
            'description' => 'Administrator for tenant organization',
            'is_system' => true,
        ]);

        $plan = SubscriptionPlan::create([
            'name' => 'Enterprise Logistics',
            'slug' => 'enterprise-plan',
            'price_monthly' => 20000.00,
            'price_yearly' => 200000.00,
            'max_vehicles' => 100,
            'max_users' => 50,
            'max_branches' => 10,
            'features' => ['fleet_management', 'fuel_management', 'trip_management', 'owner_settlements'],
            'is_active' => true,
        ]);

        $this->companyA = Company::create([
            'name' => 'Al-Makkah Goods Transport Co.',
            'code' => 'MGT',
            'slug' => 'makkah-goods',
            'email' => 'finance@makkahgoods.com',
            'phone' => '+92-42-3770011',
            'status' => 'active',
            'plan_id' => $plan->id,
            'onboarding_completed_at' => now(),
            'onboarding_step' => 6,
        ]);

        $this->branchA = Branch::create([
            'company_id' => $this->companyA->id,
            'code' => 'MGT-LHR',
            'name' => 'Lahore Logistics Hub',
            'city' => 'Lahore',
            'is_head_office' => true,
            'is_default' => true,
            'is_active' => true,
        ]);

        $adminRole = Role::where('slug', 'tenant_admin')->first();
        $this->ownerA = User::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'role_id' => $adminRole->id,
            'name' => 'Haji Rasheed Khan',
            'email' => 'rasheed@makkahgoods.com',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
            'is_super_admin' => false,
        ]);
        $this->companyA->update(['owner_id' => $this->ownerA->id]);

        // Tenant B for isolation checks
        $this->companyB = Company::create([
            'name' => 'Khyber Freight Carriers',
            'code' => 'KFC',
            'slug' => 'khyber-freight',
            'email' => 'ops@khyberfreight.com',
            'phone' => '+92-91-580022',
            'status' => 'active',
            'plan_id' => $plan->id,
            'onboarding_completed_at' => now(),
            'onboarding_step' => 6,
        ]);

        $this->branchB = Branch::create([
            'company_id' => $this->companyB->id,
            'code' => 'KFC-PEW',
            'name' => 'Peshawar Terminal',
            'city' => 'Peshawar',
            'is_head_office' => true,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->ownerB = User::create([
            'company_id' => $this->companyB->id,
            'branch_id' => $this->branchB->id,
            'role_id' => $adminRole->id,
            'name' => 'Gulzar Afridi',
            'email' => 'gulzar@khyberfreight.com',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
            'is_super_admin' => false,
        ]);
        $this->companyB->update(['owner_id' => $this->ownerB->id]);

        // 3rd-Party Vehicle Owner for Company A
        $this->truckOwnerA = VehicleOwner::create([
            'company_id' => $this->companyA->id,
            'name' => 'Chaudhry Akram Gujjar',
            'company_name' => 'Gujjar Transport Service',
            'cnic_number' => '35202-9876543-1',
            'phone' => '+92-300-4455667',
            'email' => 'akram@gujjartransport.com',
            'bank_name' => 'Habib Bank Limited',
            'bank_account_title' => 'Chaudhry Akram Gujjar',
            'bank_account_number' => 'PK36HABB00012345678901',
            'status' => 'active',
        ]);

        // 3rd-Party Vehicle Owner for Company B
        $this->truckOwnerB = VehicleOwner::create([
            'company_id' => $this->companyB->id,
            'name' => 'Sardar Wali Khan',
            'company_name' => 'Wali Brothers Haulage',
            'cnic_number' => '17301-1122334-5',
            'phone' => '+92-333-9988776',
            'status' => 'active',
        ]);

        // Commercial Contracts for Owner A
        $this->contractFixed = OwnerContract::create([
            'company_id' => $this->companyA->id,
            'vehicle_owner_id' => $this->truckOwnerA->id,
            'contract_number' => 'CONTR-FIXED-01',
            'payment_model' => OwnerContract::MODEL_FIXED_PER_TRIP,
            'rate_amount' => 45000.00,
            'freight_percentage' => 0.00,
            'deduct_fuel' => true,
            'deduct_advances' => true,
            'start_date' => now()->subMonths(2)->toDateString(),
            'status' => 'active',
        ]);

        $this->contractPercent = OwnerContract::create([
            'company_id' => $this->companyA->id,
            'vehicle_owner_id' => $this->truckOwnerA->id,
            'contract_number' => 'CONTR-PERCENT-02',
            'payment_model' => OwnerContract::MODEL_PERCENTAGE_OF_FREIGHT,
            'rate_amount' => 0.00,
            'freight_percentage' => 85.00, // 85% to owner, 15% company cut
            'commission_percentage' => 15.00,
            'deduct_fuel' => true,
            'deduct_advances' => true,
            'start_date' => now()->subMonths(1)->toDateString(),
            'status' => 'active',
        ]);

        $this->contractPerKm = OwnerContract::create([
            'company_id' => $this->companyA->id,
            'vehicle_owner_id' => $this->truckOwnerA->id,
            'contract_number' => 'CONTR-PERKM-03',
            'payment_model' => OwnerContract::MODEL_PER_KM,
            'rate_amount' => 125.00, // PKR 125 / KM
            'deduct_fuel' => true,
            'deduct_advances' => true,
            'start_date' => now()->subMonths(1)->toDateString(),
            'status' => 'active',
        ]);

        // Attached Vehicle
        $this->vehicleAttachedA = Vehicle::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'vehicle_owner_id' => $this->truckOwnerA->id,
            'active_contract_id' => $this->contractFixed->id,
            'registration_number' => 'LES-7788',
            'fleet_number' => 'MGT-ATT-01',
            'ownership_type' => Vehicle::OWNERSHIP_ATTACHED,
            'vehicle_category' => 'heavy_commercial',
            'vehicle_type' => '10_wheeler_truck',
            'make' => 'Hino',
            'model' => '500 Series',
            'manufacturing_year' => 2021,
            'chassis_number' => 'CH-LES7788',
            'engine_number' => 'ENG-LES7788',
            'payload_capacity_tons' => 25.00,
            'current_odometer_km' => 120000,
            'status' => Vehicle::STATUS_AVAILABLE,
        ]);

        $this->driverA = Driver::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'name' => 'Ghulam Dastagir',
            'cnic_number' => '35201-1234567-8',
            'license_number' => 'LHR-HTV-9988',
            'license_category' => 'HTV',
            'license_expiry_date' => now()->addYears(2),
            'phone' => '+92-301-7788990',
            'status' => 'available',
        ]);

        $this->customerA = Customer::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'name' => 'Millat Industrial Corp',
            'company_name' => 'Millat Industries Ltd',
            'phone' => '+92-42-35889911',
            'status' => 'active',
            'billing_address' => 'Industrial Area, Kot Lakhpat, Lahore',
        ]);

        // Completed Trip with Consignments
        $this->tripA1 = Trip::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'trip_number' => 'TR-2026-00088',
            'trip_date' => now()->subDays(2)->toDateString(),
            'origin_city' => 'Lahore',
            'destination_city' => 'Karachi',
            'trip_status' => Trip::STATUS_OPERATIONALLY_COMPLETED,
            'financial_status' => Trip::FINANCIAL_PENDING,
            'departure_odometer_km' => 120000,
            'arrival_odometer_km' => 121250,
            'total_distance_km' => 1250,
            'operationally_completed_at' => now()->subDay(),
        ]);

        TripResource::create([
            'company_id' => $this->companyA->id,
            'trip_id' => $this->tripA1->id,
            'resource_type' => 'vehicle',
            'vehicle_id' => $this->vehicleAttachedA->id,
            'role' => 'primary_vehicle',
            'assigned_at' => now()->subDays(2),
            'is_active' => true,
        ]);

        TripResource::create([
            'company_id' => $this->companyA->id,
            'trip_id' => $this->tripA1->id,
            'resource_type' => 'driver',
            'driver_id' => $this->driverA->id,
            'role' => 'primary_driver',
            'assigned_at' => now()->subDays(2),
            'is_active' => true,
        ]);

        // Customer Booking and Consignment with Freight of PKR 100,000
        $booking = Booking::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'customer_id' => $this->customerA->id,
            'booking_number' => 'BK-2026-00099',
            'booking_date' => now()->subDays(2)->toDateString(),
            'origin_city' => 'Lahore',
            'destination_city' => 'Karachi',
            'status' => 'confirmed',
        ]);

        $consignment = Consignment::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'booking_id' => $booking->id,
            'customer_id' => $this->customerA->id,
            'consignment_number' => 'LR-2026-00099',
            'consignment_date' => now()->subDays(2)->toDateString(),
            'consignee_name' => 'Karachi Textile Impex',
            'consignee_phone' => '+92-21-34567890',
            'consignee_city' => 'Karachi',
            'cargo_description' => 'Cotton Yarn Cones',
            'packaging_type' => 'cartons',
            'total_quantity' => 500,
            'dispatched_quantity' => 500,
            'delivered_quantity' => 500,
            'remaining_quantity' => 0,
            'chargeable_weight_kg' => 20000.00,
            'freight_rate' => 5.00,
            'freight_charge' => 100000.00,
            'total_charges' => 100000.00,
            'status' => 'delivered',
            'payment_mode' => 'to_pay',
        ]);

        TripConsignment::create([
            'company_id' => $this->companyA->id,
            'trip_id' => $this->tripA1->id,
            'consignment_id' => $consignment->id,
            'assigned_quantity' => 500,
            'weight_kg' => 20000.00,
            'loading_status' => 'loaded',
            'delivered_quantity' => 500,
            'delivery_status' => 'delivered_full',
        ]);

        $this->tripA1->refreshCumulativeTotals();
    }

    /**
     * Test 1: Contract-Based Payable Calculation (Fixed Per Trip).
     */
    public function test_owner_payable_calculation_fixed_per_trip(): void
    {
        $service = app(OwnerSettlementService::class);
        $calc = $service->calculateOwnerPayable($this->tripA1, $this->contractFixed);

        $this->assertEquals(OwnerSettlement::MODEL_FIXED_PER_TRIP, $calc['payment_model']);
        $this->assertEquals(45000.00, $calc['agreed_owner_payable']);
        $this->assertEquals(100000.00, $calc['customer_freight_total']);
        $this->assertNotEmpty($calc['contract_snapshot']);
        $this->assertEquals('CONTR-FIXED-01', $calc['contract_snapshot']['contract_number']);
        $this->assertStringContainsString('Fixed Rate Per Trip', $calc['calculation_details']['formula']);
    }

    /**
     * Test 2: Contract-Based Payable Calculation (Percentage of Freight).
     */
    public function test_owner_payable_calculation_percentage_of_freight(): void
    {
        $service = app(OwnerSettlementService::class);
        $calc = $service->calculateOwnerPayable($this->tripA1, $this->contractPercent);

        // 85% of PKR 100,000 = PKR 85,000
        $this->assertEquals(OwnerSettlement::MODEL_PERCENTAGE_OF_FREIGHT, $calc['payment_model']);
        $this->assertEquals(85000.00, $calc['agreed_owner_payable']);
        $this->assertEquals(100000.00, $calc['customer_freight_total']);
        $this->assertStringContainsString('85%', $calc['calculation_details']['formula']);
    }

    /**
     * Test 3: Contract-Based Payable Calculation (Per Kilometre).
     */
    public function test_owner_payable_calculation_per_km(): void
    {
        $service = app(OwnerSettlementService::class);
        $calc = $service->calculateOwnerPayable($this->tripA1, $this->contractPerKm);

        // 1250 KM * 125 PKR/KM = 156,250 PKR
        $this->assertEquals(OwnerSettlement::MODEL_PER_KM, $calc['payment_model']);
        $this->assertEquals(156250.00, $calc['agreed_owner_payable']);
        $this->assertEquals(1250, $calc['calculation_details']['distance_km']);
        $this->assertStringContainsString('1250 KM', $calc['calculation_details']['formula']);
    }

    /**
     * Test 4: Special Negotiated Rate override per trip.
     */
    public function test_owner_payable_calculation_negotiated_override(): void
    {
        $service = app(OwnerSettlementService::class);
        $calc = $service->calculateOwnerPayable($this->tripA1, $this->contractFixed, [
            'negotiated_rate' => 52000.00,
        ]);

        $this->assertEquals(OwnerSettlement::MODEL_NEGOTIATED, $calc['payment_model']);
        $this->assertEquals(52000.00, $calc['agreed_owner_payable']);
        $this->assertStringContainsString('Special Negotiated Rate', $calc['calculation_details']['formula']);
    }

    /**
     * Test 5: Detect pending deductions for company fuel and trip advances.
     */
    public function test_detect_pending_deductions_fuel_and_advances(): void
    {
        $supplier = FuelSupplier::create([
            'company_id' => $this->companyA->id,
            'name' => 'Pakistan State Oil',
            'contact_person' => 'Tariq Mehmood',
            'phone' => '+92-42-37778899',
            'status' => 'active',
        ]);

        // 1. Company paid fuel for attached truck: 100L @ 280 = 28,000
        $fuel = FuelEntry::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'vehicle_id' => $this->vehicleAttachedA->id,
            'trip_id' => $this->tripA1->id,
            'fuel_supplier_id' => $supplier->id,
            'entry_number' => 'FL-2026-00010',
            'entry_date' => now()->subDays(2)->toDateString(),
            'fuel_type' => 'diesel',
            'quantity_liters' => 100.00,
            'rate_per_liter' => 280.00,
            'total_amount' => 28000.00,
            'odometer_km' => 120500,
            'payment_method' => 'credit',
        ]);

        // 2. Company gave cash advance to driver/truck for voyage: 5,000
        $advance = TripExpense::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'trip_id' => $this->tripA1->id,
            'vehicle_id' => $this->vehicleAttachedA->id,
            'voucher_number' => 'EXP-2026-00055',
            'expense_category' => 'driver_allowance',
            'expense_date' => now()->subDays(2)->toDateString(),
            'amount' => 5000.00,
            'payment_method' => 'cash',
            'status' => TripExpense::STATUS_APPROVED,
        ]);

        $service = app(OwnerSettlementService::class);
        $deductions = $service->detectPendingDeductions($this->tripA1);

        $this->assertCount(2, $deductions);
        $this->assertTrue(collect($deductions)->contains('deduction_type', OwnerSettlementDeduction::TYPE_FUEL));
        $this->assertTrue(collect($deductions)->contains('deduction_type', OwnerSettlementDeduction::TYPE_ADVANCE));
        $this->assertEquals(33000.00, collect($deductions)->sum('amount'));
    }

    /**
     * Test 6: Settlement creation with deductions and net payable calculation.
     */
    public function test_settlement_creation_with_deductions_and_net_payable(): void
    {
        $service = app(OwnerSettlementService::class);

        $calc = $service->calculateOwnerPayable($this->tripA1, $this->contractFixed);

        $settlement = $service->createSettlement([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'vehicle_owner_id' => $this->truckOwnerA->id,
            'vehicle_id' => $this->vehicleAttachedA->id,
            'trip_id' => $this->tripA1->id,
            'owner_contract_id' => $this->contractFixed->id,
            'settlement_date' => now()->toDateString(),
            'payment_model' => $calc['payment_model'],
            'customer_freight_total' => $calc['customer_freight_total'],
            'agreed_owner_payable' => 50000.00,
            'additional_charges' => 2000.00, // Demurrage bonus
            'contract_snapshot' => $calc['contract_snapshot'],
            'calculation_details' => $calc['calculation_details'],
            'deductions' => [
                [
                    'deduction_type' => OwnerSettlementDeduction::TYPE_FUEL,
                    'amount' => 20000.00,
                    'reference_type' => FuelEntry::class,
                    'reference_id' => 9991,
                    'description' => 'Diesel refuel slip',
                ],
                [
                    'deduction_type' => OwnerSettlementDeduction::TYPE_ADVANCE,
                    'amount' => 5000.00,
                    'reference_type' => TripExpense::class,
                    'reference_id' => 9992,
                    'description' => 'Cash advance recovery',
                ],
            ],
        ], $this->ownerA);

        $this->assertEquals(OwnerSettlement::STATUS_DRAFT, $settlement->status);
        $this->assertEquals(50000.00, (float) $settlement->agreed_owner_payable);
        $this->assertEquals(25000.00, (float) $settlement->total_deductions);
        $this->assertEquals(2000.00, (float) $settlement->additional_charges);

        // Net Payable = 50,000 - 25,000 + 2,000 = 27,000
        $this->assertEquals(27000.00, (float) $settlement->net_payable);
        $this->assertEquals(27000.00, (float) $settlement->balance_due);
        $this->assertCount(2, $settlement->deductionItems);
    }

    /**
     * Test 7: Prevent duplicate deduction of the same fuel slip or expense.
     */
    public function test_prevent_duplicate_deduction_of_fuel_or_expenses(): void
    {
        $service = app(OwnerSettlementService::class);

        // 1. Create first settlement deducting fuel slip #777
        $service->createSettlement([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'vehicle_owner_id' => $this->truckOwnerA->id,
            'vehicle_id' => $this->vehicleAttachedA->id,
            'trip_id' => $this->tripA1->id,
            'agreed_owner_payable' => 40000.00,
            'deductions' => [
                [
                    'deduction_type' => OwnerSettlementDeduction::TYPE_FUEL,
                    'amount' => 15000.00,
                    'reference_type' => FuelEntry::class,
                    'reference_id' => 777,
                    'description' => 'Unique Fuel Slip #777',
                ],
            ],
        ], $this->ownerA);

        // 2. Second trip
        $trip2 = Trip::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'trip_number' => 'TR-2026-00089',
            'trip_date' => now()->toDateString(),
            'origin_city' => 'Lahore',
            'destination_city' => 'Multan',
            'trip_status' => Trip::STATUS_OPERATIONALLY_COMPLETED,
        ]);

        // Attempt to deduct fuel slip #777 again in trip2 -> must throw LogicException
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/already been deducted/i');

        $service->createSettlement([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'vehicle_owner_id' => $this->truckOwnerA->id,
            'vehicle_id' => $this->vehicleAttachedA->id,
            'trip_id' => $trip2->id,
            'agreed_owner_payable' => 30000.00,
            'deductions' => [
                [
                    'deduction_type' => OwnerSettlementDeduction::TYPE_FUEL,
                    'amount' => 15000.00,
                    'reference_type' => FuelEntry::class,
                    'reference_id' => 777,
                    'description' => 'Attempting duplicate fuel deduction',
                ],
            ],
        ], $this->ownerA);
    }

    /**
     * Test 8: Prevent duplicate active settlement for the same trip.
     */
    public function test_prevent_duplicate_settlement_for_same_trip(): void
    {
        $service = app(OwnerSettlementService::class);

        $service->createSettlement([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'vehicle_owner_id' => $this->truckOwnerA->id,
            'vehicle_id' => $this->vehicleAttachedA->id,
            'trip_id' => $this->tripA1->id,
            'agreed_owner_payable' => 45000.00,
        ], $this->ownerA);

        // Attempt second settlement for same trip -> must throw LogicException
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/An active settlement .* already exists for this trip/i');

        $service->createSettlement([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'vehicle_owner_id' => $this->truckOwnerA->id,
            'vehicle_id' => $this->vehicleAttachedA->id,
            'trip_id' => $this->tripA1->id,
            'agreed_owner_payable' => 45000.00,
        ], $this->ownerA);
    }

    /**
     * Test 9: Settlement approval and posting to owner ledger with correct balance.
     */
    public function test_settlement_approval_and_posting_to_owner_ledger(): void
    {
        $service = app(OwnerSettlementService::class);

        $settlement = $service->createSettlement([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'vehicle_owner_id' => $this->truckOwnerA->id,
            'vehicle_id' => $this->vehicleAttachedA->id,
            'trip_id' => $this->tripA1->id,
            'settlement_date' => now()->toDateString(),
            'agreed_owner_payable' => 50000.00,
            'additional_charges' => 5000.00,
            'deductions' => [
                [
                    'deduction_type' => OwnerSettlementDeduction::TYPE_FUEL,
                    'amount' => 10000.00,
                    'description' => 'Company Diesel Deduction',
                ],
            ],
        ], $this->ownerA);

        // 1. Submit
        $settlement = $service->submitSettlement($settlement, $this->ownerA);
        $this->assertEquals(OwnerSettlement::STATUS_SUBMITTED, $settlement->status);

        // 2. Approve
        $settlement = $service->approveSettlement($settlement, $this->ownerA);
        $this->assertEquals(OwnerSettlement::STATUS_APPROVED, $settlement->status);
        $this->assertNotNull($settlement->approved_at);

        // 3. Post to Owner Ledger
        $settlement = $service->postSettlement($settlement, $this->ownerA);
        $this->assertEquals(OwnerSettlement::STATUS_POSTED, $settlement->status);
        $this->assertNotNull($settlement->posted_at);

        // Verify Trip marked settled
        $this->assertEquals(Trip::FINANCIAL_SETTLED, $this->tripA1->fresh()->financial_status);

        // Verify Ledger Entries
        // Expected:
        // +50,000 Credit (Trip Payable) -> Balance: 50,000
        // -10,000 Debit (Fuel Deduction) -> Balance: 40,000
        // +5,000 Credit (Additional Charges) -> Balance: 45,000
        $ledgerEntries = OwnerLedger::where('company_id', $this->companyA->id)
            ->where('vehicle_owner_id', $this->truckOwnerA->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(3, $ledgerEntries);
        $this->assertEquals(45000.00, (float) $ledgerEntries->last()->running_balance);
        $this->assertEquals(45000.00, $this->truckOwnerA->getCurrentBalance());
    }

    /**
     * Test 10: Prevent silent edit or re-posting of already posted settlement.
     */
    public function test_prevent_silent_edit_or_reposting_of_posted_settlement(): void
    {
        $service = app(OwnerSettlementService::class);

        $settlement = $service->createSettlement([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'vehicle_owner_id' => $this->truckOwnerA->id,
            'vehicle_id' => $this->vehicleAttachedA->id,
            'trip_id' => $this->tripA1->id,
            'agreed_owner_payable' => 40000.00,
        ], $this->ownerA);

        $service->submitSettlement($settlement, $this->ownerA);
        $service->approveSettlement($settlement, $this->ownerA);
        $service->postSettlement($settlement, $this->ownerA);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/already posted/i');

        // Attempting to post again must be blocked
        $service->postSettlement($settlement, $this->ownerA);
    }

    /**
     * Test 11: Reversal of posted settlement creates compensating ledger entries.
     */
    public function test_reversal_of_posted_settlement_creates_counter_entries(): void
    {
        $service = app(OwnerSettlementService::class);

        $settlement = $service->createSettlement([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'vehicle_owner_id' => $this->truckOwnerA->id,
            'vehicle_id' => $this->vehicleAttachedA->id,
            'trip_id' => $this->tripA1->id,
            'agreed_owner_payable' => 60000.00,
            'deductions' => [
                [
                    'deduction_type' => OwnerSettlementDeduction::TYPE_ADVANCE,
                    'amount' => 10000.00,
                    'reference_type' => TripExpense::class,
                    'reference_id' => 8881,
                    'description' => 'Advance to be reversed',
                ],
            ],
        ], $this->ownerA);

        $service->submitSettlement($settlement, $this->ownerA);
        $service->approveSettlement($settlement, $this->ownerA);
        $service->postSettlement($settlement, $this->ownerA);

        // Ledger balance was 60,000 - 10,000 = 50,000
        $this->assertEquals(50000.00, $this->truckOwnerA->getCurrentBalance());

        // Reverse the settlement
        $reversed = $service->reverseSettlement($settlement, 'Dispute on freight rate resolved, reissuing', $this->ownerA);

        $this->assertEquals(OwnerSettlement::STATUS_REVERSED, $reversed->status);
        $this->assertEquals('Dispute on freight rate resolved, reissuing', $reversed->reversal_reason);
        $this->assertEquals(Trip::FINANCIAL_PENDING, $this->tripA1->fresh()->financial_status);

        // Balance should return to 0.00
        $this->assertEquals(0.00, $this->truckOwnerA->getCurrentBalance());

        // The advance deduction item is now released; another settlement can be created without duplicate error
        $newSettlement = $service->createSettlement([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'vehicle_owner_id' => $this->truckOwnerA->id,
            'vehicle_id' => $this->vehicleAttachedA->id,
            'trip_id' => $this->tripA1->id,
            'agreed_owner_payable' => 58000.00,
            'deductions' => [
                [
                    'deduction_type' => OwnerSettlementDeduction::TYPE_ADVANCE,
                    'amount' => 10000.00,
                    'reference_type' => TripExpense::class,
                    'reference_id' => 8881,
                    'description' => 'Advance re-applied',
                ],
            ],
        ], $this->ownerA);

        $this->assertNotNull($newSettlement);
    }

    /**
     * Test 12: Partial and full owner payments with balance update.
     */
    public function test_partial_and_full_owner_payments(): void
    {
        $service = app(OwnerSettlementService::class);

        $settlement = $service->createSettlement([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'vehicle_owner_id' => $this->truckOwnerA->id,
            'vehicle_id' => $this->vehicleAttachedA->id,
            'trip_id' => $this->tripA1->id,
            'agreed_owner_payable' => 80000.00,
        ], $this->ownerA);

        $service->submitSettlement($settlement, $this->ownerA);
        $service->approveSettlement($settlement, $this->ownerA);
        $service->postSettlement($settlement, $this->ownerA);

        $this->assertEquals(80000.00, (float) $settlement->balance_due);

        // 1. Partial Payment of PKR 50,000
        $payment1 = $service->recordOwnerPayment([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'vehicle_owner_id' => $this->truckOwnerA->id,
            'owner_settlement_id' => $settlement->id,
            'payment_date' => now()->toDateString(),
            'amount' => 50000.00,
            'payment_method' => OwnerPayment::METHOD_BANK_TRANSFER,
            'payment_reference' => 'IBFT-77112233',
        ], $this->ownerA);

        $settlement->refresh();
        $this->assertEquals(50000.00, (float) $settlement->paid_amount);
        $this->assertEquals(30000.00, (float) $settlement->balance_due);
        $this->assertEquals(OwnerSettlement::STATUS_PARTIALLY_PAID, $settlement->status);
        $this->assertEquals(OwnerSettlement::PAYMENT_PARTIALLY_PAID, $settlement->payment_status);
        $this->assertEquals(30000.00, $this->truckOwnerA->getCurrentBalance());

        // 2. Final Payment of PKR 30,000
        $payment2 = $service->recordOwnerPayment([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'vehicle_owner_id' => $this->truckOwnerA->id,
            'owner_settlement_id' => $settlement->id,
            'payment_date' => now()->toDateString(),
            'amount' => 30000.00,
            'payment_method' => OwnerPayment::METHOD_CHEQUE,
            'cheque_number' => 'CHQ-990011',
            'bank_name' => 'Meezan Bank Ltd',
        ], $this->ownerA);

        $settlement->refresh();
        $this->assertEquals(80000.00, (float) $settlement->paid_amount);
        $this->assertEquals(0.00, (float) $settlement->balance_due);
        $this->assertEquals(OwnerSettlement::STATUS_PAID, $settlement->status);
        $this->assertEquals(OwnerSettlement::PAYMENT_PAID, $settlement->payment_status);
        $this->assertEquals(0.00, $this->truckOwnerA->getCurrentBalance());
    }

    /**
     * Test 13: Owner Ledger Running Balance Continuity across multiple transactions.
     */
    public function test_owner_ledger_running_balance_continuity(): void
    {
        $ledgerService = app(OwnerLedgerService::class);

        // 1. Opening Balance: PKR 15,000 Credit
        $ledgerService->recordOpeningBalance($this->truckOwnerA, 15000.00, OwnerLedger::ENTRY_CREDIT, now()->subMonths(1)->toDateString(), $this->ownerA->id);
        $this->assertEquals(15000.00, $this->truckOwnerA->getCurrentBalance());

        // 2. Trip 1 Payable: +PKR 40,000
        $ledgerService->recordEntry(
            companyId: $this->companyA->id,
            vehicleOwnerId: $this->truckOwnerA->id,
            transactionType: OwnerLedger::TYPE_TRIP_PAYABLE,
            entryType: OwnerLedger::ENTRY_CREDIT,
            amount: 40000.00,
            description: 'Trip 1 Freight'
        );
        $this->assertEquals(55000.00, $this->truckOwnerA->getCurrentBalance());

        // 3. Deduction (Fuel): -PKR 10,000
        $ledgerService->recordEntry(
            companyId: $this->companyA->id,
            vehicleOwnerId: $this->truckOwnerA->id,
            transactionType: OwnerLedger::TYPE_DEDUCTION,
            entryType: OwnerLedger::ENTRY_DEBIT,
            amount: 10000.00,
            description: 'Fuel Deduction'
        );
        $this->assertEquals(45000.00, $this->truckOwnerA->getCurrentBalance());

        // 4. Payment to Owner: -PKR 35,000
        $ledgerService->recordEntry(
            companyId: $this->companyA->id,
            vehicleOwnerId: $this->truckOwnerA->id,
            transactionType: OwnerLedger::TYPE_PAYMENT,
            entryType: OwnerLedger::ENTRY_DEBIT,
            amount: 35000.00,
            description: 'Bank Transfer Payment'
        );
        $this->assertEquals(10000.00, $this->truckOwnerA->getCurrentBalance());

        // Summary verification
        $summary = $ledgerService->getOwnerLedgerSummary($this->truckOwnerA);
        $this->assertEquals(15000.00, $summary['opening_balance']);
        $this->assertEquals(40000.00, $summary['total_trip_payables']);
        $this->assertEquals(10000.00, $summary['total_deductions']);
        $this->assertEquals(35000.00, $summary['total_payments']);
        $this->assertEquals(10000.00, $summary['closing_balance']);
    }

    /**
     * Test 14: Strict Financial Separation in Accounting Integration Payloads.
     */
    public function test_financial_separation_accounting_payload(): void
    {
        $service = app(OwnerSettlementService::class);
        $accountingService = app(AccountingIntegrationService::class);

        $settlement = $service->createSettlement([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'vehicle_owner_id' => $this->truckOwnerA->id,
            'vehicle_id' => $this->vehicleAttachedA->id,
            'trip_id' => $this->tripA1->id,
            'customer_freight_total' => 120000.00, // Customer billed
            'agreed_owner_payable' => 90000.00,    // Owner hire expense
            'additional_charges' => 2000.00,
            'deductions' => [
                [
                    'deduction_type' => OwnerSettlementDeduction::TYPE_FUEL,
                    'amount' => 15000.00,
                    'description' => 'Diesel recovery',
                ],
                [
                    'deduction_type' => OwnerSettlementDeduction::TYPE_PENALTY,
                    'amount' => 3000.00,
                    'description' => 'Late arrival penalty',
                ],
            ],
        ], $this->ownerA);

        $payload = $accountingService->buildOwnerSettlementAccountingPayload($settlement);

        $this->assertEquals('OWNER_SETTLEMENT', $payload['transaction_type']);
        $this->assertEquals(120000.00, $payload['customer_freight_reference']);
        $this->assertEquals(90000.00, $payload['agreed_gross_payable']);

        // Check double-entry balance: Sum(Debits) must equal Sum(Credits)
        $totalDebits = collect($payload['entries'])->sum('debit');
        $totalCredits = collect($payload['entries'])->sum('credit');

        // Gross cost = 90,000 + 2,000 = 92,000
        // Deductions = 15,000 (Fuel) + 3,000 (Penalty) = 18,000
        // Net Payable = 92,000 - 18,000 = 74,000
        // Credits = 15,000 + 3,000 + 74,000 = 92,000
        $this->assertEquals(92000.00, $totalDebits);
        $this->assertEquals(92000.00, $totalCredits);
        $this->assertEquals($totalDebits, $totalCredits);

        // Ensure owner payable is recorded as EXPENSE, NOT income/revenue
        $expenseEntry = collect($payload['entries'])->firstWhere('account_code', '5300-ATTACHED-TRUCK-HIRE-EXPENSE');
        $this->assertNotNull($expenseEntry);
        $this->assertEquals('EXPENSE', $expenseEntry['account_type']);
    }

    /**
     * Test 15: Cross-tenant isolation (Tenant B cannot access or settle Tenant A's owners).
     */
    public function test_owner_settlement_and_ledger_tenant_isolation(): void
    {
        $service = app(OwnerSettlementService::class);

        // Tenant A creates settlement
        $settlementA = $service->createSettlement([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'vehicle_owner_id' => $this->truckOwnerA->id,
            'vehicle_id' => $this->vehicleAttachedA->id,
            'trip_id' => $this->tripA1->id,
            'agreed_owner_payable' => 40000.00,
        ], $this->ownerA);

        // 1. Eloquent BelongsToCompany Scope isolation
        $this->actingAs($this->ownerB);
        app(TenantContextService::class)->setTenantContext($this->companyB, $this->branchB);

        $visibleSettlementsForB = OwnerSettlement::where('id', $settlementA->id)->first();
        $this->assertNull($visibleSettlementsForB);

        // 2. Livewire Component Isolation
        Livewire::test(OwnerSettlementManager::class)
            ->assertDontSee($settlementA->settlement_number)
            ->assertDontSee($this->truckOwnerA->name);
    }
}


<?php

namespace Tests\Feature;

use App\Livewire\Fleet\DriverManager;
use App\Livewire\Fleet\FleetAlertsManager;
use App\Livewire\Fleet\VehicleManager;
use App\Livewire\Fleet\VehicleOwnerManager;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Driver;
use App\Models\DriverAdvance;
use App\Models\FleetDocument;
use App\Models\OwnerContract;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDriverAssignment;
use App\Models\VehicleOwner;
use App\Services\DriverOperationService;
use App\Services\FleetManagementService;
use App\Services\OwnerContractService;
use App\Services\TenantContextService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class FleetDriverManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected Company $companyB;
    protected User $ownerA;
    protected User $ownerB;
    protected User $superAdmin;
    protected Branch $branchA;
    protected Branch $branchB;
    protected SubscriptionPlan $fleetPlan;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. System Roles
        Role::create([
            'company_id' => null,
            'name' => 'Tenant Administrator',
            'slug' => 'tenant_admin',
            'is_system' => true,
        ]);

        Role::create([
            'company_id' => null,
            'name' => 'Fleet Supervisor',
            'slug' => 'fleet_supervisor',
            'is_system' => true,
        ]);

        // 2. Subscription Plan with max_vehicles = 2
        $this->fleetPlan = SubscriptionPlan::create([
            'name' => 'Fleet Standard Plan',
            'slug' => 'fleet-standard',
            'description' => 'Logistics fleet plan with 2 truck limit for testing',
            'monthly_price' => 20000.00,
            'quarterly_price' => 55000.00,
            'annual_price' => 200000.00,
            'trial_duration_days' => 14,
            'max_vehicles' => 2,
            'max_users' => 10,
            'max_branches' => 3,
            'max_storage_mb' => 2048,
            'features' => ['fleet_management', 'driver_management', 'trip_management'],
            'is_active' => true,
        ]);

        // 3. Super Admin
        $this->superAdmin = User::create([
            'name' => 'Platform Super Admin',
            'email' => 'superadmin@platform.local',
            'password' => Hash::make('Secret123!'),
            'is_super_admin' => true,
            'status' => 'active',
            'company_id' => null,
        ]);

        $adminRole = Role::where('slug', 'tenant_admin')->first();

        // 4. Tenant Company A
        $this->companyA = Company::create([
            'name' => 'Khyber Cargo Services',
            'code' => 'KCS',
            'slug' => 'khyber-cargo',
            'email' => 'ops@khybercargo.com',
            'phone' => '+92-91-5840011',
            'currency' => 'PKR',
            'timezone' => 'Asia/Karachi',
            'status' => 'active',
            'plan_id' => $this->fleetPlan->id,
        ]);

        $this->branchA = Branch::create([
            'company_id' => $this->companyA->id,
            'code' => 'KCS-PEW',
            'name' => 'Peshawar Terminal',
            'city' => 'Peshawar',
            'is_head_office' => true,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->ownerA = User::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'role_id' => $adminRole->id,
            'name' => 'Khan Bahadur',
            'email' => 'khan@khybercargo.com',
            'password' => Hash::make('Secret123!'),
            'status' => 'active',
            'is_super_admin' => false,
        ]);

        $this->companyA->owner_id = $this->ownerA->id;
        $this->companyA->save();

        // 5. Tenant Company B
        $this->companyB = Company::create([
            'name' => 'Mehran Transport Line',
            'code' => 'MTL',
            'slug' => 'mehran-transport',
            'email' => 'info@mehrantransport.com',
            'phone' => '+92-21-34567890',
            'currency' => 'PKR',
            'timezone' => 'Asia/Karachi',
            'status' => 'active',
            'plan_id' => $this->fleetPlan->id,
        ]);

        $this->branchB = Branch::create([
            'company_id' => $this->companyB->id,
            'code' => 'MTL-KHI',
            'name' => 'Karachi Port Branch',
            'city' => 'Karachi',
            'is_head_office' => true,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->ownerB = User::create([
            'company_id' => $this->companyB->id,
            'branch_id' => $this->branchB->id,
            'role_id' => $adminRole->id,
            'name' => 'Jamshoro Logistics Owner',
            'email' => 'jamshoro@mehrantransport.com',
            'password' => Hash::make('Secret123!'),
            'status' => 'active',
            'is_super_admin' => false,
        ]);

        $this->companyB->owner_id = $this->ownerB->id;
        $this->companyB->save();
    }

    /**
     * 1. Test Company-Owned Vehicle Registration and Audit Logging
     */
    public function test_company_owned_vehicle_creation_and_registration(): void
    {
        $fleetService = app(FleetManagementService::class);

        $vehicle = $fleetService->registerVehicle($this->companyA, [
            'registration_number' => 'les-18-9922',
            'fleet_number' => 'FL-01',
            'ownership_type' => Vehicle::OWNERSHIP_COMPANY,
            'vehicle_category' => 'HCV',
            'vehicle_type' => 'TRAILER_22W',
            'make' => 'Hino',
            'model' => '700 Series',
            'manufacturing_year' => 2021,
            'chassis_number' => 'CH-88392019',
            'engine_number' => 'ENG-7728192',
            'payload_capacity_tons' => 45.00,
            'volume_capacity_cbm' => 85.00,
            'fuel_type' => 'DIESEL',
            'current_odometer_km' => 125000,
            'branch_id' => $this->branchA->id,
            'registration_expiry_date' => now()->addYear()->toDateString(),
            'insurance_expiry_date' => now()->addMonths(6)->toDateString(),
            'fitness_expiry_date' => now()->addMonths(4)->toDateString(),
            'route_permit_expiry_date' => now()->addMonths(8)->toDateString(),
            'status' => Vehicle::STATUS_AVAILABLE,
        ]);

        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'company_id' => $this->companyA->id,
            'registration_number' => 'LES-18-9922',
            'ownership_type' => 'company_owned',
            'vehicle_owner_id' => null,
            'status' => 'available',
        ]);

        $this->assertTrue($vehicle->isCompanyOwned());
        $this->assertFalse($vehicle->isMarketAttached());
    }

    /**
     * 2. Test Market-Attached Vehicle Requires Registered Vehicle Owner
     */
    public function test_attached_vehicle_requires_vehicle_owner(): void
    {
        $fleetService = app(FleetManagementService::class);

        // Expect Exception if attached truck lacks owner
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Market attached or rented vehicles must be linked to a registered vehicle owner.');

        $fleetService->registerVehicle($this->companyA, [
            'registration_number' => 'TKN-4001',
            'ownership_type' => Vehicle::OWNERSHIP_ATTACHED,
            'vehicle_owner_id' => null, // Intentionally missing owner
        ]);
    }

    /**
     * 3. Test Attached Vehicle Creation Succeeded When Owner Provided
     */
    public function test_attached_vehicle_succeeds_with_registered_owner(): void
    {
        $owner = VehicleOwner::create([
            'company_id' => $this->companyA->id,
            'name' => 'Chaudhry Akram Gujjar',
            'company_name' => 'Gujjar Goods Transport',
            'cnic_number' => '35202-9988776-1',
            'phone' => '0300-9988776',
            'city' => 'Gujranwala',
            'status' => 'active',
        ]);

        $fleetService = app(FleetManagementService::class);

        $vehicle = $fleetService->registerVehicle($this->companyA, [
            'registration_number' => 'TKN-4001',
            'ownership_type' => Vehicle::OWNERSHIP_ATTACHED,
            'vehicle_owner_id' => $owner->id,
            'vehicle_category' => 'HCV',
            'payload_capacity_tons' => 30.00,
        ]);

        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'company_id' => $this->companyA->id,
            'registration_number' => 'TKN-4001',
            'vehicle_owner_id' => $owner->id,
            'ownership_type' => 'market_attached',
        ]);

        $this->assertTrue($vehicle->isMarketAttached());
        $this->assertEquals($owner->id, $vehicle->owner->id);
    }

    /**
     * 4. Test Duplicate Active Registration Prevention Within Tenant
     */
    public function test_duplicate_registration_number_prevention_within_tenant(): void
    {
        Vehicle::create([
            'company_id' => $this->companyA->id,
            'registration_number' => 'KHI-9988',
            'ownership_type' => Vehicle::OWNERSHIP_COMPANY,
            'status' => 'available',
        ]);

        // Attempting to create same registration in Company A via Livewire should fail validation
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setCompany($this->companyA);

        Livewire::test(VehicleManager::class)
            ->set('registration_number', 'KHI-9988')
            ->set('ownership_type', Vehicle::OWNERSHIP_COMPANY)
            ->set('vehicle_category', 'HCV')
            ->set('payload_capacity_tons', 20.00)
            ->call('saveVehicle')
            ->assertHasErrors(['registration_number']);

        // But Company B CAN register KHI-9988 (isolated tenant database scope)
        $this->actingAs($this->ownerB);
        app(TenantContextService::class)->setCompany($this->companyB);

        Livewire::test(VehicleManager::class)
            ->set('registration_number', 'khi-9988')
            ->set('ownership_type', Vehicle::OWNERSHIP_COMPANY)
            ->set('vehicle_category', 'HCV')
            ->set('payload_capacity_tons', 25.00)
            ->call('saveVehicle')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('vehicles', [
            'company_id' => $this->companyB->id,
            'registration_number' => 'KHI-9988',
        ]);
    }

    /**
     * 5. Test Subscription Plan max_vehicles Quota Enforcement
     */
    public function test_subscription_plan_max_vehicles_quota_enforcement(): void
    {
        $fleetService = app(FleetManagementService::class);

        // Plan max_vehicles is 2
        // Register Vehicle 1
        $fleetService->registerVehicle($this->companyA, [
            'registration_number' => 'TRK-001',
            'ownership_type' => Vehicle::OWNERSHIP_COMPANY,
        ]);

        // Register Vehicle 2
        $fleetService->registerVehicle($this->companyA, [
            'registration_number' => 'TRK-002',
            'ownership_type' => Vehicle::OWNERSHIP_COMPANY,
        ]);

        $this->assertEquals(2, $this->companyA->vehicles()->count());

        // Attempting to register Vehicle 3 must throw AuthorizationException
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Fleet quota exceeded. Your plan [Fleet Standard Plan] permits a maximum of 2 registered vehicles.');

        $fleetService->registerVehicle($this->companyA, [
            'registration_number' => 'TRK-003',
            'ownership_type' => Vehicle::OWNERSHIP_COMPANY,
        ]);
    }

    /**
     * 6. Test Owner Contract Calculations Across All 5 Payment Models
     */
    public function test_owner_contract_calculations_across_all_five_payment_models(): void
    {
        $contractService = app(OwnerContractService::class);

        $owner = VehicleOwner::create([
            'company_id' => $this->companyA->id,
            'name' => 'Sardar Transport Malik',
            'phone' => '0312-3456789',
        ]);

        // Model 1: Fixed Amount Per Trip
        $fixedContract = OwnerContract::create([
            'company_id' => $this->companyA->id,
            'vehicle_owner_id' => $owner->id,
            'contract_number' => 'CNT-FIXED-01',
            'payment_model' => OwnerContract::MODEL_FIXED_PER_TRIP,
            'rate_amount' => 75000.00,
            'deduct_fuel' => true,
            'deduct_toll_taxes' => true,
            'deduct_advances' => true,
            'start_date' => now()->subMonth(),
            'status' => 'active',
        ]);

        $calcFixed = $contractService->calculateOwnerPayable(
            $fixedContract,
            80000.00,
            0.0,
            [
                'fuel' => 12000.00,
                'toll' => 3000.00,
                'advances' => 5000.00,
            ]
        );

        $this->assertEquals(75000.00, $calcFixed['gross_hire']);
        $this->assertEquals(20000.00, $calcFixed['total_deductions']); // 12000 + 3000 + 5000
        $this->assertEquals(55000.00, $calcFixed['net_payable']);

        // Model 2: Percentage of Freight
        $pctContract = OwnerContract::create([
            'company_id' => $this->companyA->id,
            'vehicle_owner_id' => $owner->id,
            'contract_number' => 'CNT-PCT-02',
            'payment_model' => OwnerContract::MODEL_PERCENTAGE_OF_FREIGHT,
            'freight_percentage' => 85.00, // 85% to owner
            'commission_percentage' => 5.00, // 5% company cut
            'deduct_fuel' => true,
            'deduct_toll_taxes' => false,
            'deduct_advances' => false,
            'start_date' => now()->subMonth(),
            'status' => 'active',
        ]);

        // Gross freight = 100,000. Owner base (85%) = 85,000. Commission (5% of 100k) = 5,000. Fuel deduction = 15,000
        $calcPct = $contractService->calculateOwnerPayable(
            $pctContract,
            100000.00,
            0.0,
            ['fuel' => 15000.00]
        );

        $this->assertEquals(85000.00, $calcPct['gross_hire']);
        $this->assertEquals(5000.00, $calcPct['deductions']['company_commission']);
        $this->assertEquals(20000.00, $calcPct['total_deductions']); // 5000 comm + 15000 fuel
        $this->assertEquals(65000.00, $calcPct['net_payable']);

        // Model 3: Per-Kilometre Rate
        $perKmContract = OwnerContract::create([
            'company_id' => $this->companyA->id,
            'vehicle_owner_id' => $owner->id,
            'contract_number' => 'CNT-KM-03',
            'payment_model' => OwnerContract::MODEL_PER_KM,
            'rate_amount' => 70.00, // PKR 70 per km
            'deduct_fuel' => false,
            'deduct_toll_taxes' => false,
            'deduct_advances' => true,
            'start_date' => now()->subMonth(),
            'status' => 'active',
        ]);

        // Trip distance = 1,200 km -> Gross Hire = 1,200 * 70 = 84,000
        $calcKm = $contractService->calculateOwnerPayable(
            $perKmContract,
            90000.00,
            1200.0,
            ['advances' => 10000.00]
        );

        $this->assertEquals(84000.00, $calcKm['gross_hire']);
        $this->assertEquals(10000.00, $calcKm['total_deductions']);
        $this->assertEquals(74000.00, $calcKm['net_payable']);

        // Model 4: Monthly Rental
        $monthlyContract = OwnerContract::create([
            'company_id' => $this->companyA->id,
            'vehicle_owner_id' => $owner->id,
            'contract_number' => 'CNT-MONTHLY-04',
            'payment_model' => OwnerContract::MODEL_MONTHLY_RENTAL,
            'rate_amount' => 220000.00,
            'start_date' => now()->subMonth(),
            'status' => 'active',
        ]);

        $calcMonthly = $contractService->calculateOwnerPayable(
            $monthlyContract,
            0.0,
            0.0,
            ['other' => 20000.00]
        );

        $this->assertEquals(220000.00, $calcMonthly['gross_hire']);
        $this->assertEquals(20000.00, $calcMonthly['total_deductions']);
        $this->assertEquals(200000.00, $calcMonthly['net_payable']);

        // Model 5: Route-Specific Payment
        $routeContract = OwnerContract::create([
            'company_id' => $this->companyA->id,
            'vehicle_owner_id' => $owner->id,
            'contract_number' => 'CNT-ROUTE-05',
            'payment_model' => OwnerContract::MODEL_ROUTE_SPECIFIC,
            'rate_amount' => 115000.00,
            'route_origin' => 'Islamabad',
            'route_destination' => 'Karachi',
            'deduct_toll_taxes' => true,
            'start_date' => now()->subMonth(),
            'status' => 'active',
        ]);

        $calcRoute = $contractService->calculateOwnerPayable(
            $routeContract,
            125000.00,
            0.0,
            ['toll' => 5000.00]
        );

        $this->assertEquals(115000.00, $calcRoute['gross_hire']);
        $this->assertEquals(5000.00, $calcRoute['total_deductions']);
        $this->assertEquals(110000.00, $calcRoute['net_payable']);
    }

    /**
     * 7. Test Driver Registration and CNIC Uniqueness Scoped to Tenant
     */
    public function test_driver_registration_and_tenant_cnic_uniqueness(): void
    {
        $driver = Driver::create([
            'company_id' => $this->companyA->id,
            'name' => 'Muhammad Aslam',
            'cnic_number' => '37405-1122334-1',
            'phone' => '0301-5554443',
            'license_number' => 'HTV-88392',
            'license_category' => 'HTV',
            'license_expiry_date' => now()->addYears(2),
            'basic_salary' => 38000.00,
            'status' => Driver::STATUS_AVAILABLE,
        ]);

        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'company_id' => $this->companyA->id,
            'cnic_number' => '37405-1122334-1',
        ]);

        // Same company cannot register identical CNIC
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setCompany($this->companyA);

        Livewire::test(DriverManager::class)
            ->set('name', 'Duplicate Aslam')
            ->set('cnic_number', '37405-1122334-1')
            ->set('phone', '0300-0000000')
            ->set('license_number', 'HTV-99999')
            ->set('license_category', 'HTV')
            ->set('license_expiry_date', now()->addYear()->toDateString())
            ->call('saveDriver')
            ->assertHasErrors(['cnic_number']);

        // Company B CAN register same CNIC (multi-tenant isolation)
        $this->actingAs($this->ownerB);
        app(TenantContextService::class)->setCompany($this->companyB);

        Livewire::test(DriverManager::class)
            ->set('name', 'Muhammad Aslam Independent')
            ->set('cnic_number', '37405-1122334-1')
            ->set('phone', '0300-1111111')
            ->set('license_number', 'HTV-88392')
            ->set('license_category', 'HTV')
            ->set('license_expiry_date', now()->addYear()->toDateString())
            ->call('saveDriver')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('drivers', [
            'company_id' => $this->companyB->id,
            'cnic_number' => '37405-1122334-1',
        ]);
    }

    /**
     * 8. Test Driver Cash Advance Disbursement and Settlement Lifecycle
     */
    public function test_driver_cash_advance_disbursement_and_settlement_lifecycle(): void
    {
        $driver = Driver::create([
            'company_id' => $this->companyA->id,
            'name' => 'Tariq Mehmood',
            'cnic_number' => '37405-7788990-1',
            'phone' => '0302-9988776',
            'license_number' => 'HTV-44556',
            'license_category' => 'HTV',
            'license_expiry_date' => now()->addYears(3),
            'basic_salary' => 35000.00,
            'status' => Driver::STATUS_AVAILABLE,
        ]);

        $driverOpService = app(DriverOperationService::class);

        // 1. Disburse Advance 1
        $advance = $driverOpService->disburseAdvance(
            $driver,
            15000.00,
            'Trip Diesel Allowance',
            'cash',
            'VOUCHER-01',
            null,
            $this->ownerA->id
        );

        $this->assertStringStartsWith('ADV-', $advance->advance_number);
        $this->assertEquals(15000.00, $advance->amount);
        $this->assertEquals(15000.00, $advance->balance_amount);
        $this->assertEquals('disbursed', $advance->status);

        $driver->refresh();
        $this->assertEquals(15000.00, (float) $driver->total_advances_balance);

        // 2. Partial Settlement of 5,000
        $driverOpService->settleAdvance($advance, 5000.00);

        $advance->refresh();
        $this->assertEquals(5000.00, $advance->settled_amount);
        $this->assertEquals(10000.00, $advance->balance_amount);
        $this->assertEquals('partially_settled', $advance->status);

        $driver->refresh();
        $this->assertEquals(10000.00, (float) $driver->total_advances_balance);

        // 3. Complete Settlement of remaining 10,000
        $driverOpService->settleAdvance($advance, 10000.00);

        $advance->refresh();
        $this->assertEquals(15000.00, $advance->settled_amount);
        $this->assertEquals(0.00, $advance->balance_amount);
        $this->assertEquals('settled', $advance->status);

        $driver->refresh();
        $this->assertEquals(0.00, (float) $driver->total_advances_balance);
    }

    /**
     * 9. Test Vehicle Driver Assignment (Primary Driver and Long-Haul Relief Co-Driver)
     */
    public function test_vehicle_driver_assignment_primary_and_co_driver(): void
    {
        $vehicle = Vehicle::create([
            'company_id' => $this->companyA->id,
            'registration_number' => 'LHR-7766',
            'ownership_type' => Vehicle::OWNERSHIP_COMPANY,
            'status' => 'available',
        ]);

        $primaryDriver = Driver::create([
            'company_id' => $this->companyA->id,
            'name' => 'Primary Driver Sultan',
            'cnic_number' => '35201-1111111-1',
            'phone' => '0300-1111111',
            'license_number' => 'HTV-111',
            'license_category' => 'HTV',
            'license_expiry_date' => now()->addYear(),
            'status' => Driver::STATUS_AVAILABLE,
        ]);

        $coDriver = Driver::create([
            'company_id' => $this->companyA->id,
            'name' => 'Relief Co-Driver Bashir',
            'cnic_number' => '35201-2222222-2',
            'phone' => '0300-2222222',
            'license_number' => 'HTV-222',
            'license_category' => 'HTV',
            'license_expiry_date' => now()->addYear(),
            'status' => Driver::STATUS_AVAILABLE,
        ]);

        $fleetService = app(FleetManagementService::class);

        // Assign both primary and co-driver
        $fleetService->assignDrivers($vehicle, $primaryDriver->id, $coDriver->id, $this->ownerA->id);

        $vehicle->refresh();
        $primaryDriver->refresh();
        $coDriver->refresh();

        $this->assertEquals($primaryDriver->id, $vehicle->current_driver_id);
        $this->assertEquals($vehicle->id, $primaryDriver->current_vehicle_id);
        $this->assertEquals($vehicle->id, $coDriver->current_vehicle_id);

        $assignments = VehicleDriverAssignment::where('vehicle_id', $vehicle->id)->where('status', 'active')->get();
        $this->assertCount(2, $assignments);
        $this->assertTrue($assignments->firstWhere('driver_id', $primaryDriver->id)->is_primary);
        $this->assertFalse($assignments->firstWhere('driver_id', $coDriver->id)->is_primary);

        // Unassign drivers
        $fleetService->unassignDrivers($vehicle);

        $vehicle->refresh();
        $primaryDriver->refresh();
        $coDriver->refresh();

        $this->assertNull($vehicle->current_driver_id);
        $this->assertNull($primaryDriver->current_vehicle_id);
        $this->assertNull($coDriver->current_vehicle_id);

        $activeAssignments = VehicleDriverAssignment::where('vehicle_id', $vehicle->id)->where('status', 'active')->count();
        $this->assertEquals(0, $activeAssignments);
    }

    /**
     * 10. Test Document Expiry Alert Categorization (Expired, Critical, Warning)
     */
    public function test_document_expiry_alert_detection_and_severity_categorization(): void
    {
        // 1. Expired Fitness Vehicle
        Vehicle::create([
            'company_id' => $this->companyA->id,
            'registration_number' => 'EXPIRED-01',
            'ownership_type' => Vehicle::OWNERSHIP_COMPANY,
            'fitness_expiry_date' => now()->subDays(5)->toDateString(),
            'status' => 'available',
        ]);

        // 2. Critical Expiry Insurance Vehicle (in 3 days <= 7 days)
        Vehicle::create([
            'company_id' => $this->companyA->id,
            'registration_number' => 'CRITICAL-02',
            'ownership_type' => Vehicle::OWNERSHIP_COMPANY,
            'insurance_expiry_date' => now()->addDays(3)->toDateString(),
            'status' => 'available',
        ]);

        // 3. Warning Expiry Route Permit Vehicle (in 20 days <= 30 days)
        Vehicle::create([
            'company_id' => $this->companyA->id,
            'registration_number' => 'WARNING-03',
            'ownership_type' => Vehicle::OWNERSHIP_COMPANY,
            'route_permit_expiry_date' => now()->addDays(20)->toDateString(),
            'status' => 'available',
        ]);

        // 4. Critical Expiry Driver License (in 2 days)
        Driver::create([
            'company_id' => $this->companyA->id,
            'name' => 'License Expiring Soon Driver',
            'cnic_number' => '37405-3333333-3',
            'phone' => '0300-3333333',
            'license_number' => 'HTV-333',
            'license_category' => 'HTV',
            'license_expiry_date' => now()->addDays(2)->toDateString(),
            'status' => Driver::STATUS_AVAILABLE,
        ]);

        $fleetService = app(FleetManagementService::class);
        $alerts = $fleetService->getExpiringDocuments($this->companyA->id, 30);

        $this->assertCount(1, $alerts['expired']); // EXPIRED-01 fitness
        $this->assertCount(2, $alerts['critical']); // CRITICAL-02 insurance + Driver license
        $this->assertCount(1, $alerts['warning']); // WARNING-03 route permit
        $this->assertEquals(4, $alerts['total_alerts']);
    }

    /**
     * 11. Test Cross-Tenant Fleet Isolation
     */
    public function test_cross_tenant_fleet_isolation(): void
    {
        $vehicleA = Vehicle::create([
            'company_id' => $this->companyA->id,
            'registration_number' => 'TK-A-01',
            'ownership_type' => Vehicle::OWNERSHIP_COMPANY,
            'status' => 'available',
        ]);

        $vehicleB = Vehicle::create([
            'company_id' => $this->companyB->id,
            'registration_number' => 'TK-B-01',
            'ownership_type' => Vehicle::OWNERSHIP_COMPANY,
            'status' => 'available',
        ]);

        $ownerA = VehicleOwner::create([
            'company_id' => $this->companyA->id,
            'name' => 'Owner Alpha',
            'phone' => '0300-1111111',
        ]);

        $ownerB = VehicleOwner::create([
            'company_id' => $this->companyB->id,
            'name' => 'Owner Beta',
            'phone' => '0300-2222222',
        ]);

        // Attempting to edit Vehicle B as Company A user must be forbidden (403)
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setCompany($this->companyA);

        Livewire::test(VehicleManager::class)
            ->call('editVehicle', $vehicleB->id)
            ->assertForbidden();
    }

    /**
     * 12. Test Livewire VehicleManager Component End-to-End
     */
    public function test_livewire_vehicle_manager_component(): void
    {
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setCompany($this->companyA);

        Livewire::test(VehicleManager::class)
            ->assertSee('Register Vehicle')
            ->call('openCreateModal')
            ->set('registration_number', 'KHI-2026-X')
            ->set('fleet_number', 'FL-88')
            ->set('ownership_type', Vehicle::OWNERSHIP_COMPANY)
            ->set('vehicle_category', 'HCV')
            ->set('vehicle_type', 'TRAILER_22W')
            ->set('make', 'ISUZU')
            ->set('payload_capacity_tons', 40.00)
            ->call('saveVehicle')
            ->assertHasNoErrors()
            ->assertSee('KHI-2026-X');

        $this->assertDatabaseHas('vehicles', [
            'company_id' => $this->companyA->id,
            'registration_number' => 'KHI-2026-X',
            'fleet_number' => 'FL-88',
        ]);
    }

    /**
     * 13. Test Livewire VehicleOwnerManager Component End-to-End
     */
    public function test_livewire_vehicle_owner_manager_component(): void
    {
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setCompany($this->companyA);

        Livewire::test(VehicleOwnerManager::class)
            ->assertSee('Register Vehicle Owner')
            ->call('openCreateOwnerModal')
            ->set('name', 'Malik Shaukat Hayat')
            ->set('company_name', 'Hayat Freight Services')
            ->set('cnic_number', '37405-5556667-1')
            ->set('phone', '0303-9988112')
            ->set('bank_name', 'Meezan Bank Ltd')
            ->set('bank_iban', 'PK36MEZN0001234567890123')
            ->call('saveOwner')
            ->assertHasNoErrors()
            ->assertSee('Malik Shaukat Hayat');

        $this->assertDatabaseHas('vehicle_owners', [
            'company_id' => $this->companyA->id,
            'name' => 'Malik Shaukat Hayat',
            'cnic_number' => '37405-5556667-1',
        ]);
    }

    /**
     * 14. Test Livewire DriverManager Component End-to-End with Cash Advance
     */
    public function test_livewire_driver_manager_component_with_advance(): void
    {
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setCompany($this->companyA);

        // 1. Create Driver via Component
        $comp = Livewire::test(DriverManager::class)
            ->assertSee('Register Driver')
            ->call('openCreateDriverModal')
            ->set('name', 'Nawazish Ali')
            ->set('father_name', 'Ali Bux')
            ->set('cnic_number', '37405-4433221-1')
            ->set('phone', '0304-4433221')
            ->set('license_number', 'HTV-778899')
            ->set('license_category', 'HTV')
            ->set('license_expiry_date', now()->addYears(2)->toDateString())
            ->set('basic_salary', 40000.00)
            ->call('saveDriver')
            ->assertHasNoErrors()
            ->assertSee('Nawazish Ali');

        $driver = Driver::where('cnic_number', '37405-4433221-1')->first();
        $this->assertNotNull($driver);

        // 2. Disburse Cash Advance via Component
        $comp->call('openAdvanceModal', $driver->id)
            ->set('advance_amount', 12000.00)
            ->set('advance_purpose', 'Trip Diesel & Highway Allowance')
            ->set('payment_method', 'cash')
            ->call('disburseAdvance')
            ->assertHasNoErrors()
            ->assertSee('Cash advance of PKR 12,000.00 disbursed');

        $driver->refresh();
        $this->assertEquals(12000.00, (float) $driver->total_advances_balance);
        $this->assertDatabaseHas('driver_advances', [
            'driver_id' => $driver->id,
            'amount' => 12000.00,
            'status' => 'disbursed',
        ]);
    }

    /**
     * 15. Test Livewire FleetAlertsManager Component
     */
    public function test_livewire_fleet_alerts_manager_component(): void
    {
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setCompany($this->companyA);

        Vehicle::create([
            'company_id' => $this->companyA->id,
            'registration_number' => 'ALERT-TEST-01',
            'ownership_type' => Vehicle::OWNERSHIP_COMPANY,
            'fitness_expiry_date' => now()->subDay()->toDateString(),
            'status' => 'available',
        ]);

        Livewire::test(FleetAlertsManager::class)
            ->assertSee('EXPIRED REGULATORY DOCUMENTS')
            ->assertSee('ALERT-TEST-01');
    }
}

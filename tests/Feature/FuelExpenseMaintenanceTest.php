<?php

namespace Tests\Feature;

use App\Livewire\Fleet\FuelManager;
use App\Livewire\Fleet\WorkshopManager;
use App\Livewire\Operations\TripExpenseManager;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Consignment;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\FuelCard;
use App\Models\FuelEntry;
use App\Models\FuelSupplier;
use App\Models\JobCard;
use App\Models\MaintenanceAlert;
use App\Models\MaintenanceSchedule;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\Trip;
use App\Models\TripExpense;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleComponent;
use App\Services\AccountingIntegrationService;
use App\Services\FuelManagementService;
use App\Services\TenantContextService;
use App\Services\TripExpenseService;
use App\Services\VehicleMaintenanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class FuelExpenseMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected Company $companyB;
    protected User $ownerA;
    protected User $ownerB;
    protected Branch $branchA;
    protected Branch $branchB;
    protected Vehicle $vehicleA1;
    protected Driver $driverA1;
    protected Trip $tripA1;
    protected FuelSupplier $supplierA;
    protected FuelCard $cardA;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create([
            'company_id' => null,
            'name' => 'Tenant Administrator',
            'slug' => 'tenant_admin',
            'is_system' => true,
        ]);

        $plan = SubscriptionPlan::create([
            'name' => 'Fleet Fuel & Maintenance Plan',
            'slug' => 'fleet-fuel-maintenance',
            'description' => 'Comprehensive fleet operations plan',
            'monthly_price' => 25000.00,
            'quarterly_price' => 70000.00,
            'annual_price' => 250000.00,
            'max_vehicles' => 50,
            'max_users' => 25,
            'max_branches' => 5,
            'max_storage_mb' => 10000,
            'features' => ['all'],
            'is_active' => true,
        ]);

        $adminRole = Role::where('slug', 'tenant_admin')->first();

        // 1. Setup Tenant Company A
        $this->companyA = Company::create([
            'name' => 'Indus Fleet Logistics',
            'legal_name' => 'Indus Fleet Logistics Pvt Ltd',
            'code' => 'IFL',
            'slug' => 'indus-fleet',
            'status' => 'active',
            'plan_id' => $plan->id,
            'currency' => 'PKR',
            'timezone' => 'Asia/Karachi',
        ]);

        $this->branchA = Branch::create([
            'company_id' => $this->companyA->id,
            'name' => 'Karachi Central Hub',
            'code' => 'KHI-01',
            'city' => 'Karachi',
            'is_head_office' => true,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->ownerA = User::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'role_id' => $adminRole->id,
            'name' => 'Shahid Afridi',
            'email' => 'shahid@indusfleet.test',
            'password' => Hash::make('Secret123!'),
            'status' => 'active',
            'is_super_admin' => false,
        ]);

        $this->companyA->update(['owner_id' => $this->ownerA->id]);

        $this->vehicleA1 = Vehicle::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'registration_number' => 'KHI-9900',
            'ownership_type' => Vehicle::OWNERSHIP_COMPANY,
            'status' => Vehicle::STATUS_AVAILABLE,
            'make' => 'Hino',
            'model' => '700 Series',
            'vehicle_type' => 'TRAILER_22W',
            'payload_capacity_tons' => 35.0,
            'current_odometer_km' => 100000,
            'insurance_expiry_date' => now()->addMonths(6),
            'fitness_expiry_date' => now()->addMonths(6),
        ]);

        $this->driverA1 = Driver::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'name' => 'Nawaz Sharif Driver',
            'cnic_number' => '42201-9988776-1',
            'phone' => '0300-8877665',
            'license_number' => 'HTV-KHI-9988',
            'license_expiry_date' => now()->addYears(2)->toDateString(),
            'status' => Driver::STATUS_AVAILABLE,
        ]);

        $this->tripA1 = Trip::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'trip_number' => 'TR-2026-00001',
            'trip_date' => now()->toDateString(),
            'origin_city' => 'Karachi',
            'destination_city' => 'Lahore',
            'trip_status' => Trip::STATUS_IN_TRANSIT,
            'departure_odometer_km' => 100000,
        ]);

        $this->supplierA = FuelSupplier::create([
            'company_id' => $this->companyA->id,
            'name' => 'Pakistan State Oil (PSO)',
            'code' => 'PSO',
            'credit_limit' => 500000.00,
            'balance' => 0.00,
            'status' => FuelSupplier::STATUS_ACTIVE,
        ]);

        $this->cardA = FuelCard::create([
            'company_id' => $this->companyA->id,
            'fuel_supplier_id' => $this->supplierA->id,
            'card_number' => '7002-9988-1122-3344',
            'assigned_vehicle_id' => $this->vehicleA1->id,
            'monthly_limit' => 200000.00,
            'current_balance' => 150000.00,
            'status' => FuelCard::STATUS_ACTIVE,
        ]);

        // 2. Setup Tenant Company B
        $this->companyB = Company::create([
            'name' => 'Rohri Goods Line',
            'legal_name' => 'Rohri Goods Line Ltd',
            'code' => 'RGL',
            'slug' => 'rohri-goods',
            'status' => 'active',
            'plan_id' => $plan->id,
            'currency' => 'PKR',
            'timezone' => 'Asia/Karachi',
        ]);

        $this->branchB = Branch::create([
            'company_id' => $this->companyB->id,
            'name' => 'Sukkur Depot',
            'code' => 'SKR-01',
            'city' => 'Sukkur',
            'is_head_office' => true,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->ownerB = User::create([
            'company_id' => $this->companyB->id,
            'branch_id' => $this->branchB->id,
            'role_id' => $adminRole->id,
            'name' => 'Ghulam Nabi',
            'email' => 'nabi@rohrigoods.test',
            'password' => Hash::make('Secret123!'),
            'status' => 'active',
            'is_super_admin' => false,
        ]);

        $this->companyB->update(['owner_id' => $this->ownerB->id]);
    }

    public function test_can_record_fuel_entry_and_calculate_efficiency_and_cost_per_km(): void
    {
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setTenantContext($this->companyA, $this->ownerA);

        $fuelService = app(FuelManagementService::class);

        // Previous vehicle odometer: 100,000 KM
        // Next refuel at 100,500 KM (+500 KM) with 150 Liters @ PKR 280/L = PKR 42,000
        $entry = $fuelService->recordFuelEntry($this->companyA, [
            'vehicle_id' => $this->vehicleA1->id,
            'driver_id' => $this->driverA1->id,
            'trip_id' => $this->tripA1->id,
            'fuel_supplier_id' => $this->supplierA->id,
            'fuel_card_id' => $this->cardA->id,
            'fuel_type' => 'diesel',
            'quantity_liters' => 150.00,
            'rate_per_liter' => 280.00,
            'odometer_km' => 100500,
            'payment_method' => 'fuel_card',
            'is_full_tank' => true,
        ]);

        $this->assertInstanceOf(FuelEntry::class, $entry);
        $this->assertStringStartsWith('FL-', $entry->entry_number);
        $this->assertEquals(42000.00, (float) $entry->total_amount);
        $this->assertEquals(500, $entry->distance_km);

        // Fuel Economy: 500 KM / 150 L = 3.33 KM/L
        $this->assertEquals(3.33, (float) $entry->fuel_economy_km_per_liter);

        // Cost per KM: PKR 42,000 / 500 KM = PKR 84.00/KM
        $this->assertEquals(84.00, (float) $entry->cost_per_km);
        $this->assertFalse($entry->is_suspicious);

        // Vehicle current odometer updated to 100,500
        $this->vehicleA1->refresh();
        $this->assertEquals(100500, $this->vehicleA1->current_odometer);

        // Fuel Card balance deducted: 150,000 - 42,000 = 108,000
        $this->cardA->refresh();
        $this->assertEquals(108000.00, (float) $this->cardA->current_balance);

        // Printable Fuel Slip Route
        $response = $this->get(route('print.fuel-slip', $entry->id));
        $response->assertStatus(200);
        $response->assertSee('REFUEL SLIP');
        $response->assertSee('KHI-9900');
        $response->assertSee('3.33 KM/L');
    }

    public function test_flags_decreasing_odometer_as_suspicious_and_emits_alert(): void
    {
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setTenantContext($this->companyA, $this->ownerA);

        $fuelService = app(FuelManagementService::class);

        // Vehicle odometer is 100,000. Trying to record refuel at 95,000 (-5,000 KM backward)
        $entry = $fuelService->recordFuelEntry($this->companyA, [
            'vehicle_id' => $this->vehicleA1->id,
            'quantity_liters' => 100.00,
            'rate_per_liter' => 280.00,
            'odometer_km' => 95000,
        ]);

        $this->assertTrue($entry->is_suspicious);
        $this->assertStringContainsString('Decreasing odometer reading', $entry->anomaly_reason);

        // Alert generated
        $alert = MaintenanceAlert::where('vehicle_id', $this->vehicleA1->id)
            ->where('alert_type', MaintenanceAlert::TYPE_FUEL_ANOMALY)
            ->first();
        $this->assertNotNull($alert);
        $this->assertStringContainsString('Decreasing odometer reading', $alert->description);
    }

    public function test_flags_abnormal_fuel_economy_as_anomaly(): void
    {
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setTenantContext($this->companyA, $this->ownerA);

        $fuelService = app(FuelManagementService::class);

        // 100,000 to 100,050 KM (+50 KM), but 100 Liters filled -> 0.5 KM/L (unrealistically high consumption / fuel theft)
        $entry = $fuelService->recordFuelEntry($this->companyA, [
            'vehicle_id' => $this->vehicleA1->id,
            'quantity_liters' => 100.00,
            'rate_per_liter' => 280.00,
            'odometer_km' => 100050,
        ]);

        $this->assertTrue($entry->is_suspicious);
        $this->assertStringContainsString('Abnormal fuel economy: [0.50 KM/L]', $entry->anomaly_reason);
    }

    public function test_calculates_comprehensive_vehicle_fuel_metrics(): void
    {
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setTenantContext($this->companyA, $this->ownerA);

        $fuelService = app(FuelManagementService::class);

        // Entry 1: 100,000 -> 100,400 (400 KM) with 100 L = 4.0 KM/L, cost PKR 28,000
        $fuelService->recordFuelEntry($this->companyA, [
            'vehicle_id' => $this->vehicleA1->id,
            'quantity_liters' => 100.00,
            'rate_per_liter' => 280.00,
            'odometer_km' => 100400,
        ]);

        // Entry 2: 100,400 -> 101,000 (600 KM) with 150 L = 4.0 KM/L, cost PKR 42,000
        $fuelService->recordFuelEntry($this->companyA, [
            'vehicle_id' => $this->vehicleA1->id,
            'quantity_liters' => 150.00,
            'rate_per_liter' => 280.00,
            'odometer_km' => 101000,
        ]);

        $metrics = $fuelService->getVehicleFuelMetrics($this->vehicleA1);

        $this->assertEquals(2, $metrics['total_entries_count']);
        $this->assertEquals(250.00, $metrics['total_liters']);
        $this->assertEquals(70000.00, $metrics['total_cost']);
        $this->assertEquals(1000, $metrics['total_distance_km']);
        $this->assertEquals(4.00, $metrics['avg_km_per_liter']);
        $this->assertEquals(70.00, $metrics['avg_cost_per_km']);
    }

    public function test_trip_expense_lifecycle_and_approval_workflow(): void
    {
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setTenantContext($this->companyA, $this->ownerA);

        $expenseService = app(TripExpenseService::class);

        // 1. Record Draft Expense
        $expense = $expenseService->recordExpense($this->companyA, [
            'trip_id' => $this->tripA1->id,
            'vehicle_id' => $this->vehicleA1->id,
            'driver_id' => $this->driverA1->id,
            'expense_category' => TripExpense::CAT_TOLL_TAX,
            'amount' => 3500.00,
            'payment_method' => 'cash',
            'notes' => 'M-5 Motorway Sukkur-Multan Section Toll',
            'status' => TripExpense::STATUS_DRAFT,
        ]);

        $this->assertInstanceOf(TripExpense::class, $expense);
        $this->assertStringStartsWith('EXP-', $expense->voucher_number);
        $this->assertEquals(TripExpense::STATUS_DRAFT, $expense->status);

        // 2. Submit for Approval
        $expenseService->submitExpense($expense);
        $this->assertEquals(TripExpense::STATUS_SUBMITTED, $expense->status);

        // 3. Approve Expense
        $expenseService->approveExpense($expense, $this->ownerA->id);
        $this->assertEquals(TripExpense::STATUS_APPROVED, $expense->status);
        $this->assertEquals($this->ownerA->id, $expense->approved_by);
        $this->assertNotNull($expense->approved_at);

        // 4. Post Expense
        $posted = $expenseService->postExpense($expense, $this->ownerA->id);
        $this->assertEquals(TripExpense::STATUS_POSTED, $posted->status);
        $this->assertEquals($this->ownerA->id, $posted->posted_by);
        $this->assertNotNull($posted->posted_at);

        // Printable Voucher Route
        $response = $this->get(route('print.expense-voucher', $expense->id));
        $response->assertStatus(200);
        $response->assertSee('POSTED');
        $response->assertSee('TOLL TAX');
        $response->assertSee('3,500.00');
    }

    public function test_prevents_duplicate_posting_of_already_posted_expense(): void
    {
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setTenantContext($this->companyA, $this->ownerA);

        $expenseService = app(TripExpenseService::class);

        $expense = $expenseService->recordExpense($this->companyA, [
            'trip_id' => $this->tripA1->id,
            'expense_category' => TripExpense::CAT_FOOD,
            'amount' => 1200.00,
            'status' => TripExpense::STATUS_SUBMITTED,
        ]);
        $expenseService->approveExpense($expense);
        $expenseService->postExpense($expense);

        // Attempt duplicate posting -> Throws LogicException
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Duplicate Posting Prevented');
        $expenseService->postExpense($expense);
    }

    public function test_can_reject_submitted_expense_with_reason(): void
    {
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setTenantContext($this->companyA, $this->ownerA);

        $expenseService = app(TripExpenseService::class);

        $expense = $expenseService->recordExpense($this->companyA, [
            'trip_id' => $this->tripA1->id,
            'expense_category' => TripExpense::CAT_POLICE_FINE,
            'amount' => 5000.00,
            'status' => TripExpense::STATUS_SUBMITTED,
        ]);

        $expenseService->rejectExpense($expense, 'Driver traffic violation is not eligible for company reimbursement.');

        $expense->refresh();
        $this->assertEquals(TripExpense::STATUS_REJECTED, $expense->status);
        $this->assertEquals('Driver traffic violation is not eligible for company reimbursement.', $expense->rejection_reason);
    }

    public function test_workshop_job_card_lifecycle_and_vehicle_downtime(): void
    {
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setTenantContext($this->companyA, $this->ownerA);

        $maintService = app(VehicleMaintenanceService::class);

        // 1. Create Job Card -> Vehicle enters Maintenance downtime
        $jobCard = $maintService->createJobCard($this->companyA, [
            'vehicle_id' => $this->vehicleA1->id,
            'workshop_type' => JobCard::WORKSHOP_IN_HOUSE,
            'service_type' => JobCard::SERVICE_PREVENTIVE,
            'priority' => 'high',
            'odometer_km' => 102000,
            'labour_charges' => 4500.00,
            'issue_description' => 'Brake shoe replacement and hub greasing',
        ]);

        $this->assertInstanceOf(JobCard::class, $jobCard);
        $this->assertStringStartsWith('JC-', $jobCard->job_card_number);
        $this->assertEquals(JobCard::STATUS_IN_PROGRESS, $jobCard->status);

        // Vehicle status is placed into 'maintenance'
        $this->vehicleA1->refresh();
        $this->assertEquals(Vehicle::STATUS_MAINTENANCE, $this->vehicleA1->status);

        // 2. Add Spare Parts to Job Card
        $part = $maintService->addJobCardPart($jobCard, [
            'part_name' => 'Heavy Duty Brake Lining Set',
            'part_number' => 'BRK-HINO-88',
            'quantity' => 2,
            'unit_cost' => 8500.00,
        ]);
        $this->assertEquals(17000.00, (float) $part->total_cost);

        $jobCard->refresh();
        $this->assertEquals(17000.00, (float) $jobCard->parts_total_cost);
        $this->assertEquals(21500.00, (float) $jobCard->total_cost); // 17,000 + 4,500 labour

        // 3. Complete Job Card -> Vehicle returned to available & downtime logged
        $maintService->completeJobCard($jobCard, [
            'completed_at' => now()->toIso8601String(),
            'work_performed' => 'Brake linings installed and adjusted. Greasing done.',
        ]);

        $jobCard->refresh();
        $this->assertEquals(JobCard::STATUS_COMPLETED, $jobCard->status);
        $this->assertGreaterThanOrEqual(0.5, (float) $jobCard->downtime_hours);

        // Vehicle restored to available
        $this->vehicleA1->refresh();
        $this->assertEquals(Vehicle::STATUS_AVAILABLE, $this->vehicleA1->status);
        $this->assertEquals(102000, $this->vehicleA1->current_odometer);

        // Printable Job Card Route
        $response = $this->get(route('print.job-card', $jobCard->id));
        $response->assertStatus(200);
        $response->assertSee('COMPLETED');
        $response->assertSee('Heavy Duty Brake Lining Set');
        $response->assertSee('21,500.00');
    }

    public function test_preventive_maintenance_schedule_evaluates_due_status(): void
    {
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setTenantContext($this->companyA, $this->ownerA);

        $maintService = app(VehicleMaintenanceService::class);

        // Vehicle odo is 100,000. Schedule every 10,000 KM (due at 110,000, lead 500 KM)
        $schedule = $maintService->createSchedule($this->companyA, [
            'vehicle_id' => $this->vehicleA1->id,
            'title' => 'Engine Oil & Filter Change',
            'maintenance_category' => MaintenanceSchedule::CAT_OIL_CHANGE,
            'interval_km' => 10000,
            'alert_lead_km' => 500,
        ]);

        // When odo is 105,000 -> Active
        $this->assertEquals(MaintenanceSchedule::STATUS_ACTIVE, $schedule->evaluateStatus(105000));

        // When odo is 109,600 (within 500 KM lead) -> Due
        $this->assertEquals(MaintenanceSchedule::STATUS_DUE, $schedule->evaluateStatus(109600));

        // When odo reaches 110,200 -> Overdue
        $this->assertEquals(MaintenanceSchedule::STATUS_OVERDUE, $schedule->evaluateStatus(110200));

        // Evaluate fleet alerts -> Generates alert
        $this->vehicleA1->update(['current_odometer_km' => 110200]);
        $maintService->evaluateFleetAlerts($this->companyA);

        $alert = MaintenanceAlert::where('vehicle_id', $this->vehicleA1->id)
            ->where('alert_type', MaintenanceAlert::TYPE_OIL_CHANGE_DUE)
            ->first();
        $this->assertNotNull($alert);
        $this->assertEquals(MaintenanceAlert::SEVERITY_CRITICAL, $alert->severity);
    }

    public function test_can_install_and_track_vehicle_components_and_life_limit(): void
    {
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setTenantContext($this->companyA, $this->ownerA);

        $maintService = app(VehicleMaintenanceService::class);

        // Install Tyre on Front Left with 80,000 KM life at odo 100,000
        $component = $maintService->installComponent($this->companyA, [
            'vehicle_id' => $this->vehicleA1->id,
            'component_type' => VehicleComponent::TYPE_TYRE,
            'brand_name' => 'Bridgestone R168',
            'position' => 'front_left',
            'installed_odometer_km' => 100000,
            'expected_life_km' => 80000,
        ]);

        $this->assertInstanceOf(VehicleComponent::class, $component);
        $this->assertEquals(0, $component->getUsageKm(100000));
        $this->assertEquals(25000, $component->getUsageKm(125000));
        $this->assertFalse($component->isLifeExceeded(150000));

        // When vehicle reaches 185,000 KM (+85,000 KM) -> Life exceeded!
        $this->assertTrue($component->isLifeExceeded(185000));

        // Replace component
        $maintService->replaceComponent($component, [
            'removed_odometer_km' => 185000,
            'status' => VehicleComponent::STATUS_REPLACED,
            'notes' => 'Tread depth below legal 1.6mm limit',
        ]);

        $component->refresh();
        $this->assertEquals(VehicleComponent::STATUS_REPLACED, $component->status);
        $this->assertEquals(85000, $component->getUsageKm(190000));
    }

    public function test_accounting_integration_payloads_are_balanced_and_idempotent(): void
    {
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setTenantContext($this->companyA, $this->ownerA);

        $fuelService = app(FuelManagementService::class);
        $expenseService = app(TripExpenseService::class);
        $maintService = app(VehicleMaintenanceService::class);
        $accounting = app(AccountingIntegrationService::class);

        // 1. Fuel Accounting
        $fuel = $fuelService->recordFuelEntry($this->companyA, [
            'vehicle_id' => $this->vehicleA1->id,
            'quantity_liters' => 100,
            'rate_per_liter' => 280,
            'odometer_km' => 100400,
        ]);
        $fuelPayload = $accounting->buildFuelAccountingPayload($fuel);
        $this->assertStringStartsWith('FUEL-', $fuelPayload['idempotency_key']);
        $totalDebit = array_sum(array_column($fuelPayload['entries'], 'debit'));
        $totalCredit = array_sum(array_column($fuelPayload['entries'], 'credit'));
        $this->assertEquals(28000.00, $totalDebit);
        $this->assertEquals(28000.00, $totalCredit);

        // 2. Trip Expense Accounting
        $expense = $expenseService->recordExpense($this->companyA, [
            'expense_category' => TripExpense::CAT_PARKING,
            'amount' => 500.00,
        ]);
        $expPayload = $accounting->buildTripExpenseAccountingPayload($expense);
        $this->assertStringStartsWith('EXPENSE-', $expPayload['idempotency_key']);
        $this->assertEquals(500.00, array_sum(array_column($expPayload['entries'], 'debit')));
        $this->assertEquals(500.00, array_sum(array_column($expPayload['entries'], 'credit')));

        // 3. Maintenance Job Card Accounting
        $jc = $maintService->createJobCard($this->companyA, [
            'vehicle_id' => $this->vehicleA1->id,
            'labour_charges' => 2000.00,
            'odometer_km' => 100500,
        ]);
        $maintService->addJobCardPart($jc, ['part_name' => 'Oil Filter', 'quantity' => 1, 'unit_cost' => 1500.00]);
        $maintService->completeJobCard($jc, []);

        $jcPayload = $accounting->buildMaintenanceAccountingPayload($jc);
        $this->assertStringStartsWith('MAINT-', $jcPayload['idempotency_key']);
        $this->assertEquals(3500.00, array_sum(array_column($jcPayload['entries'], 'debit')));
        $this->assertEquals(3500.00, array_sum(array_column($jcPayload['entries'], 'credit')));
    }

    public function test_cross_tenant_isolation_prevents_unauthorized_access(): void
    {
        // Company A records fuel entry & expense
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setTenantContext($this->companyA, $this->ownerA);

        $fuelService = app(FuelManagementService::class);
        $expenseService = app(TripExpenseService::class);
        $maintService = app(VehicleMaintenanceService::class);

        $fuelA = $fuelService->recordFuelEntry($this->companyA, [
            'vehicle_id' => $this->vehicleA1->id,
            'quantity_liters' => 50,
            'rate_per_liter' => 280,
            'odometer_km' => 100200,
        ]);

        $expA = $expenseService->recordExpense($this->companyA, [
            'expense_category' => TripExpense::CAT_FOOD,
            'amount' => 800.00,
        ]);

        $jcA = $maintService->createJobCard($this->companyA, [
            'vehicle_id' => $this->vehicleA1->id,
            'odometer_km' => 100200,
        ]);

        // Switch to Company B
        $this->actingAs($this->ownerB);
        app(TenantContextService::class)->setTenantContext($this->companyB, $this->ownerB);

        // Accessing Company A print views as User B returns 403 Forbidden
        $this->get(route('print.fuel-slip', $fuelA->id))->assertStatus(403);
        $this->get(route('print.expense-voucher', $expA->id))->assertStatus(403);
        $this->get(route('print.job-card', $jcA->id))->assertStatus(403);
    }

    public function test_livewire_fuel_expense_and_workshop_components(): void
    {
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setTenantContext($this->companyA, $this->ownerA);

        // 1. FuelManager Component
        Livewire::test(FuelManager::class)
            ->assertStatus(200)
            ->assertSee('Fuel Management');

        // 2. TripExpenseManager Component
        Livewire::test(TripExpenseManager::class)
            ->assertStatus(200)
            ->assertSee('Operational Expenses');

        // 3. WorkshopManager Component
        Livewire::test(WorkshopManager::class)
            ->assertStatus(200)
            ->assertSee('Vehicle Maintenance');
    }
}

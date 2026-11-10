<?php

namespace Tests\Feature;

use App\Livewire\Operations\DeliveryManager;
use App\Livewire\Operations\TripManager;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Consignment;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\ProofOfDelivery;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\Trip;
use App\Models\TripCheckpoint;
use App\Models\TripConsignment;
use App\Models\TripResource;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\DeliveryManagementService;
use App\Services\TenantContextService;
use App\Services\TripDispatchService;
use App\Services\TripPlanningService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class TripDispatchDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected Company $companyB;
    protected User $ownerA;
    protected User $ownerB;
    protected Branch $branchA;
    protected Branch $branchB;
    protected Customer $customerA;
    protected Customer $customerB;
    protected Booking $bookingA;
    protected Consignment $consignmentA1;
    protected Consignment $consignmentA2;
    protected Vehicle $vehicleA1;
    protected Vehicle $vehicleA2;
    protected Driver $driverA1;
    protected Driver $driverA2;

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

        // 2. Subscription Plan
        $plan = SubscriptionPlan::create([
            'name' => 'Enterprise Logistics Plan',
            'slug' => 'enterprise-logistics',
            'description' => 'Logistics operations plan',
            'monthly_price' => 25000.00,
            'quarterly_price' => 70000.00,
            'annual_price' => 250000.00,
            'max_vehicles' => 100,
            'max_users' => 50,
            'max_branches' => 10,
            'max_storage_mb' => 20000,
            'features' => ['all'],
            'is_active' => true,
        ]);

        $adminRole = Role::where('slug', 'tenant_admin')->first();

        // 3. Tenant A Setup
        $this->companyA = Company::create([
            'name' => 'Khyber Freight Line',
            'legal_name' => 'Khyber Freight Line Ltd',
            'code' => 'KFL',
            'slug' => 'khyber-freight-line',
            'status' => 'active',
            'plan_id' => $plan->id,
            'currency' => 'PKR',
            'timezone' => 'Asia/Karachi',
        ]);

        $this->branchA = Branch::create([
            'company_id' => $this->companyA->id,
            'name' => 'Karachi South Hub',
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
            'name' => 'Tariq Khan',
            'email' => 'tariq@khyberfreight.test',
            'password' => Hash::make('Secret123!'),
            'status' => 'active',
            'is_super_admin' => false,
        ]);

        $this->companyA->update(['owner_id' => $this->ownerA->id]);

        // Fleet Resources for Company A
        $this->vehicleA1 = Vehicle::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'registration_number' => 'TLB-9988',
            'ownership_type' => Vehicle::OWNERSHIP_COMPANY,
            'status' => Vehicle::STATUS_AVAILABLE,
            'make_model' => 'Hino 700 Prime Mover',
            'capacity_tons' => 35.0,
            'current_odometer' => 120500,
        ]);

        $this->vehicleA2 = Vehicle::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'registration_number' => 'TRL-5544',
            'ownership_type' => Vehicle::OWNERSHIP_COMPANY,
            'status' => Vehicle::STATUS_AVAILABLE,
            'make_model' => '40ft Tri-Axle Semi Trailer',
            'capacity_tons' => 40.0,
            'current_odometer' => 85000,
        ]);

        $this->driverA1 = Driver::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'name' => 'Muhammad Rafique',
            'cnic_number' => '42201-1122334-1',
            'phone' => '0300-9988771',
            'license_number' => 'HTV-KHI-8877',
            'license_expiry_date' => now()->addYears(2)->toDateString(),
            'status' => Driver::STATUS_AVAILABLE,
        ]);

        $this->driverA2 = Driver::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'name' => 'Gul Sher Khan',
            'cnic_number' => '42201-5566778-3',
            'phone' => '0300-3344556',
            'license_number' => 'HTV-KHI-3344',
            'license_expiry_date' => now()->addYears(2)->toDateString(),
            'status' => Driver::STATUS_AVAILABLE,
        ]);

        $this->customerA = Customer::create([
            'company_id' => $this->companyA->id,
            'name' => 'Indus Sugar Mills',
            'phone' => '021-34567890',
            'status' => 'active',
        ]);

        $this->bookingA = Booking::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'customer_id' => $this->customerA->id,
            'booking_number' => 'BK-2026-00001',
            'booking_date' => now()->toDateString(),
            'origin_city' => 'Karachi',
            'destination_city' => 'Lahore',
            'total_freight' => 120000.00,
            'net_amount' => 120000.00,
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        $this->consignmentA1 = Consignment::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'booking_id' => $this->bookingA->id,
            'customer_id' => $this->customerA->id,
            'consignment_number' => 'CN-2026-00001',
            'consignment_date' => now()->toDateString(),
            'pickup_point' => 'Karachi Port',
            'delivery_point' => 'Lahore Warehouse',
            'consignee_name' => 'Lahore Wholesale Distributors',
            'consignee_city' => 'Lahore',
            'consignee_phone' => '042-35551122',
            'cargo_description' => 'Sugar 50kg Bags',
            'packaging_type' => 'bags',
            'total_quantity' => 400,
            'dispatched_quantity' => 0,
            'delivered_quantity' => 0,
            'remaining_quantity' => 400,
            'chargeable_weight_kg' => 20000.00,
            'freight_charge' => 80000.00,
            'total_charges' => 80000.00,
            'payment_mode' => 'to_pay',
            'status' => Consignment::STATUS_BOOKED,
        ]);

        $this->consignmentA2 = Consignment::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'booking_id' => $this->bookingA->id,
            'customer_id' => $this->customerA->id,
            'consignment_number' => 'CN-2026-00002',
            'consignment_date' => now()->toDateString(),
            'pickup_point' => 'Karachi Port',
            'delivery_point' => 'Lahore Retail Depot',
            'consignee_name' => 'Punjab Traders',
            'consignee_city' => 'Lahore',
            'consignee_phone' => '042-37778899',
            'cargo_description' => 'Sugar 50kg Bags Batch 2',
            'packaging_type' => 'bags',
            'total_quantity' => 200,
            'dispatched_quantity' => 0,
            'delivered_quantity' => 0,
            'remaining_quantity' => 200,
            'chargeable_weight_kg' => 10000.00,
            'freight_charge' => 40000.00,
            'total_charges' => 40000.00,
            'payment_mode' => 'to_pay',
            'status' => Consignment::STATUS_BOOKED,
        ]);

        // 4. Tenant B Setup
        $this->companyB = Company::create([
            'name' => 'Balochistan Express Logistics',
            'legal_name' => 'Balochistan Express Ltd',
            'code' => 'BEL',
            'slug' => 'balochistan-express',
            'status' => 'active',
            'plan_id' => $plan->id,
            'currency' => 'PKR',
            'timezone' => 'Asia/Karachi',
        ]);

        $this->branchB = Branch::create([
            'company_id' => $this->companyB->id,
            'name' => 'Quetta Main Terminal',
            'code' => 'QTA-01',
            'city' => 'Quetta',
            'is_head_office' => true,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->ownerB = User::create([
            'company_id' => $this->companyB->id,
            'branch_id' => $this->branchB->id,
            'role_id' => $adminRole->id,
            'name' => 'Jamil Mengal',
            'email' => 'jamil@balochistanlogistics.test',
            'password' => Hash::make('Secret123!'),
            'status' => 'active',
            'is_super_admin' => false,
        ]);

        $this->companyB->update(['owner_id' => $this->ownerB->id]);
    }

    public function test_can_plan_trip_with_atomic_sequence_number(): void
    {
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setTenantContext($this->companyA, $this->ownerA);

        $planningService = app(TripPlanningService::class);

        $trip = $planningService->createTrip($this->companyA, [
            'branch_id' => $this->branchA->id,
            'trip_date' => now()->toDateString(),
            'origin_city' => 'Karachi',
            'destination_city' => 'Lahore',
            'planned_route' => 'National Highway N-5 / Motorway M-5',
            'estimated_departure' => now()->addHours(2),
            'estimated_arrival' => now()->addDays(2),
            'trip_notes' => 'Heavy sugar shipment convoy',
        ], $this->ownerA->id);

        $this->assertInstanceOf(Trip::class, $trip);
        $this->assertStringStartsWith('TR-', $trip->trip_number);
        $this->assertEquals($this->companyA->id, $trip->company_id);
        $this->assertEquals('Karachi', $trip->origin_city);
        $this->assertEquals('Lahore', $trip->destination_city);
        $this->assertEquals(Trip::STATUS_PLANNED, $trip->trip_status);
        $this->assertEquals(Trip::FINANCIAL_PENDING, $trip->financial_status);
    }

    public function test_can_allocate_multiple_consignments_and_partial_quantities(): void
    {
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setTenantContext($this->companyA, $this->ownerA);

        $planningService = app(TripPlanningService::class);

        $trip = $planningService->createTrip($this->companyA, [
            'branch_id' => $this->branchA->id,
            'origin_city' => 'Karachi',
            'destination_city' => 'Lahore',
        ]);

        // Allocate first consignment (partial: 250 out of 400 pkgs)
        $alloc1 = $planningService->allocateConsignment($trip, $this->consignmentA1, 250);
        $this->assertEquals(250, $alloc1->assigned_quantity);
        $this->assertEquals(12500.00, (float) $alloc1->weight_kg);

        // Allocate second consignment (full: 200 out of 200 pkgs)
        $alloc2 = $planningService->allocateConsignment($trip, $this->consignmentA2, 200);
        $this->assertEquals(200, $alloc2->assigned_quantity);
        $this->assertEquals(10000.00, (float) $alloc2->weight_kg);

        $trip->refresh();
        $this->assertEquals(2, $trip->total_consignments_count);
        $this->assertEquals(450, $trip->total_packages_count);
        $this->assertEquals(22.50, (float) $trip->total_weight_tons);
    }

    public function test_cannot_allocate_quantity_exceeding_consignment_remaining_stock(): void
    {
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setTenantContext($this->companyA, $this->ownerA);

        $planningService = app(TripPlanningService::class);
        $trip = $planningService->createTrip($this->companyA, [
            'origin_city' => 'Karachi',
            'destination_city' => 'Lahore',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds remaining quantity [400]');

        // Attempt to allocate 500 when only 400 exist
        $planningService->allocateConsignment($trip, $this->consignmentA1, 500);
    }

    public function test_can_assign_vehicles_and_drivers_to_trip(): void
    {
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setTenantContext($this->companyA, $this->ownerA);

        $planningService = app(TripPlanningService::class);
        $trip = $planningService->createTrip($this->companyA, [
            'origin_city' => 'Karachi',
            'destination_city' => 'Lahore',
        ]);

        // Assign prime mover truck
        $resVehicle = $planningService->assignResource(
            $trip,
            TripResource::TYPE_VEHICLE,
            $this->vehicleA1->id,
            TripResource::ROLE_PRIMARY_VEHICLE
        );
        $this->assertEquals(TripResource::TYPE_VEHICLE, $resVehicle->resource_type);
        $this->assertEquals($this->vehicleA1->id, $resVehicle->vehicle_id);

        // Assign semi-trailer
        $resTrailer = $planningService->assignResource(
            $trip,
            TripResource::TYPE_VEHICLE,
            $this->vehicleA2->id,
            TripResource::ROLE_TRAILER
        );
        $this->assertEquals(TripResource::ROLE_TRAILER, $resTrailer->role);

        // Assign primary commercial driver
        $resDriver = $planningService->assignResource(
            $trip,
            TripResource::TYPE_DRIVER,
            $this->driverA1->id,
            TripResource::ROLE_PRIMARY_DRIVER
        );
        $this->assertEquals($this->driverA1->id, $resDriver->driver_id);

        // Assign co-driver
        $resCoDriver = $planningService->assignResource(
            $trip,
            TripResource::TYPE_DRIVER,
            $this->driverA2->id,
            TripResource::ROLE_CO_DRIVER
        );
        $this->assertEquals(TripResource::ROLE_CO_DRIVER, $resCoDriver->role);

        $this->assertEquals(4, $trip->resources()->count());
        $this->assertEquals('TLB-9988', $trip->primaryVehicle()?->registration_number);
        $this->assertEquals('Muhammad Rafique', $trip->primaryDriver()?->name);
    }

    public function test_prevents_vehicle_and_driver_conflicting_assignment_without_override(): void
    {
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setTenantContext($this->companyA, $this->ownerA);

        $planningService = app(TripPlanningService::class);
        $dispatchService = app(TripDispatchService::class);

        // Trip 1
        $trip1 = $planningService->createTrip($this->companyA, [
            'origin_city' => 'Karachi',
            'destination_city' => 'Lahore',
        ]);
        $planningService->allocateConsignment($trip1, $this->consignmentA1, 200);
        $planningService->assignResource($trip1, TripResource::TYPE_VEHICLE, $this->vehicleA1->id, TripResource::ROLE_PRIMARY_VEHICLE);
        $planningService->assignResource($trip1, TripResource::TYPE_DRIVER, $this->driverA1->id, TripResource::ROLE_PRIMARY_DRIVER);

        // Dispatch Trip 1 -> moves to in_transit
        $dispatchService->dispatchTrip($trip1, 120500);

        // Trip 2
        $trip2 = $planningService->createTrip($this->companyA, [
            'origin_city' => 'Karachi',
            'destination_city' => 'Islamabad',
        ]);

        // Attempt to assign the busy vehicle without override -> throws InvalidArgumentException
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('already deployed on an active in-transit trip');
        $planningService->assignResource($trip2, TripResource::TYPE_VEHICLE, $this->vehicleA1->id, TripResource::ROLE_PRIMARY_VEHICLE, false);
    }

    public function test_permits_conflicting_assignment_when_authorized_override_is_recorded(): void
    {
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setTenantContext($this->companyA, $this->ownerA);

        $planningService = app(TripPlanningService::class);
        $dispatchService = app(TripDispatchService::class);

        // Trip 1
        $trip1 = $planningService->createTrip($this->companyA, [
            'origin_city' => 'Karachi',
            'destination_city' => 'Lahore',
        ]);
        $planningService->allocateConsignment($trip1, $this->consignmentA1, 200);
        $planningService->assignResource($trip1, TripResource::TYPE_VEHICLE, $this->vehicleA1->id, TripResource::ROLE_PRIMARY_VEHICLE);
        $dispatchService->dispatchTrip($trip1, 120500);

        // Trip 2 with explicit override
        $trip2 = $planningService->createTrip($this->companyA, [
            'origin_city' => 'Karachi',
            'destination_city' => 'Islamabad',
        ]);

        $res = $planningService->assignResource(
            $trip2,
            TripResource::TYPE_VEHICLE,
            $this->vehicleA1->id,
            TripResource::ROLE_PRIMARY_VEHICLE,
            true,
            'Authorized emergency handover at Sukkur hub.'
        );

        $this->assertTrue($res->override_allowed);
        $this->assertEquals('Authorized emergency handover at Sukkur hub.', $res->override_reason);
    }

    public function test_can_record_loading_verification_and_access_loading_slip(): void
    {
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setTenantContext($this->companyA, $this->ownerA);

        $planningService = app(TripPlanningService::class);
        $trip = $planningService->createTrip($this->companyA, [
            'origin_city' => 'Karachi',
            'destination_city' => 'Lahore',
        ]);
        $planningService->allocateConsignment($trip, $this->consignmentA1, 400);
        $planningService->assignResource($trip, TripResource::TYPE_VEHICLE, $this->vehicleA1->id, TripResource::ROLE_PRIMARY_VEHICLE);
        $planningService->assignResource($trip, TripResource::TYPE_DRIVER, $this->driverA1->id, TripResource::ROLE_PRIMARY_DRIVER);

        $planningService->recordLoading($trip, 'Aslam Supervisor');

        $trip->refresh();
        $this->assertEquals(Trip::STATUS_LOADING, $trip->trip_status);
        $this->assertEquals('Aslam Supervisor', $trip->loading_supervisor);
        $this->assertNotNull($trip->loading_started_at);
        $this->assertNotNull($trip->loading_completed_at);
        $this->assertEquals(TripConsignment::LOADING_LOADED, $trip->tripConsignments()->first()->loading_status);

        // Printable Loading Slip Route Check
        $response = $this->get(route('print.loading-slip', $trip->id));
        $response->assertStatus(200);
        $response->assertSee('LOADING MANIFEST');
        $response->assertSee($trip->trip_number);
        $response->assertSee('Aslam Supervisor');
    }

    public function test_cannot_dispatch_trip_without_allocated_cargo_or_resources(): void
    {
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setTenantContext($this->companyA, $this->ownerA);

        $planningService = app(TripPlanningService::class);
        $dispatchService = app(TripDispatchService::class);

        // Empty trip
        $trip = $planningService->createTrip($this->companyA, [
            'origin_city' => 'Karachi',
            'destination_city' => 'Lahore',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('without any allocated consignments');
        $dispatchService->dispatchTrip($trip, 120500);
    }

    public function test_trip_dispatch_updates_trip_consignments_and_fleet_statuses(): void
    {
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setTenantContext($this->companyA, $this->ownerA);

        $planningService = app(TripPlanningService::class);
        $dispatchService = app(TripDispatchService::class);

        $trip = $planningService->createTrip($this->companyA, [
            'origin_city' => 'Karachi',
            'destination_city' => 'Lahore',
        ]);
        $planningService->allocateConsignment($trip, $this->consignmentA1, 300);
        $planningService->assignResource($trip, TripResource::TYPE_VEHICLE, $this->vehicleA1->id, TripResource::ROLE_PRIMARY_VEHICLE);
        $planningService->assignResource($trip, TripResource::TYPE_DRIVER, $this->driverA1->id, TripResource::ROLE_PRIMARY_DRIVER);

        $dispatchService->dispatchTrip($trip, 120500, now()->toIso8601String(), $this->ownerA->id);

        $trip->refresh();
        $this->assertEquals(Trip::STATUS_IN_TRANSIT, $trip->trip_status);
        $this->assertEquals(120500, $trip->departure_odometer_km);
        $this->assertNotNull($trip->actual_departure);
        $this->assertEquals($this->ownerA->id, $trip->dispatched_by);

        // Vehicle updated to on_trip
        $this->vehicleA1->refresh();
        $this->assertEquals(Vehicle::STATUS_ON_TRIP, $this->vehicleA1->status);

        // Driver updated to on_duty
        $this->driverA1->refresh();
        $this->assertEquals(Driver::STATUS_ON_DUTY, $this->driverA1->status);

        // Consignment remaining and dispatched quantity updated
        $this->consignmentA1->refresh();
        $this->assertEquals(300, $this->consignmentA1->dispatched_quantity);
        $this->assertEquals(100, $this->consignmentA1->remaining_quantity);
        $this->assertEquals(Consignment::STATUS_DISPATCHED_IN_TRANSIT, $this->consignmentA1->status);

        // Printable Trip Sheet Route Check
        $response = $this->get(route('print.trip-sheet', $trip->id));
        $response->assertStatus(200);
        $response->assertSee('OFFICIAL DISPATCH MANIFEST');
        $response->assertSee('TLB-9988');
        $response->assertSee('Muhammad Rafique');
    }

    public function test_can_record_transit_checkpoints_and_route_delays(): void
    {
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setTenantContext($this->companyA, $this->ownerA);

        $planningService = app(TripPlanningService::class);
        $dispatchService = app(TripDispatchService::class);

        $trip = $planningService->createTrip($this->companyA, [
            'origin_city' => 'Karachi',
            'destination_city' => 'Lahore',
        ]);
        $planningService->allocateConsignment($trip, $this->consignmentA1, 200);
        $planningService->assignResource($trip, TripResource::TYPE_VEHICLE, $this->vehicleA1->id, TripResource::ROLE_PRIMARY_VEHICLE);
        $planningService->assignResource($trip, TripResource::TYPE_DRIVER, $this->driverA1->id, TripResource::ROLE_PRIMARY_DRIVER);
        $dispatchService->dispatchTrip($trip, 120500);

        // Milestone 1: Hyderabad
        $cp1 = $dispatchService->recordCheckpoint($trip, 'Hyderabad Toll Plaza', 120660, 0, null, 'Normal transit');
        $this->assertEquals(1, $cp1->stop_order);
        $this->assertEquals(120660, $cp1->odometer_km);
        $this->assertEquals(0, $cp1->delay_minutes);

        // Milestone 2: Sukkur Bypass with delay
        $cp2 = $dispatchService->recordCheckpoint(
            $trip,
            'Rohri / Sukkur Hub',
            121000,
            45,
            'Heavy fog on highway',
            'Driver rested for 30 mins'
        );
        $this->assertEquals(2, $cp2->stop_order);
        $this->assertEquals(45, $cp2->delay_minutes);
        $this->assertEquals('Heavy fog on highway', $cp2->delay_reason);

        $this->assertEquals(2, $trip->checkpoints()->count());
    }

    public function test_can_record_delivery_and_create_proof_of_delivery(): void
    {
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setTenantContext($this->companyA, $this->ownerA);

        $planningService = app(TripPlanningService::class);
        $dispatchService = app(TripDispatchService::class);
        $deliveryService = app(DeliveryManagementService::class);

        $trip = $planningService->createTrip($this->companyA, [
            'origin_city' => 'Karachi',
            'destination_city' => 'Lahore',
        ]);
        $alloc = $planningService->allocateConsignment($trip, $this->consignmentA1, 400);
        $planningService->assignResource($trip, TripResource::TYPE_VEHICLE, $this->vehicleA1->id, TripResource::ROLE_PRIMARY_VEHICLE);
        $planningService->assignResource($trip, TripResource::TYPE_DRIVER, $this->driverA1->id, TripResource::ROLE_PRIMARY_DRIVER);
        $dispatchService->dispatchTrip($trip, 120500);

        // Record Full Sound Delivery
        $pod = $deliveryService->recordDelivery(
            $alloc,
            400,
            0,
            0,
            [
                'receiver_name' => 'Sheikh Naveed',
                'receiver_phone' => '0321-4455667',
                'receiver_cnic' => '35202-9988776-5',
                'delivery_datetime' => now()->toIso8601String(),
                'gps_latitude' => 31.5204,
                'gps_longitude' => 74.3587,
                'status' => ProofOfDelivery::STATUS_VERIFIED,
                'exception_notes' => 'Received all 400 bags intact in sound condition.',
            ],
            $this->ownerA->id
        );

        $this->assertInstanceOf(ProofOfDelivery::class, $pod);
        $this->assertEquals(400, $pod->delivered_quantity);
        $this->assertEquals('Sheikh Naveed', $pod->receiver_name);
        $this->assertEquals(ProofOfDelivery::STATUS_VERIFIED, $pod->status);

        // Allocation updated to delivered full
        $alloc->refresh();
        $this->assertEquals(TripConsignment::DELIVERY_FULL, $alloc->delivery_status);
        $this->assertEquals(400, $alloc->delivered_quantity);

        // Underlying Consignment updated to delivered
        $this->consignmentA1->refresh();
        $this->assertEquals(400, $this->consignmentA1->delivered_quantity);
        $this->assertEquals(Consignment::STATUS_DELIVERED, $this->consignmentA1->status);

        // Trip transitions to delivered because all allocations are delivered
        $trip->refresh();
        $this->assertEquals(Trip::STATUS_DELIVERED, $trip->trip_status);

        // Printable POD Route Check
        $response = $this->get(route('print.pod-receipt', $pod->id));
        $response->assertStatus(200);
        $response->assertSee('ELECTRONIC PROOF OF DELIVERY');
        $response->assertSee('Sheikh Naveed');
        $response->assertSee('400');
    }

    public function test_can_record_partial_damaged_and_short_delivery(): void
    {
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setTenantContext($this->companyA, $this->ownerA);

        $planningService = app(TripPlanningService::class);
        $dispatchService = app(TripDispatchService::class);
        $deliveryService = app(DeliveryManagementService::class);

        $trip = $planningService->createTrip($this->companyA, [
            'origin_city' => 'Karachi',
            'destination_city' => 'Lahore',
        ]);
        $alloc = $planningService->allocateConsignment($trip, $this->consignmentA2, 200);
        $planningService->assignResource($trip, TripResource::TYPE_VEHICLE, $this->vehicleA1->id, TripResource::ROLE_PRIMARY_VEHICLE);
        $planningService->assignResource($trip, TripResource::TYPE_DRIVER, $this->driverA1->id, TripResource::ROLE_PRIMARY_DRIVER);
        $dispatchService->dispatchTrip($trip, 120500);

        // Deliver with damage and shortage: 180 good, 10 damaged, 10 short
        $pod = $deliveryService->recordDelivery(
            $alloc,
            180,
            10,
            10,
            [
                'receiver_name' => 'Kareem Buksh',
                'exception_notes' => '10 bags torn due to rough unloading; 10 bags missing.',
            ]
        );

        $this->assertEquals(180, $pod->delivered_quantity);
        $this->assertEquals(10, $pod->damaged_quantity);
        $this->assertEquals(10, $pod->short_quantity);

        $alloc->refresh();
        $this->assertEquals(TripConsignment::DELIVERY_PARTIAL, $alloc->delivery_status);
        $this->assertEquals(10, $alloc->damaged_quantity);
        $this->assertEquals(10, $alloc->short_quantity);
    }

    public function test_operational_completion_releases_vehicles_and_drivers(): void
    {
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setTenantContext($this->companyA, $this->ownerA);

        $planningService = app(TripPlanningService::class);
        $dispatchService = app(TripDispatchService::class);
        $deliveryService = app(DeliveryManagementService::class);

        $trip = $planningService->createTrip($this->companyA, [
            'origin_city' => 'Karachi',
            'destination_city' => 'Lahore',
        ]);
        $planningService->allocateConsignment($trip, $this->consignmentA1, 400);
        $planningService->assignResource($trip, TripResource::TYPE_VEHICLE, $this->vehicleA1->id, TripResource::ROLE_PRIMARY_VEHICLE);
        $planningService->assignResource($trip, TripResource::TYPE_DRIVER, $this->driverA1->id, TripResource::ROLE_PRIMARY_DRIVER);
        $dispatchService->dispatchTrip($trip, 120500);

        // Operational Completion at Lahore Terminal
        $arrivalOdo = 121750; // 1,250 KM traveled
        $deliveryService->completeOperationally($trip, $arrivalOdo);

        $trip->refresh();
        $this->assertEquals(Trip::STATUS_OPERATIONALLY_COMPLETED, $trip->trip_status);
        $this->assertEquals(121750, $trip->arrival_odometer_km);
        $this->assertEquals(1250, $trip->total_distance_km);
        $this->assertNotNull($trip->operationally_completed_at);

        // Vehicle released back to available & odometer updated
        $this->vehicleA1->refresh();
        $this->assertEquals(Vehicle::STATUS_AVAILABLE, $this->vehicleA1->status);
        $this->assertEquals(121750, $this->vehicleA1->current_odometer);

        // Driver released back to available
        $this->driverA1->refresh();
        $this->assertEquals(Driver::STATUS_AVAILABLE, $this->driverA1->status);

        // Financial status remains pending until financial audit!
        $this->assertEquals(Trip::FINANCIAL_PENDING, $trip->financial_status);
        $this->assertFalse($trip->isFinanciallyClosed());
    }

    public function test_operational_completion_is_distinct_from_financial_closure(): void
    {
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setTenantContext($this->companyA, $this->ownerA);

        $planningService = app(TripPlanningService::class);
        $dispatchService = app(TripDispatchService::class);
        $deliveryService = app(DeliveryManagementService::class);

        $trip = $planningService->createTrip($this->companyA, [
            'origin_city' => 'Karachi',
            'destination_city' => 'Lahore',
        ]);
        $planningService->allocateConsignment($trip, $this->consignmentA1, 400);
        $planningService->assignResource($trip, TripResource::TYPE_VEHICLE, $this->vehicleA1->id, TripResource::ROLE_PRIMARY_VEHICLE);
        $planningService->assignResource($trip, TripResource::TYPE_DRIVER, $this->driverA1->id, TripResource::ROLE_PRIMARY_DRIVER);
        $dispatchService->dispatchTrip($trip, 120500);

        // Cannot financially close before operational completion!
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be operationally completed before financial closure');
        $deliveryService->closeFinancially($trip);
    }

    public function test_can_financially_close_trip_after_operational_completion(): void
    {
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setTenantContext($this->companyA, $this->ownerA);

        $planningService = app(TripPlanningService::class);
        $dispatchService = app(TripDispatchService::class);
        $deliveryService = app(DeliveryManagementService::class);

        $trip = $planningService->createTrip($this->companyA, [
            'origin_city' => 'Karachi',
            'destination_city' => 'Lahore',
        ]);
        $planningService->allocateConsignment($trip, $this->consignmentA1, 400);
        $planningService->assignResource($trip, TripResource::TYPE_VEHICLE, $this->vehicleA1->id, TripResource::ROLE_PRIMARY_VEHICLE);
        $planningService->assignResource($trip, TripResource::TYPE_DRIVER, $this->driverA1->id, TripResource::ROLE_PRIMARY_DRIVER);
        $dispatchService->dispatchTrip($trip, 120500);

        // 1. Operationally complete
        $deliveryService->completeOperationally($trip, 121750);

        // 2. Financially close
        $deliveryService->closeFinancially($trip, 'All diesel fuel slips verified and driver advances deducted.');

        $trip->refresh();
        $this->assertEquals(Trip::STATUS_CLOSED, $trip->trip_status);
        $this->assertEquals(Trip::FINANCIAL_CLOSED, $trip->financial_status);
        $this->assertNotNull($trip->financially_closed_at);
        $this->assertTrue($trip->isFinanciallyClosed());
    }

    public function test_cross_tenant_isolation_prevents_viewing_or_modifying_another_tenants_trip_or_pod(): void
    {
        // 1. Create Trip & POD in Company A
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setTenantContext($this->companyA, $this->ownerA);

        $planningService = app(TripPlanningService::class);
        $dispatchService = app(TripDispatchService::class);
        $deliveryService = app(DeliveryManagementService::class);

        $tripA = $planningService->createTrip($this->companyA, [
            'origin_city' => 'Karachi',
            'destination_city' => 'Lahore',
        ]);
        $allocA = $planningService->allocateConsignment($tripA, $this->consignmentA1, 400);
        $planningService->assignResource($tripA, TripResource::TYPE_VEHICLE, $this->vehicleA1->id, TripResource::ROLE_PRIMARY_VEHICLE);
        $planningService->assignResource($tripA, TripResource::TYPE_DRIVER, $this->driverA1->id, TripResource::ROLE_PRIMARY_DRIVER);
        $dispatchService->dispatchTrip($tripA, 120500);

        $podA = $deliveryService->recordDelivery($allocA, 400, 0, 0, ['receiver_name' => 'Company A Receiver']);

        // 2. Switch to Tenant B (Company B)
        $this->actingAs($this->ownerB);
        app(TenantContextService::class)->setTenantContext($this->companyB, $this->ownerB);

        // Attempt to print Company A's loading slip as User B -> 403 Forbidden
        $response = $this->get(route('print.loading-slip', $tripA->id));
        $response->assertStatus(403);

        // Attempt to print Company A's trip sheet as User B -> 403 Forbidden
        $response = $this->get(route('print.trip-sheet', $tripA->id));
        $response->assertStatus(403);

        // Attempt to print Company A's POD receipt as User B -> 403 Forbidden
        $response = $this->get(route('print.pod-receipt', $podA->id));
        $response->assertStatus(403);
    }

    public function test_livewire_trip_manager_and_delivery_manager_components(): void
    {
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setTenantContext($this->companyA, $this->ownerA);

        // Test TripManager Livewire Component
        Livewire::test(TripManager::class)
            ->set('origin_city', 'Karachi')
            ->set('destination_city', 'Peshawar')
            ->set('trip_date', now()->toDateString())
            ->call('createTrip')
            ->assertHasNoErrors()
            ->assertSee('Peshawar');

        // Test DeliveryManager Livewire Component
        Livewire::test(DeliveryManager::class)
            ->assertStatus(200)
            ->assertSee('Electronic Proof of Delivery');
    }
}

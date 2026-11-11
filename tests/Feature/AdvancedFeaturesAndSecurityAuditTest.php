<?php

namespace Tests\Feature;

use App\Contracts\GpsProviderInterface;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Consignment;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\DatabaseBackup;
use App\Models\Driver;
use App\Models\Geofence;
use App\Models\GeofenceEvent;
use App\Models\GpsDevice;
use App\Models\NotificationLog;
use App\Models\NotificationSetting;
use App\Models\OwnerSettlement;
use App\Models\ProofOfDelivery;
use App\Models\Trip;
use App\Models\TripConsignment;
use App\Models\TripResource;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleLocation;
use App\Models\VehicleOwner;
use App\Services\Gps\GenericGpsProvider;
use App\Services\Gps\TeltonikaGpsProvider;
use App\Services\Gps\TraccarGpsProvider;
use App\Services\GpsTrackingService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AdvancedFeaturesAndSecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Branch $branch;
    protected User $adminUser;
    protected User $driverUser;
    protected User $customerUser;
    protected User $ownerUser;
    protected Vehicle $vehicle;
    protected Driver $driver;
    protected Customer $customer;
    protected VehicleOwner $owner;
    protected \App\Models\Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Apex Logistics Corp',
            'code' => 'APEX',
            'slug' => 'apex',
            'status' => 'active',
        ]);

        $this->branch = Branch::create([
            'company_id' => $this->company->id,
            'name' => 'Karachi Hub',
            'code' => 'KHI-01',
            'city' => 'Karachi',
            'is_head_office' => true,
        ]);

        $this->vehicle = Vehicle::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'registration_number' => 'TLR-1001',
            'fleet_number' => 'FL-1001',
            'ownership_type' => Vehicle::OWNERSHIP_COMPANY,
            'vehicle_category' => 'HCV',
            'make' => 'Hino',
            'model' => '500',
            'manufacturing_year' => 2022,
            'chassis_number' => 'CH-1001',
            'engine_number' => 'ENG-1001',
            'payload_capacity_tons' => 40.0,
            'fuel_type' => 'DIESEL',
            'current_odometer_km' => 100000,
            'status' => Vehicle::STATUS_AVAILABLE,
        ]);

        $this->driver = Driver::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'name' => 'Jamil Ahmed',
            'employee_code' => 'DRV-JAM',
            'cnic_number' => '42101-9988776-3',
            'phone' => '+92-300-9988112',
            'license_number' => 'KHI-HTV-7766',
            'license_expiry_date' => now()->addYears(2)->toDateString(),
            'status' => Driver::STATUS_AVAILABLE,
        ]);

        $this->customer = Customer::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'name' => 'Unilever Pakistan',
            'code' => 'CUST-UNI',
            'phone' => '021-38887766',
            'credit_limit' => 2000000.0,
            'is_active' => true,
        ]);

        $this->owner = VehicleOwner::create([
            'company_id' => $this->company->id,
            'name' => 'Malik Transport Services',
            'code' => 'OWN-MALIK',
            'phone' => '0333-1122334',
            'is_active' => true,
        ]);

        $this->adminUser = User::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'name' => 'Apex Admin',
            'email' => 'admin@apex.com',
            'password' => bcrypt('password123'),
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->driverUser = User::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'driver_id' => $this->driver->id,
            'name' => 'Jamil Driver',
            'email' => 'jamil.driver@apex.com',
            'password' => bcrypt('password123'),
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->customerUser = User::create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'name' => 'Unilever Rep',
            'email' => 'portal@unilever.com',
            'password' => bcrypt('password123'),
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->ownerUser = User::create([
            'company_id' => $this->company->id,
            'vehicle_owner_id' => $this->owner->id,
            'name' => 'Malik Owner',
            'email' => 'owner@malik.com',
            'password' => bcrypt('password123'),
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->booking = \App\Models\Booking::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'booking_number' => 'BK-APEX-01',
            'booking_date' => now()->toDateString(),
            'origin_city' => 'Karachi',
            'destination_city' => 'Hyderabad',
            'total_freight' => 50000.0,
            'net_amount' => 50000.0,
            'status' => 'confirmed',
        ]);
    }

    /**
     * 1. Test GPS Provider Adapters and Telemetry Ingestion.
     */
    public function test_gps_provider_adapters_and_telemetry_normalization(): void
    {
        $gpsService = app(GpsTrackingService::class);

        // Traccar Provider
        $traccar = $gpsService->resolveProvider('traccar');
        $this->assertInstanceOf(TraccarGpsProvider::class, $traccar);
        $normalizedTraccar = $traccar->normalizeTelemetry([
            'id' => 991,
            'latitude' => 24.8607,
            'longitude' => 67.0011,
            'speed' => 15.5, // knots -> ~28.7 km/h
            'course' => 180,
            'attributes' => ['ignition' => true, 'totalDistance' => 105000],
        ]);
        $this->assertEquals(24.8607, $normalizedTraccar['latitude']);
        $this->assertTrue($normalizedTraccar['speed_kmh'] > 20);

        // Teltonika Provider
        $teltonika = $gpsService->resolveProvider('teltonika');
        $this->assertInstanceOf(TeltonikaGpsProvider::class, $teltonika);
        $normalizedTeltonika = $teltonika->normalizeTelemetry([
            'lat' => 25.3960,
            'lng' => 68.3578,
            'speed' => 65,
            'angle' => 90,
            'ignition' => true,
        ]);
        $this->assertEquals(25.3960, $normalizedTeltonika['latitude']);
        $this->assertEquals(65.0, $normalizedTeltonika['speed_kmh']);

        // Ingest into database
        $loc = $gpsService->recordLocation([
            'company_id' => $this->company->id,
            'vehicle_id' => $this->vehicle->id,
            'latitude' => 24.8607,
            'longitude' => 67.0011,
            'speed_kmh' => 45.0,
            'heading_degrees' => 90.0,
            'ignition_status' => true,
            'recorded_at' => now(),
        ]);
        $this->assertDatabaseHas('vehicle_locations', ['id' => $loc->id, 'vehicle_id' => $this->vehicle->id]);
    }

    /**
     * 2. Test Geofencing Mathematical Entry/Exit Evaluation.
     */
    public function test_geofencing_entry_and_exit_triggers(): void
    {
        $gpsService = app(GpsTrackingService::class);

        // Geofence at Karachi Port: 24.8000, 66.9800 with radius 500 meters
        $geofence = Geofence::create([
            'company_id' => $this->company->id,
            'name' => 'Karachi Port Terminal',
            'fence_type' => Geofence::TYPE_CIRCLE,
            'center_latitude' => 24.8000,
            'center_longitude' => 66.9800,
            'radius_meters' => 500.0,
            'trigger_on_entry' => true,
            'trigger_on_exit' => true,
            'is_active' => true,
        ]);

        // Point inside geofence (approx 100 meters away)
        $insideLat = 24.8005;
        $insideLng = 26.9800; // wait, 66.9805
        $insideLng = 66.9805;
        $this->assertTrue($geofence->containsPoint($insideLat, $insideLng));

        // Point outside geofence (20 km away)
        $outsideLat = 24.9500;
        $outsideLng = 67.1500;
        $this->assertFalse($geofence->containsPoint($outsideLat, $outsideLng));

        // Test Geofence Trigger Execution
        $locInside = VehicleLocation::create([
            'company_id' => $this->company->id,
            'vehicle_id' => $this->vehicle->id,
            'latitude' => $insideLat,
            'longitude' => $insideLng,
            'recorded_at' => now(),
        ]);
        $events = $gpsService->checkGeofenceTriggers($locInside);
        $this->assertCount(1, $events);
        $this->assertEquals(GeofenceEvent::EVENT_ENTER, $events[0]->event_type);
    }

    /**
     * 3. Test Mobile Driver Portal Complete Interaction Flow.
     */
    public function test_mobile_driver_portal_workflow(): void
    {
        $this->actingAs($this->driverUser);

        $trip = Trip::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'trip_number' => 'TRP-MOB-01',
            'trip_date' => now()->toDateString(),
            'origin_city' => 'Karachi',
            'destination_city' => 'Hyderabad',
            'trip_status' => Trip::STATUS_PLANNED,
            'vehicle_id' => $this->vehicle->id,
            'driver_id' => $this->driver->id,
        ]);

        TripResource::create([
            'company_id' => $this->company->id,
            'trip_id' => $trip->id,
            'resource_type' => 'vehicle',
            'role' => 'primary_vehicle',
            'vehicle_id' => $this->vehicle->id,
            'is_active' => true,
        ]);

        TripResource::create([
            'company_id' => $this->company->id,
            'trip_id' => $trip->id,
            'resource_type' => 'driver',
            'role' => 'primary_driver',
            'driver_id' => $this->driver->id,
            'is_active' => true,
        ]);

        $consignment = Consignment::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'booking_id' => $this->booking->id,
            'consignment_number' => 'CN-MOB-01',
            'consignment_date' => now()->toDateString(),
            'origin_city' => 'Karachi',
            'destination_city' => 'Hyderabad',
            'consignee_name' => 'Metro Cash & Carry',
            'consignee_phone' => '022-3888111',
            'consignee_city' => 'Hyderabad',
            'cargo_description' => 'Household FMCG',
            'total_quantity' => 200,
            'remaining_quantity' => 200,
            'weight_kg' => 8000.0,
            'delivery_status' => 'booked',
        ]);

        $alloc = TripConsignment::create([
            'company_id' => $this->company->id,
            'trip_id' => $trip->id,
            'consignment_id' => $consignment->id,
            'assigned_quantity' => 200,
            'weight_kg' => 8000.0,
            'loading_status' => 'loaded',
        ]);

        // A. Dashboard Access
        $response = $this->get(route('mobile.driver.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('TRP-MOB-01');

        // B. Trip Detail
        $detailRes = $this->get(route('mobile.driver.trip.detail', $trip->id));
        $detailRes->assertStatus(200);

        // C. Start Trip
        $startRes = $this->post(route('mobile.driver.trip.start', $trip->id), [
            'departure_odometer_km' => 100000,
        ]);
        $startRes->assertRedirect();
        $this->assertEquals(Trip::STATUS_IN_TRANSIT, $trip->fresh()->trip_status);

        // D. Add Checkpoint
        $cpRes = $this->post(route('mobile.driver.trip.checkpoint', $trip->id), [
            'location_name' => 'Hyderabad Toll Plaza M-9',
            'latitude' => 25.3670,
            'longitude' => 68.3200,
            'notes' => 'Toll paid via M-Tag',
        ]);
        $cpRes->assertRedirect();
        $this->assertDatabaseHas('trip_checkpoints', ['trip_id' => $trip->id, 'checkpoint_name' => 'Hyderabad Toll Plaza M-9']);

        // E. Submit En-route Expense
        $expRes = $this->post(route('mobile.driver.trip.expense', $trip->id), [
            'expense_category' => 'toll_tax',
            'amount' => 850.0,
            'payment_method' => 'cash',
        ]);
        $expRes->assertRedirect();
        $this->assertDatabaseHas('trip_expenses', ['trip_id' => $trip->id, 'amount' => 850.0]);

        // F. Confirm Delivery & POD
        $delivRes = $this->post(route('mobile.driver.trip.deliver', $trip->id), [
            'trip_consignment_id' => $alloc->id,
            'delivered_quantity' => 200,
            'receiver_name' => 'Muhammad Bilal (Store Manager)',
            'receiver_phone' => '0300-5556677',
        ]);
        $delivRes->assertRedirect();
        $this->assertEquals('delivered_full', $alloc->fresh()->delivery_status);

        // G. Complete Trip
        $compRes = $this->post(route('mobile.driver.trip.complete', $trip->id), [
            'arrival_odometer_km' => 100165,
        ]);
        $compRes->assertRedirect(route('mobile.driver.dashboard'));
        $this->assertEquals(Trip::STATUS_OPERATIONALLY_COMPLETED, $trip->fresh()->trip_status);
        $this->assertEquals(165, $trip->fresh()->total_distance_km);

        // H. GPS Sync from Mobile
        $gpsRes = $this->postJson(route('mobile.driver.location'), [
            'latitude' => 25.3960,
            'longitude' => 68.3578,
            'speed_kmh' => 0,
            'trip_id' => $trip->id,
        ]);
        $gpsRes->assertStatus(200);
        $gpsRes->assertJsonFragment(['success' => true]);
    }

    /**
     * 4. Test Customer Portal Access and IDOR Protection.
     */
    public function test_customer_portal_and_idor_protection(): void
    {
        $this->actingAs($this->customerUser);

        // Create consignment for Customer A
        $myConsignment = Consignment::create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'booking_id' => $this->booking->id,
            'consignment_number' => 'CN-UNI-001',
            'consignment_date' => now()->toDateString(),
            'origin_city' => 'Karachi',
            'destination_city' => 'Rahim Yar Khan',
            'consignee_name' => 'Unilever Factory',
            'consignee_phone' => '068-5888111',
            'consignee_city' => 'Rahim Yar Khan',
            'cargo_description' => 'Raw Materials',
            'total_quantity' => 100,
            'remaining_quantity' => 100,
            'weight_kg' => 5000.0,
            'delivery_status' => 'booked',
        ]);

        // Customer Dashboard
        $dashRes = $this->get(route('portal.customer.dashboard'));
        $dashRes->assertStatus(200);
        $dashRes->assertSee('CN-UNI-001');

        // Consignments List
        $listRes = $this->get(route('portal.customer.consignments'));
        $listRes->assertStatus(200);
        $listRes->assertSee('CN-UNI-001');

        // Consignment Detail
        $detailRes = $this->get(route('portal.customer.consignment.detail', $myConsignment->id));
        $detailRes->assertStatus(200);
        $detailRes->assertSee('CN-UNI-001');

        // Invoices
        $invRes = $this->get(route('portal.customer.invoices'));
        $invRes->assertStatus(200);

        // IDOR Attack Simulation: Attempt to view another customer's consignment
        $otherCustomer = Customer::create([
            'company_id' => $this->company->id,
            'name' => 'Procter & Gamble',
            'code' => 'CUST-PNG',
            'phone' => '042-35559900',
            'is_active' => true,
        ]);

        $otherBooking = \App\Models\Booking::create([
            'company_id' => $this->company->id,
            'customer_id' => $otherCustomer->id,
            'booking_number' => 'BK-PNG-01',
            'booking_date' => now()->toDateString(),
            'origin_city' => 'Karachi',
            'destination_city' => 'Lahore',
            'total_freight' => 30000.0,
            'net_amount' => 30000.0,
            'status' => 'confirmed',
        ]);

        $otherConsignment = Consignment::create([
            'company_id' => $this->company->id,
            'customer_id' => $otherCustomer->id,
            'booking_id' => $otherBooking->id,
            'consignment_number' => 'CN-PNG-SECRET',
            'consignment_date' => now()->toDateString(),
            'origin_city' => 'Karachi',
            'destination_city' => 'Lahore',
            'consignee_name' => 'P&G Hub',
            'consignee_phone' => '042-3888222',
            'consignee_city' => 'Lahore',
            'cargo_description' => 'Confidential',
            'total_quantity' => 50,
            'remaining_quantity' => 50,
            'weight_kg' => 2000.0,
            'delivery_status' => 'booked',
        ]);

        $idorRes = $this->get(route('portal.customer.consignment.detail', $otherConsignment->id));
        $idorRes->assertStatus(403); // Strictly blocked with 403 Forbidden
    }

    /**
     * 5. Test Vehicle Owner Portal Access and Authorization Isolation.
     */
    public function test_vehicle_owner_portal_and_settlements(): void
    {
        $this->actingAs($this->ownerUser);

        // Register attached vehicle for this owner
        $attVeh = Vehicle::create([
            'company_id' => $this->company->id,
            'vehicle_owner_id' => $this->owner->id,
            'registration_number' => 'MLK-9988',
            'fleet_number' => 'ATT-MLK',
            'ownership_type' => Vehicle::OWNERSHIP_ATTACHED,
            'vehicle_category' => 'HCV',
            'make' => 'Nissan',
            'model' => 'UD',
            'manufacturing_year' => 2020,
            'chassis_number' => 'CH-MLK',
            'engine_number' => 'ENG-MLK',
            'payload_capacity_tons' => 35.0,
            'fuel_type' => 'DIESEL',
            'current_odometer_km' => 180000,
            'status' => Vehicle::STATUS_AVAILABLE,
        ]);

        // Create settlement
        $mySettlement = OwnerSettlement::create([
            'company_id' => $this->company->id,
            'vehicle_owner_id' => $this->owner->id,
            'vehicle_id' => $attVeh->id,
            'settlement_number' => 'STL-MLK-001',
            'settlement_date' => now()->toDateString(),
            'agreed_owner_payable' => 85000.0,
            'approved_deductions' => 5000.0,
            'advances' => 10000.0,
            'net_payable' => 70000.0,
            'paid_amount' => 40000.0,
            'balance_due' => 30000.0,
            'status' => OwnerSettlement::STATUS_PARTIALLY_PAID,
        ]);

        // Owner Dashboard
        $dashRes = $this->get(route('portal.owner.dashboard'));
        $dashRes->assertStatus(200);
        $dashRes->assertSee('MLK-9988');
        $dashRes->assertSee('STL-MLK-001');

        // Vehicles List
        $vehRes = $this->get(route('portal.owner.vehicles'));
        $vehRes->assertStatus(200);
        $vehRes->assertSee('MLK-9988');

        // Settlements List
        $stlRes = $this->get(route('portal.owner.settlements'));
        $stlRes->assertStatus(200);
        $stlRes->assertSee('STL-MLK-001');

        // Settlement Detail (Authorized)
        $detailRes = $this->get(route('portal.owner.settlement.detail', $mySettlement->id));
        $detailRes->assertStatus(200);
        $detailRes->assertSee('STL-MLK-001');

        // Trips List
        $tripsRes = $this->get(route('portal.owner.trips'));
        $tripsRes->assertStatus(200);

        // IDOR Attack Simulation: Attempt to view another owner's settlement
        $otherOwner = VehicleOwner::create([
            'company_id' => $this->company->id,
            'name' => 'Foreign Owner Transport',
            'code' => 'OWN-FOR',
            'phone' => '0300-9988776',
            'is_active' => true,
        ]);

        $otherSettlement = OwnerSettlement::create([
            'company_id' => $this->company->id,
            'vehicle_owner_id' => $otherOwner->id,
            'vehicle_id' => $this->vehicle->id,
            'settlement_number' => 'STL-SECRET-001',
            'settlement_date' => now()->toDateString(),
            'agreed_owner_payable' => 500000.0,
            'net_payable' => 500000.0,
            'balance_due' => 500000.0,
            'status' => 'approved',
        ]);

        $ownerIdor = $this->get(route('portal.owner.settlement.detail', $otherSettlement->id));
        $ownerIdor->assertStatus(403); // Strictly blocked
    }

    /**
     * 6. Test Multi-Channel Notification Engine and Deliverability.
     */
    public function test_notification_service_fail_safe_dispatch(): void
    {
        $notifService = app(NotificationService::class);

        // Configure setting for company
        NotificationSetting::create([
            'company_id' => $this->company->id,
            'event_type' => NotificationService::EVENT_DOCUMENT_EXPIRY,
            'in_app_enabled' => true,
            'email_enabled' => true,
            'sms_enabled' => true,
            'whatsapp_enabled' => true,
        ]);

        $results = $notifService->notify(
            $this->company->id,
            NotificationService::EVENT_DOCUMENT_EXPIRY,
            'Vehicle Fitness Certificate Expiring',
            'Fitness certificate for TLR-1001 expires in 7 days.',
            [
                'users' => [$this->adminUser->id],
                'emails' => ['fleet@apex.com'],
                'phones' => ['+923001234567'],
            ]
        );

        $this->assertCount(1, $results['in_app']);
        $this->assertCount(1, $results['email']);
        $this->assertCount(1, $results['sms']);
        $this->assertCount(1, $results['whatsapp']);

        // Check logs in database
        $this->assertDatabaseHas('notification_logs', [
            'company_id' => $this->company->id,
            'event_type' => NotificationService::EVENT_DOCUMENT_EXPIRY,
            'channel' => NotificationLog::CHANNEL_IN_APP,
            'status' => NotificationLog::STATUS_SENT,
        ]);
    }

    /**
     * 7. Test Enterprise Security Headers and Protection.
     */
    public function test_security_headers_middleware_enforcement(): void
    {
        $response = $this->get('/health');
        $response->assertStatus(200);

        // Assert essential security headers
        $this->assertEquals('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertEquals('1; mode=block', $response->headers->get('X-XSS-Protection'));
        $this->assertEquals('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        $this->assertStringContainsString("frame-ancestors 'self'", (string)$response->headers->get('Content-Security-Policy'));
    }

    /**
     * 8. Test Production Health Probe and Backup Restoration Engine.
     */
    public function test_health_check_probe_and_backup_cli(): void
    {
        // A. Health check
        $health = $this->get('/health');
        $health->assertStatus(200);
        $health->assertJsonFragment([
            'status' => 'healthy',
            'services' => [
                'database' => 'up',
                'cache' => 'up',
                'storage' => 'writable',
            ],
        ]);

        // B. Database Backup Command with SHA-256
        $exit = Artisan::call('tms:backup-db', ['--retention-days' => 15]);
        $this->assertEquals(0, $exit);

        $backup = DatabaseBackup::where('status', DatabaseBackup::STATUS_VERIFIED)->latest('id')->first();
        $this->assertNotNull($backup);
        $this->assertNotEmpty($backup->checksum_sha256);
        $this->assertTrue(File::exists(storage_path('app/' . $backup->file_path)));

        // C. Database Restore Verification
        $restoreExit = Artisan::call('tms:restore-db', ['--backup-id' => $backup->id, '--dry-run' => true]);
        $this->assertEquals(0, $restoreExit);
    }
}

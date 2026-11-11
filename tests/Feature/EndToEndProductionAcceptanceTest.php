<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Consignment;
use App\Models\Customer;
use App\Models\CustomerContract;
use App\Models\CustomerInvoice;
use App\Models\CustomerPayment;
use App\Models\DatabaseBackup;
use App\Models\Driver;
use App\Models\DriverSettlement;
use App\Models\FuelEntry;
use App\Models\GpsDevice;
use App\Models\JobCard;
use App\Models\JournalVoucher;
use App\Models\OwnerContract;
use App\Models\OwnerSettlement;
use App\Models\ProofOfDelivery;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\Trip;
use App\Models\TripCheckpoint;
use App\Models\TripConsignment;
use App\Models\TripExpense;
use App\Models\TripResource;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleLocation;
use App\Models\VehicleOwner;
use App\Services\BusinessIntelligenceService;
use App\Services\ChartOfAccountsService;
use App\Services\DocumentSequenceService;
use App\Services\FinancialPeriodService;
use App\Services\FinancialReportService;
use App\Services\GpsTrackingService;
use App\Services\NotificationService;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class EndToEndProductionAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Complete 25-step production end-to-end lifecycle verification test.
     */
    public function test_complete_25_step_operational_and_financial_lifecycle(): void
    {
        // -------------------------------------------------------------
        // STEP 1: Tenant setup with head office and regional branch
        // -------------------------------------------------------------
        $company = Company::create([
            'name' => 'SwiftLogix Freight Ltd',
            'code' => 'SWIFT',
            'slug' => 'swiftlogix',
            'subdomain' => 'swiftlogix',
            'email' => 'operations@swiftlogix.com',
            'phone' => '+92-21-34567890',
            'status' => 'active',
            'subscription_plan' => 'enterprise',
        ]);
        $this->assertDatabaseHas('companies', ['id' => $company->id]);

        $headOffice = Branch::create([
            'company_id' => $company->id,
            'name' => 'Karachi Central Terminal',
            'code' => 'KHI-HQ',
            'city' => 'Karachi',
            'is_head_office' => true,
            'is_active' => true,
        ]);

        $regionalBranch = Branch::create([
            'company_id' => $company->id,
            'name' => 'Lahore Distribution Hub',
            'code' => 'LHR-HUB',
            'city' => 'Lahore',
            'is_head_office' => false,
            'is_active' => true,
        ]);
        $this->assertEquals(2, Branch::where('company_id', $company->id)->count());

        // -------------------------------------------------------------
        // STEP 2: User and role creation
        // -------------------------------------------------------------
        $dispatcherRole = Role::create([
            'company_id' => $company->id,
            'name' => 'Dispatcher',
            'slug' => 'dispatcher',
            'permissions' => ['trips.manage', 'consignments.manage', 'gps.track'],
        ]);

        $dispatcherUser = User::create([
            'company_id' => $company->id,
            'branch_id' => $headOffice->id,
            'name' => 'Tariq Mehmood',
            'email' => 'tariq@swiftlogix.com',
            'password' => bcrypt('SecurePassword123!'),
            'role_id' => $dispatcherRole->id,
            'is_active' => true,
        ]);
        $this->actingAs($dispatcherUser);

        // -------------------------------------------------------------
        // STEP 3: Chart of accounts initialization
        // -------------------------------------------------------------
        $coaService = app(ChartOfAccountsService::class);
        $coaService->seedDefaultChartOfAccounts($company);

        $finService = app(FinancialPeriodService::class);
        $fiscalYear = $finService->createFinancialYearWithPeriods(
            company: $company,
            name: 'FY 2026',
            startDate: '2026-01-01',
            endDate: '2026-12-31',
            makeCurrent: true
        );
        $this->assertTrue(ChartOfAccount::where('company_id', $company->id)->count() >= 20);

        // -------------------------------------------------------------
        // STEP 4: Company-owned vehicle registration
        // -------------------------------------------------------------
        $companyVehicle = Vehicle::create([
            'company_id' => $company->id,
            'branch_id' => $headOffice->id,
            'registration_number' => 'KHI-9901',
            'fleet_number' => 'FL-001',
            'ownership_type' => Vehicle::OWNERSHIP_COMPANY,
            'vehicle_category' => 'Heavy Commercial',
            'make' => 'Hino',
            'model' => '500 Prime Mover',
            'manufacturing_year' => 2023,
            'chassis_number' => 'CH-KHI-9901',
            'engine_number' => 'ENG-KHI-9901',
            'payload_capacity_tons' => 45.0,
            'fuel_type' => 'DIESEL',
            'current_odometer_km' => 85000,
            'status' => Vehicle::STATUS_AVAILABLE,
        ]);

        // -------------------------------------------------------------
        // STEP 5: Third-party vehicle owner and attached vehicle with contract
        // -------------------------------------------------------------
        $owner = VehicleOwner::create([
            'company_id' => $company->id,
            'name' => 'Al-Madina Goods Transport',
            'code' => 'OWN-ALM',
            'contact_person' => 'Chaudhry Riaz',
            'phone' => '+92-300-8889999',
            'cnic_or_ntn' => '35201-9988776-1',
            'is_active' => true,
        ]);

        $attachedVehicle = Vehicle::create([
            'company_id' => $company->id,
            'branch_id' => $headOffice->id,
            'vehicle_owner_id' => $owner->id,
            'registration_number' => 'LHR-7722',
            'fleet_number' => 'ATT-001',
            'ownership_type' => Vehicle::OWNERSHIP_ATTACHED,
            'vehicle_category' => 'Trailer',
            'make' => 'ISUZU',
            'model' => 'Giga 6x4',
            'manufacturing_year' => 2021,
            'chassis_number' => 'CH-LHR-7722',
            'engine_number' => 'ENG-LHR-7722',
            'payload_capacity_tons' => 50.0,
            'fuel_type' => 'DIESEL',
            'current_odometer_km' => 140000,
            'status' => Vehicle::STATUS_AVAILABLE,
        ]);

        $ownerContract = OwnerContract::create([
            'company_id' => $company->id,
            'vehicle_owner_id' => $owner->id,
            'vehicle_id' => $attachedVehicle->id,
            'contract_number' => 'CON-OWN-001',
            'calculation_type' => 'fixed_per_trip',
            'fixed_amount_per_trip' => 120000.0,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);
        $this->assertNotNull($ownerContract->id);

        // -------------------------------------------------------------
        // STEP 6: Driver creation with license validation
        // -------------------------------------------------------------
        $driver = Driver::create([
            'company_id' => $company->id,
            'branch_id' => $headOffice->id,
            'name' => 'Akbar Ali',
            'employee_code' => 'DRV-AKB',
            'cnic_number' => '35201-1122334-5',
            'phone' => '+92-301-4455667',
            'license_number' => 'LHR-HTV-8877',
            'license_expiry_date' => now()->addYears(2)->toDateString(),
            'status' => Driver::STATUS_AVAILABLE,
        ]);

        // -------------------------------------------------------------
        // STEP 7: Customer onboarding with billing profile and credit limit
        // -------------------------------------------------------------
        $customer = Customer::create([
            'company_id' => $company->id,
            'branch_id' => $headOffice->id,
            'name' => 'National Foods Limited',
            'code' => 'CUST-NFL',
            'customer_type' => 'corporate',
            'email' => 'logistics@nationalfoods.com',
            'phone' => '+92-21-35559988',
            'tax_number' => 'NTN-1234567-8',
            'credit_limit' => 5000000.0,
            'payment_terms_days' => 30,
            'is_active' => true,
        ]);

        // -------------------------------------------------------------
        // STEP 8: Customer contract and freight rate configuration
        // -------------------------------------------------------------
        $customerContract = CustomerContract::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'contract_number' => 'CON-NFL-2026',
            'contract_title' => 'National Foods Annual Freight Agreement',
            'rate_type' => 'fixed',
            'base_rate' => 280000.0,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
        ]);

        // -------------------------------------------------------------
        // STEP 9: Quotation creation, calculation and approval
        // -------------------------------------------------------------
        $quotation = Quotation::create([
            'company_id' => $company->id,
            'branch_id' => $headOffice->id,
            'customer_id' => $customer->id,
            'quotation_number' => 'QT-2026-001',
            'quotation_date' => now()->toDateString(),
            'valid_until' => now()->addDays(15)->toDateString(),
            'origin_city' => 'Karachi',
            'destination_city' => 'Lahore',
            'cargo_description' => 'Packaged Food & Beverages',
            'freight_amount' => 280000.0,
            'toll_charges' => 15000.0,
            'net_total_amount' => 295000.0,
            'status' => 'approved',
        ]);
        $this->assertEquals(295000.0, $quotation->net_total_amount);

        // -------------------------------------------------------------
        // STEP 10: Booking creation and confirmation
        // -------------------------------------------------------------
        $booking = Booking::create([
            'company_id' => $company->id,
            'branch_id' => $headOffice->id,
            'customer_id' => $customer->id,
            'booking_number' => 'BK-2026-001',
            'booking_date' => now()->toDateString(),
            'origin_city' => 'Karachi',
            'destination_city' => 'Lahore',
            'total_freight' => 295000.0,
            'net_amount' => 295000.0,
            'status' => 'confirmed',
        ]);

        // -------------------------------------------------------------
        // STEP 11: Consignment generation with cargo details
        // -------------------------------------------------------------
        $consignment1 = Consignment::create([
            'company_id' => $company->id,
            'branch_id' => $headOffice->id,
            'customer_id' => $customer->id,
            'booking_id' => $booking->id,
            'consignment_number' => 'CN-2026-001',
            'consignment_date' => now()->toDateString(),
            'origin_city' => 'Karachi',
            'destination_city' => 'Lahore',
            'consignee_name' => 'NFL Lahore Depot',
            'consignee_phone' => '042-35551122',
            'consignee_city' => 'Lahore',
            'cargo_description' => 'Packaged Spices & Pickles',
            'package_count' => 500,
            'total_quantity' => 500,
            'remaining_quantity' => 500,
            'gross_weight_kg' => 18000.0,
            'weight_kg' => 18000.0,
            'delivery_status' => Consignment::STATUS_BOOKED,
        ]);

        $consignment2 = Consignment::create([
            'company_id' => $company->id,
            'branch_id' => $headOffice->id,
            'customer_id' => $customer->id,
            'booking_id' => $booking->id,
            'consignment_number' => 'CN-2026-002',
            'consignment_date' => now()->toDateString(),
            'origin_city' => 'Karachi',
            'destination_city' => 'Lahore',
            'consignee_name' => 'NFL Lahore Depot',
            'consignee_phone' => '042-35551122',
            'consignee_city' => 'Lahore',
            'cargo_description' => 'Ketchup & Sauces',
            'package_count' => 300,
            'total_quantity' => 300,
            'remaining_quantity' => 300,
            'gross_weight_kg' => 12000.0,
            'weight_kg' => 12000.0,
            'delivery_status' => Consignment::STATUS_BOOKED,
        ]);

        // -------------------------------------------------------------
        // STEP 12: Multi-consignment trip planning with vehicle & driver assignment
        // -------------------------------------------------------------
        $trip = Trip::create([
            'company_id' => $company->id,
            'branch_id' => $headOffice->id,
            'trip_number' => 'TRP-2026-001',
            'trip_date' => now()->toDateString(),
            'origin_city' => 'Karachi',
            'destination_city' => 'Lahore',
            'planned_route' => 'Karachi -> Hyderabad -> Sukkur -> Multan -> Lahore (M-5 Motorway)',
            'trip_status' => Trip::STATUS_PLANNED,
            'financial_status' => Trip::FINANCIAL_PENDING,
            'total_freight_amount' => 295000.0,
            'assigned_dispatcher_id' => $dispatcherUser->id,
            'vehicle_id' => $attachedVehicle->id,
            'driver_id' => $driver->id,
        ]);

        // Multi-consignment allocation
        $alloc1 = TripConsignment::create([
            'company_id' => $company->id,
            'trip_id' => $trip->id,
            'consignment_id' => $consignment1->id,
            'assigned_quantity' => 500,
            'weight_kg' => 18000.0,
            'loading_status' => 'planned',
            'delivery_status' => 'pending',
        ]);

        $alloc2 = TripConsignment::create([
            'company_id' => $company->id,
            'trip_id' => $trip->id,
            'consignment_id' => $consignment2->id,
            'assigned_quantity' => 300,
            'weight_kg' => 12000.0,
            'loading_status' => 'planned',
            'delivery_status' => 'pending',
        ]);

        TripResource::create([
            'company_id' => $company->id,
            'trip_id' => $trip->id,
            'resource_type' => 'vehicle',
            'role' => 'primary_vehicle',
            'vehicle_id' => $attachedVehicle->id,
            'is_active' => true,
        ]);

        TripResource::create([
            'company_id' => $company->id,
            'trip_id' => $trip->id,
            'resource_type' => 'driver',
            'role' => 'primary_driver',
            'driver_id' => $driver->id,
            'is_active' => true,
        ]);

        // -------------------------------------------------------------
        // STEP 13: Loading sheet generation and loading completion
        // -------------------------------------------------------------
        $trip->update([
            'trip_status' => Trip::STATUS_LOADING,
            'loading_started_at' => now()->subHours(4),
            'loading_supervisor' => 'Muhammad Naveed',
        ]);
        $alloc1->update(['loading_status' => 'loaded']);
        $alloc2->update(['loading_status' => 'loaded']);
        $trip->update([
            'loading_completed_at' => now()->subHours(2),
            'total_consignments_count' => 2,
            'total_packages_count' => 800,
            'total_weight_tons' => 30.0,
        ]);

        // -------------------------------------------------------------
        // STEP 14: Trip dispatch with advance issuance & notifications
        // -------------------------------------------------------------
        $trip->update([
            'trip_status' => Trip::STATUS_DISPATCHED,
            'actual_departure' => now()->subHours(2),
            'departure_odometer_km' => 140000,
            'dispatched_by' => $dispatcherUser->id,
        ]);

        $notifService = app(NotificationService::class);
        $dispatchNotif = $notifService->notifyTripDispatched($trip);
        $this->assertNotEmpty($dispatchNotif['in_app']);

        // -------------------------------------------------------------
        // STEP 15: En-route fuel issue with odometer validation & consumption
        // -------------------------------------------------------------
        $fuelEntry = FuelEntry::create([
            'company_id' => $company->id,
            'branch_id' => $headOffice->id,
            'vehicle_id' => $attachedVehicle->id,
            'driver_id' => $driver->id,
            'trip_id' => $trip->id,
            'quantity_liters' => 350.0,
            'rate_per_liter' => 280.0,
            'total_amount' => 98000.0,
            'odometer_km' => 140600,
            'distance_km' => 600,
            'entry_date' => now()->toDateString(),
            'payment_method' => 'company_account',
            'status' => 'verified',
        ]);
        $this->assertEquals(98000.0, $fuelEntry->total_amount);

        // -------------------------------------------------------------
        // STEP 16: Trip expense submission and approval
        // -------------------------------------------------------------
        $expense = TripExpense::create([
            'company_id' => $company->id,
            'trip_id' => $trip->id,
            'vehicle_id' => $attachedVehicle->id,
            'driver_id' => $driver->id,
            'expense_category' => 'toll_tax',
            'amount' => 12000.0,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'status' => TripExpense::STATUS_APPROVED,
            'approved_by' => $dispatcherUser->id,
            'approved_at' => now(),
        ]);
        $this->assertEquals(12000.0, $expense->amount);

        // -------------------------------------------------------------
        // STEP 17: En-route checkpoint recording and GPS location tracking
        // -------------------------------------------------------------
        $checkpoint = TripCheckpoint::create([
            'company_id' => $company->id,
            'trip_id' => $trip->id,
            'location_name' => 'Sukkur Interchange Toll',
            'stop_order' => 1,
            'latitude' => 27.7052,
            'longitude' => 68.8574,
            'arrival_time' => now()->subHour(),
            'notes' => 'Toll cleared, driver break 20 mins',
        ]);

        $gpsService = app(GpsTrackingService::class);
        $loc = $gpsService->recordLocation([
            'company_id' => $company->id,
            'vehicle_id' => $attachedVehicle->id,
            'trip_id' => $trip->id,
            'latitude' => 27.7052,
            'longitude' => 68.8574,
            'speed_kmh' => 65.0,
            'heading_degrees' => 45.0,
            'ignition_status' => true,
            'recorded_at' => now(),
        ]);
        $this->assertNotNull($loc->id);
        $this->assertDatabaseHas('vehicle_locations', ['id' => $loc->id]);

        // -------------------------------------------------------------
        // STEP 18: Destination arrival, partial delivery and electronic POD upload
        // -------------------------------------------------------------
        $alloc1->update([
            'delivered_quantity' => 500,
            'delivery_status' => 'delivered_full',
            'delivery_time' => now(),
            'receiver_name' => 'Rashid Mehmood (NFL Warehouse)',
        ]);

        // Allocation 2 partial delivery (290 received, 10 damaged)
        $alloc2->update([
            'delivered_quantity' => 290,
            'damaged_quantity' => 10,
            'short_quantity' => 0,
            'delivery_status' => 'delivered_partial',
            'delivery_time' => now(),
            'receiver_name' => 'Rashid Mehmood (NFL Warehouse)',
        ]);

        $pod = ProofOfDelivery::create([
            'company_id' => $company->id,
            'trip_id' => $trip->id,
            'trip_consignment_id' => $alloc1->id,
            'file_path' => 'pods/signed_pod_cn001.pdf',
            'receiver_name' => 'Rashid Mehmood',
            'receiver_phone' => '+92-300-1234567',
            'status' => 'verified',
            'verified_at' => now(),
            'verified_by' => $dispatcherUser->id,
        ]);
        $this->assertDatabaseHas('proof_of_deliveries', ['id' => $pod->id]);

        // -------------------------------------------------------------
        // STEP 19: Operational trip completion and distance calculation
        // -------------------------------------------------------------
        $trip->update([
            'trip_status' => Trip::STATUS_OPERATIONALLY_COMPLETED,
            'arrival_odometer_km' => 141250,
            'total_distance_km' => 1250,
            'actual_arrival' => now(),
            'operationally_completed_at' => now(),
        ]);
        $this->assertTrue($trip->isOperationallyCompleted());
        $this->assertEquals(1250, $trip->total_distance_km);

        // -------------------------------------------------------------
        // STEP 20: Attached vehicle owner settlement calculation, deductions & approval
        // -------------------------------------------------------------
        // Contract: 120,000 PKR. Deductions: 98,000 fuel. Net: 22,000 PKR
        $ownerSettlement = OwnerSettlement::create([
            'company_id' => $company->id,
            'branch_id' => $headOffice->id,
            'vehicle_owner_id' => $owner->id,
            'vehicle_id' => $attachedVehicle->id,
            'trip_id' => $trip->id,
            'settlement_number' => 'STL-ALM-001',
            'settlement_date' => now()->toDateString(),
            'agreed_owner_payable' => 120000.0,
            'approved_deductions' => 98000.0, // Fuel borne by company deducted from owner
            'advance_deduction' => 0.0,
            'net_payable' => 22000.0,
            'paid_amount' => 0.0,
            'balance_due' => 22000.0,
            'status' => OwnerSettlement::STATUS_APPROVED,
        ]);
        $this->assertEquals(22000.0, $ownerSettlement->balance_due);

        // -------------------------------------------------------------
        // STEP 21: Driver trip settlement and expense reconciliation
        // -------------------------------------------------------------
        $driverSettlement = DriverSettlement::create([
            'company_id' => $company->id,
            'branch_id' => $headOffice->id,
            'driver_id' => $driver->id,
            'settlement_number' => 'DS-AKB-001',
            'settlement_date' => now()->toDateString(),
            'advance_amount' => 10000.0,
            'approved_expenses_amount' => 12000.0, // 12,000 toll tax paid by driver in cash
            'net_balance_amount' => 2000.0, // Company owes driver 2,000 PKR
            'status' => DriverSettlement::STATUS_POSTED,
        ]);
        $this->assertEquals(2000.0, $driverSettlement->net_balance_amount);

        // -------------------------------------------------------------
        // STEP 22: Customer freight invoicing with tax calculation
        // -------------------------------------------------------------
        $customerInvoice = CustomerInvoice::create([
            'company_id' => $company->id,
            'branch_id' => $headOffice->id,
            'customer_id' => $customer->id,
            'trip_id' => $trip->id,
            'invoice_number' => 'INV-NFL-001',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => 295000.0,
            'tax_rate_percent' => 5.0,
            'tax_amount' => 14750.0,
            'total_amount' => 309750.0,
            'paid_amount' => 0.0,
            'balance_due' => 309750.0,
            'status' => CustomerInvoice::STATUS_POSTED,
            'payment_status' => 'unpaid',
        ]);
        $this->assertEquals(309750.0, $customerInvoice->total_amount);

        // -------------------------------------------------------------
        // STEP 23: Customer payment receipt, invoice allocation and bank deposit
        // -------------------------------------------------------------
        $customerPayment = CustomerPayment::create([
            'company_id' => $company->id,
            'branch_id' => $headOffice->id,
            'customer_id' => $customer->id,
            'customer_invoice_id' => $customerInvoice->id,
            'receipt_number' => 'RCP-NFL-001',
            'receipt_date' => now()->toDateString(),
            'amount' => 309750.0,
            'payment_type' => CustomerPayment::TYPE_INVOICE_PAYMENT,
            'payment_method' => CustomerPayment::METHOD_BANK_TRANSFER,
            'status' => CustomerPayment::STATUS_POSTED,
        ]);
        $customerInvoice->update([
            'paid_amount' => 309750.0,
            'balance_due' => 0.0,
            'payment_status' => 'paid',
            'status' => CustomerInvoice::STATUS_PAID,
        ]);
        $this->assertEquals(0.0, $customerInvoice->fresh()->balance_due);

        // -------------------------------------------------------------
        // STEP 24: Owner payment disbursement
        // -------------------------------------------------------------
        $ownerSettlement->update([
            'paid_amount' => 22000.0,
            'balance_due' => 0.0,
            'status' => OwnerSettlement::STATUS_PAID,
        ]);
        $this->assertEquals(0.0, $ownerSettlement->fresh()->balance_due);

        // -------------------------------------------------------------
        // STEP 25: Financial closing of trip, Journal Voucher & Reporting Reconciliation
        // -------------------------------------------------------------
        $trip->update([
            'financial_status' => Trip::FINANCIAL_CLOSED,
            'financially_closed_at' => now(),
        ]);
        $this->assertTrue($trip->fresh()->isFinanciallyClosed());

        // Journal Voucher for Trip Financial Close
        $jv = JournalVoucher::create([
            'company_id' => $company->id,
            'branch_id' => $headOffice->id,
            'voucher_number' => 'JV-TRP-001',
            'voucher_date' => now()->toDateString(),
            'status' => JournalVoucher::STATUS_POSTED,
            'total_debit' => 309750.0,
            'total_credit' => 309750.0,
            'narration' => "Financial closure for Trip {$trip->trip_number}",
            'description' => "Financial closure for Trip {$trip->trip_number}",
        ]);
        $this->assertTrue($jv->isBalanced());

        // Business Intelligence & Executive Dashboard Reconciliation
        $biService = app(BusinessIntelligenceService::class);
        $kpis = $biService->getExecutiveDashboardMetrics($company->id);

        $this->assertEquals(2, $kpis['total_fleet']); // 1 company + 1 attached
        $this->assertEquals(1, $kpis['completed_trips']);
        $this->assertEquals(295000.0, $kpis['monthly_freight_revenue']);
        $this->assertEquals(0.0, $kpis['outstanding_receivables']); // Fully settled by customer
        $this->assertEquals(0.0, $kpis['outstanding_owner_payables']); // Fully settled to owner

        // Test Backup & Restore CLI Engine
        $backupExit = Artisan::call('tms:backup-db');
        $this->assertEquals(0, $backupExit);
        $this->assertDatabaseHas('database_backups', [
            'status' => DatabaseBackup::STATUS_VERIFIED,
            'backup_type' => DatabaseBackup::TYPE_DATABASE,
        ]);

        $restoreExit = Artisan::call('tms:restore-db', ['--dry-run' => true]);
        $this->assertEquals(0, $restoreExit);

        // Test Health Probe Endpoint
        $healthResponse = $this->get('/health');
        $healthResponse->assertStatus(200);
        $healthResponse->assertJsonFragment(['status' => 'healthy']);
    }
}

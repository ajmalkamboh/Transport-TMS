<?php

namespace Tests\Feature;

use App\Livewire\Commercial\BookingManager;
use App\Livewire\Commercial\ConsignmentManager;
use App\Livewire\Commercial\CustomerManager;
use App\Livewire\Commercial\QuotationManager;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Consignment;
use App\Models\Customer;
use App\Models\CustomerContract;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\BookingService;
use App\Services\ConsignmentService;
use App\Services\FreightCalculationService;
use App\Services\QuotationService;
use App\Services\TenantContextService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerCommercialManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected Company $companyB;
    protected User $ownerA;
    protected User $ownerB;
    protected Branch $branchA;
    protected Branch $branchB;
    protected SubscriptionPlan $plan;

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
        $this->plan = SubscriptionPlan::create([
            'name' => 'Commercial Freight Plan',
            'slug' => 'commercial-freight',
            'description' => 'Commercial operations plan',
            'monthly_price' => 25000.00,
            'quarterly_price' => 70000.00,
            'annual_price' => 250000.00,
            'trial_duration_days' => 14,
            'max_vehicles' => 20,
            'max_users' => 15,
            'max_branches' => 5,
            'max_storage_mb' => 4096,
            'features' => ['fleet_management', 'driver_management', 'customer_management', 'booking_management'],
            'is_active' => true,
        ]);

        $adminRole = Role::where('slug', 'tenant_admin')->first();

        // 3. Tenant Company A
        $this->companyA = Company::create([
            'name' => 'Khyber Freight Express',
            'code' => 'KFE',
            'slug' => 'khyber-freight',
            'email' => 'ops@khyberfreight.com',
            'phone' => '+92-91-5544332',
            'currency' => 'PKR',
            'timezone' => 'Asia/Karachi',
            'status' => 'active',
            'plan_id' => $this->plan->id,
        ]);

        $this->branchA = Branch::create([
            'company_id' => $this->companyA->id,
            'code' => 'KFE-LHR',
            'name' => 'Lahore Central Terminal',
            'city' => 'Lahore',
            'is_head_office' => true,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->ownerA = User::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'role_id' => $adminRole->id,
            'name' => 'Malik Aslam',
            'email' => 'aslam@khyberfreight.com',
            'password' => Hash::make('Secret123!'),
            'status' => 'active',
            'is_super_admin' => false,
        ]);

        $this->companyA->owner_id = $this->ownerA->id;
        $this->companyA->save();

        // 4. Tenant Company B
        $this->companyB = Company::create([
            'name' => 'Indus Logistics Ltd',
            'code' => 'ILL',
            'slug' => 'indus-logistics',
            'email' => 'info@induslogistics.com',
            'phone' => '+92-21-3998877',
            'currency' => 'PKR',
            'timezone' => 'Asia/Karachi',
            'status' => 'active',
            'plan_id' => $this->plan->id,
        ]);

        $this->branchB = Branch::create([
            'company_id' => $this->companyB->id,
            'code' => 'ILL-KHI',
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
            'name' => 'Farhan Qureshi',
            'email' => 'farhan@induslogistics.com',
            'password' => Hash::make('Secret123!'),
            'status' => 'active',
            'is_super_admin' => false,
        ]);

        $this->companyB->owner_id = $this->ownerB->id;
        $this->companyB->save();
    }

    /**
     * 1. Test Customer Registration (Corporate & Individual) with Credit Limits
     */
    public function test_customer_registration_corporate_and_individual(): void
    {
        $corp = Customer::create([
            'company_id' => $this->companyA->id,
            'customer_code' => 'CUST-001',
            'customer_type' => Customer::TYPE_CORPORATE,
            'name' => 'Engro Fertilizers Ltd',
            'company_name' => 'Engro Corporation',
            'contact_person' => 'Babar Azam',
            'designation' => 'Logistics Head',
            'phone' => '021-35680000',
            'email' => 'logistics@engro.com',
            'tax_ntn' => '0711223-4',
            'is_tax_exempt' => false,
            'credit_limit' => 2000000.00,
            'credit_days' => 45,
            'payment_terms' => Customer::TERMS_CREDIT_30,
            'status' => Customer::STATUS_ACTIVE,
        ]);

        $indiv = Customer::create([
            'company_id' => $this->companyA->id,
            'customer_code' => 'CUST-002',
            'customer_type' => Customer::TYPE_INDIVIDUAL,
            'name' => 'Chaudhry Munir Trader',
            'phone' => '0300-9988776',
            'is_tax_exempt' => true,
            'credit_limit' => 0.00,
            'payment_terms' => Customer::TERMS_TO_PAY,
            'status' => Customer::STATUS_ACTIVE,
        ]);

        $this->assertTrue($corp->isCorporate());
        $this->assertTrue($indiv->isIndividual());
        $this->assertTrue($corp->hasAvailableCredit(1500000.00));
        $this->assertFalse($corp->hasAvailableCredit(2500000.00));
        $this->assertTrue($indiv->hasAvailableCredit(50000.00)); // 0 credit limit = cash customer
    }

    /**
     * 2. Test Customer Commercial Contracts with Route and Cargo Tariffs
     */
    public function test_customer_commercial_contracts_with_route_and_cargo_tariffs(): void
    {
        $customer = Customer::create([
            'company_id' => $this->companyA->id,
            'name' => 'Fauji Cement Company',
            'phone' => '051-11132854',
            'status' => 'active',
        ]);

        $contract = CustomerContract::create([
            'company_id' => $this->companyA->id,
            'customer_id' => $customer->id,
            'contract_number' => 'CNT-FCC-2026',
            'contract_title' => 'Bulk Cement Freight Agreement',
            'rate_type' => CustomerContract::RATE_ROUTE_BASED,
            'base_rate' => 90000.00,
            'route_rates' => [
                ['origin' => 'Rawalpindi', 'destination' => 'Lahore', 'rate' => 75000.00],
                ['origin' => 'Karachi', 'destination' => 'Peshawar', 'rate' => 180000.00],
            ],
            'loading_charges' => 2000.00,
            'unloading_charges' => 2000.00,
            'discount_percentage' => 5.00,
            'tax_percentage' => 16.00,
            'start_date' => now()->subMonth(),
            'status' => 'active',
        ]);

        $this->assertTrue($contract->isActive());
        $this->assertEquals(75000.00, $contract->getRateForRoute('Rawalpindi', 'Lahore'));
        $this->assertEquals(180000.00, $contract->getRateForRoute('Karachi', 'Peshawar'));
        $this->assertEquals(90000.00, $contract->getRateForRoute('Multan', 'Faisalabad')); // fallback to base rate
    }

    /**
     * 3. Test Freight Calculation Service Across All Rate Models
     */
    public function test_freight_calculation_service_across_all_rate_models(): void
    {
        $calcService = app(FreightCalculationService::class);

        // 1. Fixed Rate
        $resFixed = $calcService->calculateFreight('fixed', 85000.00, [], ['loading' => 2000, 'toll' => 3000]);
        $this->assertEquals(85000.00, $resFixed['freight_amount']);
        $this->assertEquals(5000.00, $resFixed['total_additional_charges']);
        $this->assertEquals(90000.00, $resFixed['net_total_amount']);

        // 2. Per Ton Rate (30 tons @ PKR 3,000/ton = 90,000)
        $resTon = $calcService->calculateFreight('per_ton', 3000.00, ['weight_tons' => 30.00]);
        $this->assertEquals(90000.00, $resTon['freight_amount']);

        // 3. Per KM Rate (1,200 km @ PKR 75/km = 90,000)
        $resKm = $calcService->calculateFreight('per_km', 75.00, ['distance_km' => 1200.00]);
        $this->assertEquals(90000.00, $resKm['freight_amount']);

        // 4. Per Quantity (500 bags @ PKR 150/bag = 75,000)
        $resQty = $calcService->calculateFreight('per_quantity', 150.00, ['quantity' => 500]);
        $this->assertEquals(75000.00, $resQty['freight_amount']);

        // 5. With Discount (10%) and Sales Tax (16%)
        $resDiscountTax = $calcService->calculateFreight(
            'fixed',
            100000.00,
            [],
            [],
            10.00, // 10% discount -> 90,000
            16.00  // 16% tax on 90,000 -> 14,400 -> Net = 104,400
        );
        $this->assertEquals(10000.00, $resDiscountTax['discount_amount']);
        $this->assertEquals(14400.00, $resDiscountTax['tax_amount']);
        $this->assertEquals(104400.00, $resDiscountTax['net_total_amount']);
    }

    /**
     * 4. Test Quotation Creation, Approval, and Atomic Numbering
     */
    public function test_quotation_creation_and_approval_workflow(): void
    {
        $customer = Customer::create([
            'company_id' => $this->companyA->id,
            'name' => 'National Foods Ltd',
            'phone' => '021-35070000',
            'status' => 'active',
        ]);

        $quotationService = app(QuotationService::class);

        // 1. Create Quotation
        $quotation = $quotationService->createQuotation($this->companyA, [
            'customer_id' => $customer->id,
            'origin_city' => 'Karachi',
            'destination_city' => 'Lahore',
            'cargo_description' => 'Spices & Food Ingredients',
            'weight_tons' => 25.00,
            'package_count' => 500,
            'rate_type' => 'fixed',
            'base_rate' => 95000.00,
            'loading_charges' => 3000.00,
            'unloading_charges' => 3000.00,
            'toll_charges' => 4000.00,
            'tax_percentage' => 16.00,
        ], $this->ownerA->id);

        $this->assertStringStartsWith('QT-', $quotation->quotation_number);
        $this->assertEquals(Quotation::STATUS_DRAFT, $quotation->status);
        $this->assertEquals(95000.00, $quotation->freight_amount);
        $this->assertEquals(10000.00, $quotation->loading_charges + $quotation->unloading_charges + $quotation->toll_charges);
        // Subtotal = 105,000. Tax 16% = 16,800. Net Total = 121,800
        $this->assertEquals(121800.00, $quotation->net_total_amount);

        // 2. Approve Quotation
        $quotationService->approveQuotation($quotation, $this->ownerA->id);
        $quotation->refresh();

        $this->assertEquals(Quotation::STATUS_APPROVED, $quotation->status);
        $this->assertNotNull($quotation->approved_at);
        $this->assertTrue($quotation->isConvertible());
    }

    /**
     * 5. Test Quotation Conversion into Booking with Rate Immutability
     */
    public function test_quotation_conversion_into_booking_with_rate_immutability(): void
    {
        $customer = Customer::create([
            'company_id' => $this->companyA->id,
            'name' => 'Unilever Pakistan',
            'phone' => '021-35681122',
            'status' => 'active',
        ]);

        $quotationService = app(QuotationService::class);

        $quotation = $quotationService->createQuotation($this->companyA, [
            'customer_id' => $customer->id,
            'origin_city' => 'Rahim Yar Khan',
            'destination_city' => 'Lahore',
            'cargo_description' => 'Personal Care Products',
            'weight_tons' => 20.00,
            'package_count' => 800,
            'rate_type' => 'fixed',
            'base_rate' => 80000.00,
            'toll_charges' => 4000.00,
            'tax_percentage' => 0.00,
        ], $this->ownerA->id);

        $quotationService->approveQuotation($quotation, $this->ownerA->id);

        // Convert Quotation into Confirmed Booking with advance of 30,000
        $booking = $quotationService->convertToBooking($quotation, [
            'advance_received' => 30000.00,
            'advance_payment_method' => 'bank_transfer',
            'advance_receipt_reference' => 'TXN-UL-9988',
        ], $this->ownerA->id);

        $this->assertStringStartsWith('BK-', $booking->booking_number);
        $this->assertEquals(Booking::STATUS_CONFIRMED, $booking->status);
        $this->assertEquals($quotation->id, $booking->quotation_id);
        $this->assertEquals(80000.00, $booking->total_freight);
        $this->assertEquals(84000.00, $booking->net_amount);
        $this->assertEquals(30000.00, $booking->advance_received);
        $this->assertEquals(54000.00, $booking->balance_amount); // 84,000 - 30,000

        // Quotation marked converted
        $quotation->refresh();
        $this->assertEquals(Quotation::STATUS_CONVERTED, $quotation->status);
        $this->assertEquals($booking->id, $quotation->converted_booking_id);

        // Initial Bilty automatically created under the booking
        $this->assertDatabaseHas('consignments', [
            'booking_id' => $booking->id,
            'company_id' => $this->companyA->id,
            'total_quantity' => 800,
            'remaining_quantity' => 800,
            'dispatched_quantity' => 0,
        ]);
    }

    /**
     * 6. Test Multi-Consignment Booking Management
     */
    public function test_multi_consignment_booking_management(): void
    {
        $customer = Customer::create([
            'company_id' => $this->companyA->id,
            'name' => 'Packages Ltd',
            'phone' => '042-35811541',
            'status' => 'active',
        ]);

        $bookingService = app(BookingService::class);
        $consignmentService = app(ConsignmentService::class);

        // 1. Create Direct Booking
        $booking = $bookingService->createBooking($this->companyA, [
            'customer_id' => $customer->id,
            'origin_city' => 'Lahore',
            'destination_city' => 'Karachi',
            'cargo_description' => 'Cartons & Paper Rolls',
            'weight_tons' => 40.00,
            'package_count' => 1000,
            'total_freight' => 150000.00,
            'consignee_name' => 'Packages Karachi Warehouse',
            'consignee_phone' => '021-34567890',
        ], $this->ownerA->id);

        // Initial Bilty is created (1000 packages)
        $this->assertEquals(1, $booking->consignments()->count());

        // 2. Add a Second Consignment Note under the same booking
        $consignment2 = $consignmentService->createConsignment($booking, [
            'consignee_name' => 'Packages Korangi Hub',
            'consignee_phone' => '021-35050505',
            'consignee_city' => 'Karachi',
            'cargo_description' => 'Corrugated Packaging Boxes',
            'packaging_type' => Consignment::PACKAGING_CARTONS,
            'total_quantity' => 500,
            'gross_weight_kg' => 15000.00,
            'chargeable_weight_kg' => 15000.00,
            'freight_charge' => 70000.00,
            'payment_mode' => 'to_pay',
        ], $this->ownerA->id);

        $booking->refresh();
        $this->assertEquals(2, $booking->total_consignments_count);
        $this->assertEquals(1500, $booking->total_packages_count); // 1000 + 500
        $this->assertEquals(55.00, (float) $booking->total_weight_tons); // 40 + 15
    }

    /**
     * 7. Test Split Dispatch Enforcement: Dispatched Quantity Cannot Exceed Remaining
     */
    public function test_split_dispatch_enforcement_cannot_exceed_remaining_quantity(): void
    {
        $customer = Customer::create([
            'company_id' => $this->companyA->id,
            'name' => 'Al-Moiz Sugar Mills',
            'phone' => '042-35711223',
            'status' => 'active',
        ]);

        $bookingService = app(BookingService::class);
        $consignmentService = app(ConsignmentService::class);

        $booking = $bookingService->createBooking($this->companyA, [
            'customer_id' => $customer->id,
            'origin_city' => 'Mianwali',
            'destination_city' => 'Karachi Port',
            'cargo_description' => 'White Refined Sugar Bags',
            'package_count' => 1000, // 1,000 bags
            'weight_tons' => 50.00,
            'total_freight' => 160000.00,
            'consignee_name' => 'Export Berth # 5',
            'consignee_phone' => '021-32851122',
        ], $this->ownerA->id);

        /** @var Consignment $consignment */
        $consignment = $booking->consignments()->first();
        $this->assertEquals(1000, $consignment->remaining_quantity);

        // Split 1: Dispatch 600 bags on Truck # 1
        $consignmentService->splitAndDispatch($consignment, 600, ['truck' => 'LES-18-9922']);
        $consignment->refresh();

        $this->assertEquals(600, $consignment->dispatched_quantity);
        $this->assertEquals(400, $consignment->remaining_quantity);
        $this->assertEquals(Consignment::STATUS_DISPATCHED_IN_TRANSIT, $consignment->status);

        // Split 2: Attempting to dispatch 500 bags MUST fail (remaining is 400)
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Split dispatch failed: Requested [500] packages exceeds remaining [400] packages');

        $consignmentService->splitAndDispatch($consignment, 500);
    }

    /**
     * 8. Test Customer Advance Recording Without Double Revenue Counting
     */
    public function test_customer_advance_recording_without_revenue_double_counting(): void
    {
        $customer = Customer::create([
            'company_id' => $this->companyA->id,
            'name' => 'Habib Oil Mills',
            'phone' => '021-32560000',
            'status' => 'active',
        ]);

        $bookingService = app(BookingService::class);

        $booking = $bookingService->createBooking($this->companyA, [
            'customer_id' => $customer->id,
            'origin_city' => 'Karachi',
            'destination_city' => 'Multan',
            'cargo_description' => 'Cooking Oil Tins',
            'package_count' => 800,
            'total_freight' => 120000.00,
            'advance_received' => 0.00,
            'consignee_name' => 'Multan Depot',
            'consignee_phone' => '061-4500000',
        ], $this->ownerA->id);

        $this->assertEquals(120000.00, $booking->net_amount);
        $this->assertEquals(120000.00, $booking->balance_amount);
        $this->assertEquals(0.00, $booking->advance_received);

        // Record Partial Advance of PKR 40,000
        $bookingService->recordAdvance($booking, 40000.00, 'bank_transfer', 'TXN-ADV-001');

        $booking->refresh();
        $this->assertEquals(40000.00, $booking->advance_received);
        $this->assertEquals(80000.00, $booking->balance_amount);
        $this->assertEquals('bank_transfer', $booking->advance_payment_method);
        $this->assertNotNull($booking->advance_recorded_at);
    }

    /**
     * 9. Test Booking Cancellation Safety Rules
     */
    public function test_booking_cancellation_safety_rules(): void
    {
        $customer = Customer::create([
            'company_id' => $this->companyA->id,
            'name' => 'Lucky Cement',
            'phone' => '021-111786555',
            'status' => 'active',
        ]);

        $bookingService = app(BookingService::class);
        $consignmentService = app(ConsignmentService::class);

        // 1. Booking without dispatches CAN be cancelled
        $booking = $bookingService->createBooking($this->companyA, [
            'customer_id' => $customer->id,
            'origin_city' => 'Pezu',
            'destination_city' => 'Lahore',
            'cargo_description' => 'Cement Bags',
            'package_count' => 600,
            'total_freight' => 90000.00,
            'consignee_name' => 'Lahore Retailer',
            'consignee_phone' => '042-36655443',
        ], $this->ownerA->id);

        $bookingService->cancelBooking($booking, 'Client cancelled order due to site delay.');
        $booking->refresh();

        $this->assertEquals(Booking::STATUS_CANCELLED, $booking->status);
        $this->assertNotNull($booking->cancelled_at);

        // Linked consignment is marked cancelled
        $this->assertEquals(Consignment::STATUS_CANCELLED, $booking->consignments()->first()->status);

        // 2. Booking WITH active dispatch CANNOT be cancelled
        $activeBooking = $bookingService->createBooking($this->companyA, [
            'customer_id' => $customer->id,
            'origin_city' => 'Karachi',
            'destination_city' => 'Hyderabad',
            'cargo_description' => 'Cement Clinker',
            'package_count' => 500,
            'total_freight' => 50000.00,
            'consignee_name' => 'Hyderabad Hub',
            'consignee_phone' => '022-2780000',
        ], $this->ownerA->id);

        $consignment = $activeBooking->consignments()->first();
        $consignmentService->splitAndDispatch($consignment, 200); // Dispatched 200

        $activeBooking->refresh();
        $this->assertFalse($activeBooking->canBeCancelled());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot cancel booking: active dispatches or completed deliveries already exist.');

        $bookingService->cancelBooking($activeBooking, 'Illegal attempt to cancel dispatched goods.');
    }

    /**
     * 10. Test Cross-Tenant Commercial Data Isolation
     */
    public function test_cross_tenant_commercial_data_isolation(): void
    {
        $customerA = Customer::create([
            'company_id' => $this->companyA->id,
            'name' => 'Alpha Corp',
            'phone' => '0300-1111111',
            'status' => 'active',
        ]);

        $customerB = Customer::create([
            'company_id' => $this->companyB->id,
            'name' => 'Beta Corp',
            'phone' => '0300-2222222',
            'status' => 'active',
        ]);

        // Attempting to edit Customer B as Company A user must be forbidden (403)
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setCompany($this->companyA);

        Livewire::test(CustomerManager::class)
            ->call('editCustomer', $customerB->id)
            ->assertForbidden();
    }

    /**
     * 11. Test Livewire CustomerManager Component End-to-End
     */
    public function test_livewire_customer_manager_component(): void
    {
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setCompany($this->companyA);

        Livewire::test(CustomerManager::class)
            ->assertSee('Register Customer')
            ->call('openCreateCustomerModal')
            ->set('name', 'Nishat Mills Ltd')
            ->set('customer_type', Customer::TYPE_CORPORATE)
            ->set('company_name', 'Nishat Group')
            ->set('phone', '042-35711223')
            ->set('tax_ntn', '0812345-6')
            ->set('credit_limit', 1500000.00)
            ->set('credit_days', 30)
            ->set('payment_terms', Customer::TERMS_CREDIT_30)
            ->call('saveCustomer')
            ->assertHasNoErrors()
            ->assertSee('Nishat Mills Ltd');

        $this->assertDatabaseHas('customers', [
            'company_id' => $this->companyA->id,
            'name' => 'Nishat Mills Ltd',
            'tax_ntn' => '0812345-6',
        ]);
    }

    /**
     * 12. Test Livewire QuotationManager Component and 1-Click Conversion
     */
    public function test_livewire_quotation_manager_component_and_conversion(): void
    {
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setCompany($this->companyA);

        $customer = Customer::create([
            'company_id' => $this->companyA->id,
            'name' => 'Nestle Pakistan',
            'phone' => '042-111637853',
            'status' => 'active',
        ]);

        $comp = Livewire::test(QuotationManager::class)
            ->assertSee('Generate Quotation')
            ->call('openCreateModal')
            ->set('customer_id', $customer->id)
            ->set('origin_city', 'Sheikhupura')
            ->set('destination_city', 'Karachi')
            ->set('cargo_description', 'Dairy & Milk Cartons')
            ->set('weight_tons', 28.00)
            ->set('package_count', 700)
            ->set('rate_type', 'fixed')
            ->set('base_rate', 110000.00)
            ->call('saveQuotation')
            ->assertHasNoErrors()
            ->assertSee('Dairy & Milk Cartons');

        $quotation = Quotation::where('customer_id', $customer->id)->first();
        $this->assertNotNull($quotation);

        // Approve and Convert
        $comp->call('approveQuotation', $quotation->id)
            ->call('convertToBooking', $quotation->id)
            ->assertHasNoErrors();

        $quotation->refresh();
        $this->assertEquals(Quotation::STATUS_CONVERTED, $quotation->status);
        $this->assertNotNull($quotation->converted_booking_id);
    }

    /**
     * 13. Test Livewire Booking and Consignment Managers
     */
    public function test_livewire_booking_and_consignment_managers(): void
    {
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setCompany($this->companyA);

        $customer = Customer::create([
            'company_id' => $this->companyA->id,
            'name' => 'Ibrahim Fibres Ltd',
            'phone' => '041-111111435',
            'status' => 'active',
        ]);

        // 1. Create Booking via Component
        Livewire::test(BookingManager::class)
            ->assertSee('New Cargo Booking')
            ->call('openCreateBookingModal')
            ->set('customer_id', $customer->id)
            ->set('origin_city', 'Faisalabad')
            ->set('destination_city', 'Karachi')
            ->set('cargo_description', 'Polyester Staple Fibre Bales')
            ->set('weight_tons', 35.00)
            ->set('package_count', 350)
            ->set('total_freight', 125000.00)
            ->set('consignee_name', 'Karachi Weaving Mills')
            ->set('consignee_phone', '021-32551100')
            ->call('saveBooking')
            ->assertHasNoErrors()
            ->assertSee('Faisalabad');

        $booking = Booking::where('customer_id', $customer->id)->first();
        $this->assertNotNull($booking);

        // 2. Consignment Manager Component view and split dispatch
        $consignment = $booking->consignments()->first();
        $this->assertNotNull($consignment);

        Livewire::test(ConsignmentManager::class)
            ->assertSee($consignment->consignment_number)
            ->call('openSplitModal', $consignment->id)
            ->set('dispatch_quantity', 150)
            ->call('splitAndDispatch')
            ->assertHasNoErrors();

        $consignment->refresh();
        $this->assertEquals(150, $consignment->dispatched_quantity);
        $this->assertEquals(200, $consignment->remaining_quantity); // 350 - 150 = 200
    }

    /**
     * 14. Test Printable Document Gateway HTTP Responses
     */
    public function test_printable_document_gateway(): void
    {
        $customer = Customer::create([
            'company_id' => $this->companyA->id,
            'name' => 'Print Test Customer',
            'phone' => '0300-1234567',
            'status' => 'active',
        ]);

        $quotation = Quotation::create([
            'company_id' => $this->companyA->id,
            'customer_id' => $customer->id,
            'quotation_number' => 'QT-TEST-001',
            'quotation_date' => now()->toDateString(),
            'valid_until' => now()->addDays(10)->toDateString(),
            'origin_city' => 'Lahore',
            'destination_city' => 'Islamabad',
            'cargo_description' => 'Office Stationery',
            'weight_tons' => 5.00,
            'package_count' => 100,
            'rate_type' => 'fixed',
            'base_rate' => 35000.00,
            'freight_amount' => 35000.00,
            'net_total_amount' => 35000.00,
            'status' => 'draft',
        ]);

        $booking = Booking::create([
            'company_id' => $this->companyA->id,
            'customer_id' => $customer->id,
            'booking_number' => 'BK-TEST-001',
            'booking_date' => now()->toDateString(),
            'origin_city' => 'Lahore',
            'destination_city' => 'Islamabad',
            'net_amount' => 35000.00,
            'balance_amount' => 35000.00,
            'status' => 'confirmed',
        ]);

        $consignment = Consignment::create([
            'company_id' => $this->companyA->id,
            'booking_id' => $booking->id,
            'customer_id' => $customer->id,
            'consignment_number' => 'LR-TEST-001',
            'consignment_date' => now()->toDateString(),
            'consignee_name' => 'Islamabad Stationery Store',
            'consignee_phone' => '051-2233445',
            'consignee_city' => 'Islamabad',
            'cargo_description' => 'Office Stationery',
            'total_quantity' => 100,
            'remaining_quantity' => 100,
            'status' => 'booked',
        ]);

        // Authenticate as Owner A
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setCompany($this->companyA);

        $this->get(route('print.quotation', $quotation->id))->assertOk()->assertSee('FREIGHT QUOTATION')->assertSee('QT-TEST-001');
        $this->get(route('print.booking', $booking->id))->assertOk()->assertSee('BOOKING CONFIRMATION')->assertSee('BK-TEST-001');
        $this->get(route('print.consignment', $consignment->id))->assertOk()->assertSee('LORRY RECEIPT / BILTY')->assertSee('LR-TEST-001');
        $this->get(route('print.cargo-receipt', $consignment->id))->assertOk()->assertSee('GOODS INWARD RECEIPT');
        $this->get(route('print.booking-summary', $booking->id))->assertOk()->assertSee('BOOKING DISPATCH SUMMARY');

        // Cross-tenant access as Owner B must be forbidden (403)
        $this->actingAs($this->ownerB);
        app(TenantContextService::class)->setCompany($this->companyB);

        $this->get(route('print.quotation', $quotation->id))->assertForbidden();
        $this->get(route('print.booking', $booking->id))->assertForbidden();
        $this->get(route('print.consignment', $consignment->id))->assertForbidden();
    }
}

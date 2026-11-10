<?php

namespace Tests\Feature;

use App\Livewire\Onboarding\SetupWizard;
use App\Livewire\Settings\BranchManager;
use App\Livewire\Settings\CompanyProfileManager;
use App\Livewire\Settings\FinancialSettingsManager;
use App\Livewire\Settings\MasterDataManager;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\FinancialYear;
use App\Models\MasterData;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\DocumentSequenceService;
use App\Services\FinancialPeriodService;
use App\Services\MasterDataService;
use App\Services\TenantContextService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class CompanyBranchConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected Company $companyB;
    protected User $ownerA;
    protected User $ownerB;
    protected User $superAdmin;
    protected Branch $branchA;
    protected Branch $branchB;
    protected SubscriptionPlan $starterPlan;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Roles
        Role::create([
            'company_id' => null,
            'name' => 'Tenant Administrator',
            'slug' => 'tenant_admin',
            'is_system' => true,
        ]);

        Role::create([
            'company_id' => null,
            'name' => 'Dispatcher',
            'slug' => 'dispatcher',
            'is_system' => true,
        ]);

        // 2. Subscription Plan with max_branches = 2
        $this->starterPlan = SubscriptionPlan::create([
            'name' => 'Starter Logistics Plan',
            'slug' => 'starter',
            'description' => 'Entry plan for small freight operations',
            'monthly_price' => 15000.00,
            'quarterly_price' => 42000.00,
            'annual_price' => 150000.00,
            'trial_duration_days' => 14,
            'max_vehicles' => 5,
            'max_users' => 5,
            'max_branches' => 2, // Permits maximum 2 branches
            'max_storage_mb' => 2048,
            'features' => ['fleet_management', 'trip_management'],
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

        // 4. Company A
        $this->companyA = Company::create([
            'name' => 'Falcon Express Ltd',
            'code' => 'FEC',
            'slug' => 'falcon-express',
            'email' => 'ops@falconexpress.com',
            'phone' => '+92-42-35889900',
            'currency' => 'PKR',
            'timezone' => 'Asia/Karachi',
            'status' => 'active',
            'plan_id' => $this->starterPlan->id,
        ]);

        $this->branchA = Branch::create([
            'company_id' => $this->companyA->id,
            'code' => 'FEC-LHR',
            'name' => 'Lahore Hub',
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
            'name' => 'Malik Tariq',
            'email' => 'tariq@falconexpress.com',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
            'is_super_admin' => false,
        ]);

        $this->companyA->owner_id = $this->ownerA->id;
        $this->companyA->save();

        // 5. Company B (to test isolation)
        $this->companyB = Company::create([
            'name' => 'Indus Freight Ltd',
            'code' => 'IFL',
            'slug' => 'indus-freight',
            'email' => 'contact@indusfreight.com',
            'phone' => '+92-51-2233445',
            'status' => 'active',
            'plan_id' => $this->starterPlan->id,
        ]);

        $this->branchB = Branch::create([
            'company_id' => $this->companyB->id,
            'code' => 'IFL-ISB',
            'name' => 'Islamabad Depot',
            'city' => 'Islamabad',
            'is_head_office' => true,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->ownerB = User::create([
            'company_id' => $this->companyB->id,
            'branch_id' => $this->branchB->id,
            'role_id' => $adminRole->id,
            'name' => 'Chaudhry Nadeem',
            'email' => 'nadeem@indusfreight.com',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
            'is_super_admin' => false,
        ]);

        $this->companyB->owner_id = $this->ownerB->id;
        $this->companyB->save();
    }

    /**
     * Test 1: Company Profile management and updating.
     */
    public function test_tenant_owner_can_manage_company_profile(): void
    {
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setTenantContext($this->companyA, $this->branchA);

        Livewire::test(CompanyProfileManager::class)
            ->set('trading_name', 'Falcon Express Premier')
            ->set('registration_number', 'SECP-884920')
            ->set('tax_number', 'NTN-4018294-1')
            ->set('website', 'https://falconexpress.com')
            ->set('currency', 'PKR')
            ->set('currency_precision', 2)
            ->set('is_tax_enabled', true)
            ->set('tax_name', 'Punjab Sales Tax')
            ->set('default_tax_rate', 15.00)
            ->call('save')
            ->assertHasNoErrors();

        $this->companyA->refresh();
        $this->assertEquals('Falcon Express Premier', $this->companyA->trading_name);
        $this->assertEquals('SECP-884920', $this->companyA->registration_number);
        $this->assertEquals(15.00, (float) $this->companyA->default_tax_rate);
    }

    /**
     * Test 2: Branch management respects plan quotas and default branch setting.
     */
    public function test_branch_management_and_quota_enforcement(): void
    {
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setTenantContext($this->companyA, $this->branchA);

        // 1. Current branch count = 1. Plan limit = 2.
        // Adding 2nd branch (Allowed)
        Livewire::test(BranchManager::class)
            ->set('name', 'Karachi Port Terminal')
            ->set('code', 'FEC-KHI')
            ->set('city', 'Karachi')
            ->set('phone', '+92-21-32448800')
            ->set('dock_capacity', 4)
            ->set('is_default', true)
            ->call('saveBranch')
            ->assertHasNoErrors();

        $this->assertEquals(2, $this->companyA->branches()->count());

        $khiBranch = Branch::where('company_id', $this->companyA->id)->where('code', 'FEC-KHI')->first();
        $this->assertNotNull($khiBranch);
        $this->assertTrue($khiBranch->is_default);

        // Assert previous default branch was unset
        $this->branchA->refresh();
        $this->assertFalse($this->branchA->is_default);

        // 2. Attempting to add 3rd branch (Must be rejected by plan quota limit of 2)
        Livewire::test(BranchManager::class)
            ->set('name', 'Multan Regional Depot')
            ->set('code', 'FEC-MUX')
            ->set('city', 'Multan')
            ->call('saveBranch')
            ->assertForbidden();

        $this->assertEquals(2, $this->companyA->branches()->count());
    }

    /**
     * Test 3: Cross-tenant branch isolation (Tenant A cannot view or edit Tenant B branch).
     */
    public function test_tenant_branch_isolation(): void
    {
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setTenantContext($this->companyA, $this->branchA);

        // Attempting to edit Tenant B's branch must be rejected
        Livewire::test(BranchManager::class)
            ->call('editBranch', $this->branchB->id)
            ->assertForbidden();
    }

    /**
     * Test 4: Financial Year creation and automatic 12-period generation.
     */
    public function test_financial_year_and_accounting_period_generation(): void
    {
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setTenantContext($this->companyA, $this->branchA);

        Livewire::test(FinancialSettingsManager::class)
            ->set('name', 'FY 2026-2027')
            ->set('start_date', '2026-07-01')
            ->set('end_date', '2027-06-30')
            ->set('make_current', true)
            ->call('createFinancialYear')
            ->assertHasNoErrors();

        $fy = FinancialYear::where('company_id', $this->companyA->id)->where('name', 'FY 2026-2027')->first();
        $this->assertNotNull($fy);
        $this->assertTrue($fy->is_current);
        $this->assertTrue($fy->isOpen());

        // Assert 12 monthly accounting periods were automatically generated
        $this->assertEquals(12, $fy->periods()->count());
        $firstPeriod = $fy->periods()->first();
        $this->assertEquals(1, $firstPeriod->period_number);
        $this->assertTrue($firstPeriod->isOpen());
    }

    /**
     * Test 5: Financial period restriction and transaction locking.
     */
    public function test_financial_period_transaction_date_validation(): void
    {
        $fyService = app(FinancialPeriodService::class);

        // Create FY 2026-2027
        $fy = $fyService->createFinancialYearWithPeriods(
            $this->companyA,
            'FY 2026-2027',
            '2026-07-01',
            '2027-06-30',
            true
        );

        // 1. Valid date inside open period (August 2026) -> Allowed
        $this->assertTrue($fyService->validateTransactionDate($this->companyA, '2026-08-15'));

        // 2. Date outside any financial year (e.g. 2024-01-01) -> Throws AuthorizationException
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessageMatches('/does not fall within any configured Financial Year/i');
        $fyService->validateTransactionDate($this->companyA, '2024-01-01');
    }

    /**
     * Test 6: Closed accounting periods reject financial transactions.
     */
    public function test_closed_accounting_period_rejects_transactions(): void
    {
        $fyService = app(FinancialPeriodService::class);

        $fy = $fyService->createFinancialYearWithPeriods(
            $this->companyA,
            'FY 2026-2027',
            '2026-07-01',
            '2027-06-30',
            true
        );

        // Close Period 1 (July 2026)
        $period1 = $fy->periods()->where('period_number', 1)->first();
        $fyService->closeAccountingPeriod($period1, $this->ownerA->id);

        $this->assertTrue($period1->fresh()->isClosed());

        // Attempt transaction on July 10, 2026 -> must be rejected
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessageMatches('/Accounting Period .* is CLOSED/i');
        $fyService->validateTransactionDate($this->companyA, '2026-07-10');
    }

    /**
     * Test 7: Master Data lookups (12 categories) and tenant-custom lookups.
     */
    public function test_master_data_lookups_and_tenant_custom_additions(): void
    {
        $masterService = app(MasterDataService::class);

        // Seed system lookups
        $masterService->seedSystemDefaultLookups();

        // 1. Verify system lookups available to tenant
        $vehicleTypes = $masterService->getItemsByCategory(MasterData::CATEGORY_VEHICLE_TYPE, $this->companyA->id);
        $this->assertNotEmpty($vehicleTypes);
        $this->assertTrue($vehicleTypes->contains('code', 'TRAILER_22W'));

        // 2. Tenant creates custom lookup
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setTenantContext($this->companyA, $this->branchA);

        Livewire::test(MasterDataManager::class)
            ->set('selectedCategory', MasterData::CATEGORY_VEHICLE_TYPE)
            ->set('code', 'DUMPER_10W')
            ->set('name', '10-Wheeler Sand Dumper Truck')
            ->set('meta_symbol', 'Dumper')
            ->call('saveCustomItem')
            ->assertHasNoErrors();

        // Custom lookup visible to Company A
        $customItem = MasterData::where('company_id', $this->companyA->id)->where('code', 'DUMPER_10W')->first();
        $this->assertNotNull($customItem);
        $this->assertFalse($customItem->is_system);

        // 3. Custom lookup NOT visible to Company B
        $itemsForB = $masterService->getItemsByCategory(MasterData::CATEGORY_VEHICLE_TYPE, $this->companyB->id);
        $this->assertFalse($itemsForB->contains('code', 'DUMPER_10W'));
    }

    /**
     * Test 8: Document sequences for 11 core document types with pessimistic concurrency locking.
     */
    public function test_document_sequences_for_all_eleven_types_and_branch_support(): void
    {
        $seqService = app(DocumentSequenceService::class);
        $year = (int) date('Y');

        // All 11 core document types
        $types = array_keys(DocumentSequence::CORE_ELEVEN_TYPES);
        $this->assertCount(11, $types);

        foreach ($types as $type) {
            $num1 = $seqService->getNextSequenceNumber($type, null, 5, $this->companyA->id);
            $num2 = $seqService->getNextSequenceNumber($type, null, 5, $this->companyA->id);

            $this->assertNotEmpty($num1);
            $this->assertNotEmpty($num2);
            $this->assertNotEquals($num1, $num2);

            // Verify sequential increment (00001, 00002)
            $this->assertStringEndsWith('00001', $num1);
            $this->assertStringEndsWith('00002', $num2);
        }

        // Branch-specific sequence
        $branchNum = $seqService->getNextSequenceNumber(
            DocumentSequence::TYPE_CONSIGNMENT,
            'FEC-LR',
            5,
            $this->companyA->id,
            $this->branchA->id
        );

        // Must include branch code 'FEC-LHR'
        $this->assertStringContainsString('FEC-LHR', $branchNum);
    }

    /**
     * Test 9: Complete guided onboarding wizard workflow (Steps 1 through 6).
     */
    public function test_initial_setup_wizard_full_onboarding_lifecycle(): void
    {
        $this->actingAs($this->ownerA);
        app(TenantContextService::class)->setTenantContext($this->companyA, $this->branchA);

        $this->assertFalse($this->companyA->isOnboarded());

        $wizard = Livewire::test(SetupWizard::class);

        // Step 1: Company Profile
        $wizard->set('company_name', 'Falcon Express Logistics PVT')
            ->set('trading_name', 'Falcon Express')
            ->set('registration_number', 'SECP-112233')
            ->set('tax_number', 'NTN-9988776')
            ->set('email', 'corporate@falconexpress.com')
            ->set('phone', '+92-42-35889900')
            ->set('address', 'Logistics Park, Lahore')
            ->set('currency', 'PKR')
            ->call('completeStep1')
            ->assertSet('currentStep', 2);

        // Step 2: Branch Setup
        $wizard->set('branch_name', 'Lahore Central Terminal')
            ->set('branch_code', 'FEC-HQ')
            ->set('branch_city', 'Lahore')
            ->call('completeStep2')
            ->assertSet('currentStep', 3);

        // Step 3: Fiscal Year
        $wizard->set('fy_name', 'FY 2026-2027')
            ->set('fy_start_date', '2026-07-01')
            ->set('fy_end_date', '2027-06-30')
            ->set('currency_precision', 2)
            ->call('completeStep3')
            ->assertSet('currentStep', 4);

        // Step 4: Master Data Activation
        $wizard->call('completeStep4')
            ->assertSet('currentStep', 5);

        // Step 5: Staff Member (Optional)
        $wizard->set('staff_name', 'Imran Khan (Dispatcher)')
            ->set('staff_email', 'imran.dispatch@falconexpress.com')
            ->set('staff_password', 'Password123!')
            ->set('staff_role', 'dispatcher')
            ->call('completeStep5')
            ->assertSet('currentStep', 6);

        // Step 6: Initial Fleet & Complete
        $wizard->set('vehicle_registration', 'LES-26-9090')
            ->set('vehicle_type', 'TRAILER_22W')
            ->set('ownership_type', 'company_owned')
            ->set('payload_capacity', 50.00)
            ->call('completeStep6')
            ->assertRedirect(route('dashboard'));

        $this->companyA->refresh();
        $this->assertTrue($this->companyA->isOnboarded());
        $this->assertNotNull($this->companyA->onboarding_completed_at);
        $this->assertEquals(100, $this->companyA->getOnboardingProgressPercentage());

        // Verify staff user created
        $staffUser = User::where('email', 'imran.dispatch@falconexpress.com')->first();
        $this->assertNotNull($staffUser);
        $this->assertEquals($this->companyA->id, $staffUser->company_id);

        // Verify FY created
        $this->assertDatabaseHas('financial_years', [
            'company_id' => $this->companyA->id,
            'name' => 'FY 2026-2027',
        ]);
    }
}


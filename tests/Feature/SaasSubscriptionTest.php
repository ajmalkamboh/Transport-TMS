<?php

namespace Tests\Feature;

use App\Livewire\Admin\UserManagement;
use App\Livewire\Saas\BillingManager;
use App\Livewire\Saas\PlanManager;
use App\Livewire\Saas\TenantManager;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\FeatureManagerService;
use App\Services\SubscriptionService;
use App\Services\TenantContextService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

class SaasSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected Company $tenantCompany;
    protected Branch $tenantBranch;
    protected User $tenantOwner;
    protected SubscriptionPlan $starterPlan;
    protected SubscriptionPlan $enterprisePlan;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create Super Admin (company_id is null)
        $this->superAdmin = User::create([
            'name' => 'Platform Super Admin',
            'email' => 'superadmin@platform.local',
            'password' => Hash::make('Secret123!'),
            'is_super_admin' => true,
            'status' => 'active',
            'company_id' => null,
            'branch_id' => null,
        ]);

        // 2. Create Roles
        Role::create([
            'company_id' => null,
            'name' => 'Tenant Administrator',
            'slug' => 'tenant_admin',
            'is_system' => true,
        ]);

        // 3. Create Subscription Plans
        $this->starterPlan = SubscriptionPlan::create([
            'name' => 'Starter Logistics Plan',
            'slug' => 'starter',
            'description' => 'Entry plan for small freight operations',
            'monthly_price' => 15000.00,
            'quarterly_price' => 42000.00,
            'annual_price' => 150000.00,
            'trial_duration_days' => 14,
            'max_vehicles' => 5,
            'max_users' => 2,
            'max_branches' => 1,
            'max_storage_mb' => 2048,
            'features' => [
                'fleet_management',
                'driver_management',
                'customer_management',
                'booking_management',
                'trip_management',
            ],
            'is_active' => true,
        ]);

        $this->enterprisePlan = SubscriptionPlan::create([
            'name' => 'Enterprise Logistics Plan',
            'slug' => 'enterprise',
            'description' => 'Full platform access including accounting & GPS',
            'monthly_price' => 75000.00,
            'quarterly_price' => 210000.00,
            'annual_price' => 750000.00,
            'trial_duration_days' => 14,
            'max_vehicles' => -1,
            'max_users' => -1,
            'max_branches' => -1,
            'max_storage_mb' => -1,
            'features' => array_keys(SubscriptionPlan::ALL_FEATURES),
            'is_active' => true,
        ]);

        // 4. Create Standard Tenant Company
        $this->tenantCompany = Company::create([
            'name' => 'Falcon Express Ltd',
            'code' => 'FEC',
            'slug' => 'falcon-express',
            'email' => 'ops@falconexpress.com',
            'phone' => '+92-42-35889900',
            'status' => 'active',
            'plan_id' => $this->starterPlan->id,
        ]);

        $this->tenantBranch = Branch::create([
            'company_id' => $this->tenantCompany->id,
            'code' => 'FEC-HQ',
            'name' => 'Lahore Terminal',
            'city' => 'Lahore',
            'is_head_office' => true,
            'is_active' => true,
        ]);

        $adminRole = Role::where('slug', 'tenant_admin')->first();

        $this->tenantOwner = User::create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'role_id' => $adminRole->id,
            'name' => 'Malik Tariq',
            'email' => 'tariq@falconexpress.com',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
            'is_super_admin' => false,
        ]);

        $this->tenantCompany->owner_id = $this->tenantOwner->id;
        $this->tenantCompany->save();
    }

    /**
     * Test 1: Super Admin can access SaaS dashboard with accurate KPIs.
     */
    public function test_super_admin_can_access_saas_dashboard_with_kpi_calculations(): void
    {
        // Create an active subscription for tenant
        Subscription::create([
            'company_id' => $this->tenantCompany->id,
            'plan_id' => $this->starterPlan->id,
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'grace_period_ends_at' => now()->addMonth()->addDays(7),
            'price' => 15000.00,
            'currency' => 'PKR',
        ]);

        $response = $this->actingAs($this->superAdmin)->get(route('saas.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Platform SaaS Governance Console');
        $response->assertSee('15,000'); // Monthly Recurring Revenue
        $response->assertSee('Falcon Express Ltd');
    }

    /**
     * Test 2: Normal tenant owner cannot access platform SaaS administration routes.
     */
    public function test_normal_tenant_user_blocked_from_all_saas_platform_routes(): void
    {
        $this->actingAs($this->tenantOwner);

        $routes = [
            route('saas.dashboard'),
            route('saas.tenants'),
            route('saas.plans'),
            route('saas.billing'),
        ];

        foreach ($routes as $route) {
            $response = $this->get($route);
            $response->assertStatus(403);
        }
    }

    /**
     * Test 3: Super Admin can provision a new transport company tenant via Livewire.
     */
    public function test_super_admin_can_provision_new_tenant_with_owner_and_subscription(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(TenantManager::class)
            ->set('name', 'Speedy Cargo Express')
            ->set('code', 'SCX')
            ->set('email', 'contact@speedycargo.com')
            ->set('phone', '+92-300-1122334')
            ->set('city', 'Karachi')
            ->set('plan_id', $this->enterprisePlan->id)
            ->set('billing_cycle', 'annual')
            ->set('is_trial', false)
            ->set('owner_name', 'Ahmed Raza')
            ->set('owner_email', 'ahmed@speedycargo.com')
            ->set('owner_password', 'Password123!')
            ->call('saveTenant')
            ->assertHasNoErrors();

        // Verify company created
        $company = Company::where('code', 'SCX')->first();
        $this->assertNotNull($company);
        $this->assertEquals('Speedy Cargo Express', $company->name);
        $this->assertEquals('active', $company->status);
        $this->assertEquals($this->enterprisePlan->id, $company->plan_id);

        // Verify head office branch created
        $branch = Branch::where('company_id', $company->id)->where('is_head_office', true)->first();
        $this->assertNotNull($branch);
        $this->assertEquals('SCX-HQ', $branch->code);
        $this->assertEquals('Karachi', $branch->city);

        // Verify business owner user created and linked
        $owner = User::where('email', 'ahmed@speedycargo.com')->first();
        $this->assertNotNull($owner);
        $this->assertEquals($company->id, $owner->company_id);
        $this->assertEquals($branch->id, $owner->branch_id);
        $this->assertEquals($owner->id, $company->owner_id);

        // Verify subscription & initial invoice created
        $subscription = Subscription::where('company_id', $company->id)->first();
        $this->assertNotNull($subscription);
        $this->assertEquals('annual', $subscription->billing_cycle);
        $this->assertEquals('active', $subscription->status);
        $this->assertEquals($this->enterprisePlan->annual_price, (float) $subscription->price);

        $invoice = SubscriptionInvoice::where('subscription_id', $subscription->id)->first();
        $this->assertNotNull($invoice);
        $this->assertEquals('unpaid', $invoice->status);
        $this->assertGreaterThan(0, (float) $invoice->total_amount);
    }

    /**
     * Test 4: Super Admin can edit tenant details, suspend, and reactivate a tenant.
     */
    public function test_super_admin_can_edit_and_suspend_reactivate_tenant(): void
    {
        $this->actingAs($this->superAdmin);

        $component = Livewire::test(TenantManager::class);

        // Edit tenant
        $component->call('editTenant', $this->tenantCompany->id)
            ->set('name', 'Falcon Express Logistics PVT')
            ->set('phone', '+92-42-99887766')
            ->call('saveTenant')
            ->assertHasNoErrors();

        $this->tenantCompany->refresh();
        $this->assertEquals('Falcon Express Logistics PVT', $this->tenantCompany->name);
        $this->assertEquals('+92-42-99887766', $this->tenantCompany->phone);

        // Suspend tenant
        $component->call('setStatus', $this->tenantCompany->id, 'suspended');
        $this->tenantCompany->refresh();
        $this->assertEquals('suspended', $this->tenantCompany->status);

        // Reactivate tenant
        $component->call('setStatus', $this->tenantCompany->id, 'active');
        $this->tenantCompany->refresh();
        $this->assertEquals('active', $this->tenantCompany->status);
    }

    /**
     * Test 5: Super Admin can configure tenant-specific feature overrides.
     */
    public function test_tenant_specific_feature_overrides(): void
    {
        $this->actingAs($this->superAdmin);
        $featureService = app(FeatureManagerService::class);

        // Starter plan does NOT include 'accounting'
        $this->assertFalse($featureService->hasFeature($this->tenantCompany, 'accounting'));

        // Super Admin toggles override on
        Livewire::test(TenantManager::class)
            ->call('toggleFeatureOverride', $this->tenantCompany->id, 'accounting');

        $this->tenantCompany->refresh();
        $this->assertTrue($featureService->hasFeature($this->tenantCompany, 'accounting'));

        // Super Admin toggles override off
        Livewire::test(TenantManager::class)
            ->call('toggleFeatureOverride', $this->tenantCompany->id, 'accounting');

        $this->tenantCompany->refresh();
        $this->assertFalse($featureService->hasFeature($this->tenantCompany, 'accounting'));
    }

    /**
     * Test 6: Super Admin can create and update subscription plans via Livewire.
     */
    public function test_super_admin_can_create_and_update_subscription_plans(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(PlanManager::class)
            ->set('name', 'Gold Cargo Tier')
            ->set('slug', 'gold-cargo')
            ->set('description', 'Mid-market freight operations')
            ->set('monthly_price', 45000.00)
            ->set('quarterly_price', 125000.00)
            ->set('annual_price', 450000.00)
            ->set('trial_duration_days', 21)
            ->set('max_vehicles', 50)
            ->set('max_users', 15)
            ->set('max_branches', 8)
            ->set('features', ['fleet_management', 'trip_management', 'fuel_management', 'accounting'])
            ->call('savePlan')
            ->assertHasNoErrors();

        $plan = SubscriptionPlan::where('slug', 'gold-cargo')->first();
        $this->assertNotNull($plan);
        $this->assertEquals(45000.00, (float) $plan->monthly_price);
        $this->assertEquals(15, $plan->max_users);
        $this->assertTrue($plan->hasFeature('fuel_management'));
        $this->assertFalse($plan->hasFeature('gps_tracking'));

        // Update plan
        Livewire::test(PlanManager::class)
            ->call('editPlan', $plan->id)
            ->set('monthly_price', 48000.00)
            ->call('savePlan')
            ->assertHasNoErrors();

        $plan->refresh();
        $this->assertEquals(48000.00, (float) $plan->monthly_price);
    }

    /**
     * Test 7: EnsureFeatureEnabled middleware restricts access server-side.
     */
    public function test_feature_gating_middleware_enforces_plan_feature_access(): void
    {
        // Define runtime routes protected by feature middleware
        Route::middleware(['web', 'auth', 'user.active', 'tenant', 'feature:accounting'])
            ->get('/test-route-accounting', function () {
                return response('Accounting Feature Accessed', 200);
            });

        Route::middleware(['web', 'auth', 'user.active', 'tenant', 'feature:fleet_management'])
            ->get('/test-route-fleet', function () {
                return response('Fleet Feature Accessed', 200);
            });

        // 1. Tenant is on Starter plan (has fleet_management, does NOT have accounting)
        $this->actingAs($this->tenantOwner);

        // Allowed feature
        $responseFleet = $this->get('/test-route-fleet');
        $responseFleet->assertStatus(200);
        $responseFleet->assertSee('Fleet Feature Accessed');

        // Blocked feature
        $responseAccounting = $this->get('/test-route-accounting');
        $responseAccounting->assertStatus(403);

        // 2. Grant override to company
        app(FeatureManagerService::class)->setFeatureOverride($this->tenantCompany, 'accounting', true);
        $this->tenantCompany->refresh();

        $responseAccountingAllowed = $this->get('/test-route-accounting');
        $responseAccountingAllowed->assertStatus(200);
        $responseAccountingAllowed->assertSee('Accounting Feature Accessed');

        // 3. Super Admin bypasses feature checks
        $this->actingAs($this->superAdmin);
        $responseSuperAdmin = $this->get('/test-route-accounting');
        $responseSuperAdmin->assertStatus(200);
    }

    /**
     * Test 8: Plan limits block exceeding maximum user quota.
     */
    public function test_plan_user_limit_enforcement_blocks_exceeding_users(): void
    {
        // Starter plan max_users = 2.
        // Current user count = 1 ($this->tenantOwner).
        $this->actingAs($this->tenantOwner);
        app(TenantContextService::class)->setTenantContext($this->tenantCompany, $this->tenantBranch);

        $role = Role::where('slug', 'tenant_admin')->first();

        // Add 2nd user (allowed)
        Livewire::test(UserManagement::class)
            ->set('name', 'Second User')
            ->set('email', 'user2@falconexpress.com')
            ->set('password', 'Password123!')
            ->set('branch_id', $this->tenantBranch->id)
            ->set('role_id', $role->id)
            ->set('status', 'active')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals(2, $this->tenantCompany->users()->count());
        $this->assertFalse($this->tenantCompany->fresh()->canAddUser());

        // Attempt to add 3rd user (must be blocked by quota limit)
        Livewire::test(UserManagement::class)
            ->set('name', 'Third User Exceeding')
            ->set('email', 'user3@falconexpress.com')
            ->set('password', 'Password123!')
            ->set('branch_id', $this->tenantBranch->id)
            ->set('role_id', $role->id)
            ->set('status', 'active')
            ->call('save')
            ->assertForbidden();

        // Assert 3rd user was not created
        $this->assertDatabaseMissing('users', ['email' => 'user3@falconexpress.com']);
    }

    /**
     * Test 9: Plan branch limit enforcement.
     */
    public function test_plan_branch_limit_enforcement(): void
    {
        // Starter plan max_branches = 1.
        $this->assertEquals(1, $this->tenantCompany->branches()->count());
        $this->assertFalse($this->tenantCompany->canAddBranch());

        $featureService = app(FeatureManagerService::class);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessageMatches('/Branch quota exceeded/i');

        $featureService->checkBranchLimit($this->tenantCompany);
    }

    /**
     * Test 10: Subscription lifecycle transitions (trial, active, suspend, renew, cancel).
     */
    public function test_subscription_lifecycle_state_transitions(): void
    {
        $subService = app(SubscriptionService::class);

        // 1. Create Trial Subscription
        $subscription = $subService->createSubscription($this->tenantCompany, $this->starterPlan, 'monthly', true);
        $this->assertTrue($subscription->isTrial());
        $this->assertFalse($subscription->isActive());
        $this->assertEquals('trial', $subscription->status);
        $this->assertEquals(0.00, (float) $subscription->price);

        // 2. Activate Subscription
        $activeSub = $subService->activateSubscription($subscription, 15000.00, 'CHQ-998822');
        $this->assertTrue($activeSub->isActive());
        $this->assertEquals('active', $activeSub->status);
        $this->assertEquals('active', $this->tenantCompany->fresh()->status);

        // Verify invoice was generated and payment recorded
        $invoice = SubscriptionInvoice::where('subscription_id', $activeSub->id)->latest()->first();
        $this->assertNotNull($invoice);
        $this->assertEquals(15000.00, (float) $invoice->paid_amount);

        // 3. Renew Subscription
        $oldEndsAt = $activeSub->ends_at;
        $renewedSub = $subService->renewSubscription($activeSub);
        $this->assertTrue($renewedSub->ends_at->isAfter($oldEndsAt));

        // 4. Suspend Subscription
        $suspendedSub = $subService->suspendSubscription($activeSub, 'Payment default');
        $this->assertEquals('suspended', $suspendedSub->status);
        $this->assertEquals('suspended', $this->tenantCompany->fresh()->status);

        // 5. Cancel Subscription
        $cancelledSub = $subService->cancelSubscription($activeSub, 'Customer requested termination');
        $this->assertEquals('cancelled', $cancelledSub->status);
        $this->assertEquals('suspended', $this->tenantCompany->fresh()->status);
    }

    /**
     * Test 11: SaaS Billing, Invoice Generation, Payment Verification, and MRR.
     */
    public function test_saas_billing_invoice_and_payment_verification_lifecycle(): void
    {
        $this->actingAs($this->superAdmin);
        $subService = app(SubscriptionService::class);

        // 1. Create active subscription
        $subscription = $subService->createSubscription($this->tenantCompany, $this->starterPlan, 'monthly', false);

        $invoice = SubscriptionInvoice::where('subscription_id', $subscription->id)->first();
        $this->assertNotNull($invoice);
        $this->assertEquals('unpaid', $invoice->status);
        $total = (float) $invoice->total_amount;
        $this->assertGreaterThan(0, $total);

        // 2. Super Admin records manual payment via BillingManager Livewire (enters pending_verification)
        Livewire::test(BillingManager::class)
            ->call('openRecordPaymentModal', $invoice->id)
            ->set('paymentAmount', $total)
            ->set('paymentMethod', 'bank_transfer')
            ->set('paymentReference', 'MCB-WIRE-778899')
            ->call('savePayment')
            ->assertHasNoErrors();

        // Invoice remains unpaid until payment is manually verified
        $invoice->refresh();
        $this->assertEquals('unpaid', $invoice->status);

        $payment = SubscriptionPayment::where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals('pending_verification', $payment->status);

        // 3. Super Admin verifies payment -> invoice balance cleared & marked paid
        Livewire::test(BillingManager::class)
            ->call('verifyPayment', $payment->id)
            ->assertHasNoErrors();

        $payment->refresh();
        $this->assertEquals('verified', $payment->status);
        $this->assertEquals($this->superAdmin->id, $payment->verified_by);
        $this->assertNotNull($payment->verified_at);

        $invoice->refresh();
        $this->assertEquals('paid', $invoice->status);
        $this->assertEquals(0.00, (float) $invoice->balance_amount);

        // 4. Verify MRR and Platform Revenue calculations
        $mrr = $subService->calculateMRR();
        $this->assertGreaterThan(0, $mrr);

        $totalRevenue = $subService->calculateTotalRevenue();
        $this->assertGreaterThan(0, $totalRevenue);
    }

    /**
     * Test 12: Super Admin Livewire action authorization rejects non-super admins.
     */
    public function test_saas_livewire_components_reject_unauthorized_users(): void
    {
        $this->actingAs($this->tenantOwner);

        // TenantManager rejects non-super admin
        Livewire::test(TenantManager::class)
            ->assertForbidden();

        // PlanManager rejects non-super admin
        Livewire::test(PlanManager::class)
            ->assertForbidden();

        // BillingManager rejects non-super admin
        Livewire::test(BillingManager::class)
            ->assertForbidden();
    }
}

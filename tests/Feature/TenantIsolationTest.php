<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\Role;
use App\Models\User;
use App\Services\TenantAuthorizationService;
use App\Services\TenantContextService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected Company $companyB;
    protected User $userA;
    protected User $userB;
    protected User $superAdmin;
    protected Branch $branchA;
    protected Branch $branchB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = Company::create([
            'name' => 'Falcon Express Ltd',
            'code' => 'FEC',
            'slug' => 'falcon-express',
            'status' => 'active',
        ]);

        $this->companyB = Company::create([
            'name' => 'Indus Freight Ltd',
            'code' => 'IFL',
            'slug' => 'indus-freight',
            'status' => 'active',
        ]);

        $adminRole = Role::create([
            'company_id' => null,
            'name' => 'Tenant Administrator',
            'slug' => 'tenant_admin',
            'is_system' => true,
        ]);

        $this->branchA = Branch::create([
            'company_id' => $this->companyA->id,
            'code' => 'FEC-LHR',
            'name' => 'Lahore Hub',
            'city' => 'Lahore',
            'is_head_office' => true,
            'is_active' => true,
        ]);

        $this->branchB = Branch::create([
            'company_id' => $this->companyB->id,
            'code' => 'IFL-ISB',
            'name' => 'Islamabad Hub',
            'city' => 'Islamabad',
            'is_head_office' => true,
            'is_active' => true,
        ]);

        $this->userA = User::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'role_id' => $adminRole->id,
            'name' => 'User Company A',
            'email' => 'user.a@falconexpress.com',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
            'is_super_admin' => false,
        ]);

        $this->userB = User::create([
            'company_id' => $this->companyB->id,
            'branch_id' => $this->branchB->id,
            'role_id' => $adminRole->id,
            'name' => 'User Company B',
            'email' => 'user.b@indusfreight.com',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
            'is_super_admin' => false,
        ]);

        $this->superAdmin = User::create([
            'company_id' => null,
            'branch_id' => null,
            'name' => 'Super Administrator',
            'email' => 'admin@platform.local',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
            'is_super_admin' => true,
        ]);
    }

    /**
     * Test Tenant A cannot access Tenant B records via scoped queries.
     */
    public function test_tenant_a_cannot_access_tenant_b_records(): void
    {
        $this->actingAs($this->userA);
        app(TenantContextService::class)->setCompany($this->companyA);

        // Querying branches through Eloquent must only return Company A branches
        $branches = Branch::all();

        $this->assertTrue($branches->contains('id', $this->branchA->id));
        $this->assertFalse($branches->contains('id', $this->branchB->id));
        $this->assertCount(1, $branches);

        // Querying users through Eloquent must only return Company A users
        $users = User::all();
        $this->assertTrue($users->contains('id', $this->userA->id));
        $this->assertFalse($users->contains('id', $this->userB->id));
    }

    /**
     * Test Tenant A cannot modify Tenant B records (service validation).
     */
    public function test_tenant_a_cannot_modify_tenant_b_records(): void
    {
        $this->actingAs($this->userA);
        app(TenantContextService::class)->setCompany($this->companyA);

        $tenantAuth = app(TenantAuthorizationService::class);

        // Attempting to validate ownership of Tenant B's branch must throw an AuthorizationException
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Cross-tenant violation');

        $tenantAuth->validateTenantOwnership($this->branchB);
    }

    /**
     * Test Tenant A cannot delete Tenant B records.
     */
    public function test_tenant_a_cannot_delete_tenant_b_records(): void
    {
        $this->actingAs($this->userA);
        app(TenantContextService::class)->setCompany($this->companyA);

        $tenantAuth = app(TenantAuthorizationService::class);

        $this->expectException(AuthorizationException::class);
        $tenantAuth->validateTenantOwnership($this->userB);
    }

    /**
     * Test Tenant ownership cannot be manipulated through request data.
     */
    public function test_tenant_ownership_cannot_be_manipulated_through_request_data(): void
    {
        $this->actingAs($this->userA);
        app(TenantContextService::class)->setCompany($this->companyA);

        $tenantAuth = app(TenantAuthorizationService::class);

        // Attacker injects Company B's ID in payload
        $maliciousPayload = [
            'name' => 'Hijacked Resource',
            'company_id' => $this->companyB->id,
        ];

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Manipulating tenant ownership via request data is strictly forbidden.');

        $tenantAuth->sanitizeAndValidatePayload($maliciousPayload);
    }

    /**
     * Test Tenant Owner must never receive Super Admin privileges.
     */
    public function test_tenant_owner_cannot_access_super_admin_saas_dashboard(): void
    {
        $this->actingAs($this->userA);

        $response = $this->get('/saas/dashboard');

        // Middleware EnsureSuperAdmin must reject non-super admins with 403
        $response->assertStatus(403);
    }

    /**
     * Test Platform Super Admin can access SaaS dashboard.
     */
    public function test_super_admin_can_access_saas_dashboard(): void
    {
        $this->actingAs($this->superAdmin);

        $response = $this->get('/saas/dashboard');
        $response->assertStatus(200);
        $response->assertSee('Platform SaaS Governance Console');
    }
}


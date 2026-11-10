<?php

namespace Tests\Feature;

use App\Livewire\Admin\UserManagement;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\TenantContextService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class LivewireAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected Company $companyB;
    protected Branch $branchA;
    protected Branch $branchB;
    protected Role $adminRole;
    protected Role $clerkRole;
    protected User $adminUserA;
    protected User $clerkUserA;
    protected User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = Company::create([
            'name' => 'Falcon Cargo A',
            'code' => 'FCA',
            'slug' => 'falcon-cargo-a',
            'status' => 'active',
        ]);

        $this->companyB = Company::create([
            'name' => 'Indus Logistics B',
            'code' => 'ILB',
            'slug' => 'indus-logistics-b',
            'status' => 'active',
        ]);

        $this->branchA = Branch::create([
            'company_id' => $this->companyA->id,
            'code' => 'FCA-LHR',
            'name' => 'Lahore Hub A',
            'city' => 'Lahore',
            'is_head_office' => true,
            'is_active' => true,
        ]);

        $this->branchB = Branch::create([
            'company_id' => $this->companyB->id,
            'code' => 'ILB-ISB',
            'name' => 'Islamabad Hub B',
            'city' => 'Islamabad',
            'is_head_office' => true,
            'is_active' => true,
        ]);

        $this->adminRole = Role::create([
            'company_id' => null,
            'name' => 'Tenant Administrator',
            'slug' => 'tenant_admin',
            'is_system' => true,
        ]);

        $this->clerkRole = Role::create([
            'company_id' => null,
            'name' => 'Booking Clerk',
            'slug' => 'booking_clerk',
            'is_system' => true,
        ]);

        $this->adminUserA = User::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'role_id' => $this->adminRole->id,
            'name' => 'Admin User A',
            'email' => 'admin.a@falconcargo.local',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
        ]);

        $this->clerkUserA = User::create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'role_id' => $this->clerkRole->id,
            'name' => 'Clerk User A',
            'email' => 'clerk.a@falconcargo.local',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
        ]);

        $this->userB = User::create([
            'company_id' => $this->companyB->id,
            'branch_id' => $this->branchB->id,
            'role_id' => $this->adminRole->id,
            'name' => 'User of Company B',
            'email' => 'admin.b@indus.local',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
    }

    /**
     * Test unauthorized user without manage_users permission cannot mount component.
     */
    public function test_unauthorized_user_cannot_access_user_management(): void
    {
        $this->actingAs($this->clerkUserA);
        app(TenantContextService::class)->setCompany($this->companyA);

        Livewire::test(UserManagement::class)
            ->assertForbidden();
    }

    /**
     * Test tenant admin can mount component and view their own users.
     */
    public function test_tenant_admin_can_access_user_management(): void
    {
        $this->actingAs($this->adminUserA);
        app(TenantContextService::class)->setCompany($this->companyA);

        Livewire::test(UserManagement::class)
            ->assertSee($this->adminUserA->name)
            ->assertSee($this->clerkUserA->name)
            ->assertDontSee($this->userB->name);
    }

    /**
     * Test tenant admin cannot edit user belonging to another tenant via Livewire.
     */
    public function test_tenant_admin_cannot_edit_user_of_another_tenant(): void
    {
        $this->actingAs($this->adminUserA);
        app(TenantContextService::class)->setCompany($this->companyA);

        Livewire::test(UserManagement::class)
            ->call('editUser', $this->userB->id)
            ->assertForbidden();
    }

    /**
     * Test tenant admin cannot delete user belonging to another tenant via Livewire.
     */
    public function test_tenant_admin_cannot_delete_user_of_another_tenant(): void
    {
        $this->actingAs($this->adminUserA);
        app(TenantContextService::class)->setCompany($this->companyA);

        Livewire::test(UserManagement::class)
            ->call('deleteUser', $this->userB->id)
            ->assertForbidden();
    }

    /**
     * Test user cannot delete their own account.
     */
    public function test_user_cannot_delete_themselves(): void
    {
        $this->actingAs($this->adminUserA);
        app(TenantContextService::class)->setCompany($this->companyA);

        Livewire::test(UserManagement::class)
            ->call('deleteUser', $this->adminUserA->id)
            ->assertSee('You cannot delete your own account.');

        $this->assertDatabaseHas('users', ['id' => $this->adminUserA->id, 'deleted_at' => null]);
    }
}


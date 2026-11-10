<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\BranchAccessService;
use App\Services\TenantContextService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BranchAccessTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Branch $branchLahore;
    protected Branch $branchKarachi;
    protected Branch $branchMultan;
    protected User $singleBranchUser;
    protected User $multiBranchUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Falcon Logistics',
            'code' => 'FL',
            'slug' => 'falcon-logistics',
            'status' => 'active',
        ]);

        $this->branchLahore = Branch::create([
            'company_id' => $this->company->id,
            'code' => 'FL-LHR',
            'name' => 'Lahore Hub',
            'city' => 'Lahore',
            'is_head_office' => true,
            'is_active' => true,
        ]);

        $this->branchKarachi = Branch::create([
            'company_id' => $this->company->id,
            'code' => 'FL-KHI',
            'name' => 'Karachi Hub',
            'city' => 'Karachi',
            'is_head_office' => false,
            'is_active' => true,
        ]);

        $this->branchMultan = Branch::create([
            'company_id' => $this->company->id,
            'code' => 'FL-MUX',
            'name' => 'Multan Hub',
            'city' => 'Multan',
            'is_head_office' => false,
            'is_active' => true,
        ]);

        $staffRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Booking Clerk',
            'slug' => 'booking_clerk',
            'is_system' => false,
        ]);

        // User assigned strictly to Lahore
        $this->singleBranchUser = User::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branchLahore->id,
            'role_id' => $staffRole->id,
            'name' => 'Lahore Clerk',
            'email' => 'clerk.lhr@falcon.local',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
        ]);

        // User assigned to Lahore (primary) and Karachi (secondary)
        $this->multiBranchUser = User::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branchLahore->id,
            'role_id' => $staffRole->id,
            'name' => 'Regional Dispatcher',
            'email' => 'dispatcher@falcon.local',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
        ]);

        $this->multiBranchUser->branches()->attach($this->branchKarachi->id);
    }

    /**
     * Test single-branch user cannot access unauthorized branch.
     */
    public function test_unauthorized_branch_access_is_rejected(): void
    {
        $branchService = app(BranchAccessService::class);

        // Lahore clerk has access to Lahore
        $this->assertTrue($branchService->userHasBranchAccess($this->singleBranchUser, $this->branchLahore->id));

        // Lahore clerk is rejected from Karachi and Multan
        $this->assertFalse($branchService->userHasBranchAccess($this->singleBranchUser, $this->branchKarachi->id));
        $this->assertFalse($branchService->userHasBranchAccess($this->singleBranchUser, $this->branchMultan->id));

        // Switching branch must throw AuthorizationException
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('You are not authorized to switch to this branch.');

        $branchService->switchActiveBranch($this->singleBranchUser, $this->branchKarachi->id);
    }

    /**
     * Test multi-branch user can switch between authorized branches.
     */
    public function test_authorized_multi_branch_user_can_switch_branches(): void
    {
        $branchService = app(BranchAccessService::class);

        $this->assertTrue($branchService->userHasBranchAccess($this->multiBranchUser, $this->branchLahore->id));
        $this->assertTrue($branchService->userHasBranchAccess($this->multiBranchUser, $this->branchKarachi->id));
        $this->assertFalse($branchService->userHasBranchAccess($this->multiBranchUser, $this->branchMultan->id));

        // Switch to Karachi
        $branchService->switchActiveBranch($this->multiBranchUser, $this->branchKarachi->id);

        $this->assertEquals($this->branchKarachi->id, $this->multiBranchUser->fresh()->branch_id);
        $this->assertEquals($this->branchKarachi->id, app(TenantContextService::class)->getBranchId());
    }

    /**
     * Test branch switch endpoint via HTTP POST.
     */
    public function test_branch_switch_via_http_post(): void
    {
        $this->actingAs($this->multiBranchUser);
        app(TenantContextService::class)->setCompany($this->company);

        $response = $this->post('/branch/switch', [
            'branch_id' => $this->branchKarachi->id,
        ]);

        $response->assertSessionHas('status', 'Active branch switched successfully.');
        $response->assertRedirect();
    }
}


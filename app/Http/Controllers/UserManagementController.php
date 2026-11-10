<?php

namespace App\Http\Controllers;

use App\Services\BranchAccessService;
use App\Services\TenantContextService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function __construct(
        protected TenantContextService $tenantContext,
        protected BranchAccessService $branchAccessService
    ) {}

    /**
     * Display user management console.
     */
    public function index(Request $request): View
    {
        $user = auth()->user();
        $company = $this->tenantContext->getCompany();
        $accessibleBranches = $this->branchAccessService->getUserAccessibleBranches($user);

        return view('admin.users.index', [
            'user' => $user,
            'company' => $company,
            'accessibleBranches' => $accessibleBranches,
        ]);
    }
}


<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use App\Services\BranchAccessService;
use App\Services\TenantContextService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected TenantContextService $tenantContext,
        protected BranchAccessService $branchAccessService
    ) {}

    /**
     * Display tenant logistics dashboard.
     */
    public function index(Request $request): View
    {
        $user = auth()->user();
        $company = $this->tenantContext->getCompany();
        $activeBranchId = $this->tenantContext->getBranchId();

        $activeBranch = $activeBranchId ? Branch::find($activeBranchId) : null;
        $accessibleBranches = $this->branchAccessService->getUserAccessibleBranches($user);

        // Operational stats scoped by tenant
        $branchCount = Branch::count();
        $userCount = User::count();

        return view('dashboard', [
            'user' => $user,
            'company' => $company,
            'activeBranch' => $activeBranch,
            'accessibleBranches' => $accessibleBranches,
            'branchCount' => $branchCount,
            'userCount' => $userCount,
        ]);
    }
}


<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use App\Services\BranchAccessService;
use App\Services\BusinessIntelligenceService;
use App\Services\TenantContextService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected TenantContextService $tenantContext,
        protected BranchAccessService $branchAccessService,
        protected BusinessIntelligenceService $biService
    ) {}

    /**
     * Display tenant logistics executive dashboard.
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

        // Phase 11 Executive Dashboard Metrics
        $executiveMetrics = [];
        if ($company) {
            $executiveMetrics = $this->biService->getExecutiveDashboardMetrics(
                companyId: $company->id,
                branchId: $activeBranchId,
                fromDate: now()->startOfMonth()->toDateString(),
                toDate: now()->toDateString()
            );
        }

        return view('dashboard', [
            'user' => $user,
            'company' => $company,
            'activeBranch' => $activeBranch,
            'accessibleBranches' => $accessibleBranches,
            'branchCount' => $branchCount,
            'userCount' => $userCount,
            'kpis' => $executiveMetrics,
        ]);
    }
}

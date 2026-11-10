<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Services\TenantContextService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    public function __construct(
        protected TenantContextService $tenantContext
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            $user = auth()->user();

            if ($user->isSuperAdmin()) {
                // Check if Super Admin is explicitly scoping to a specific tenant company
                $scopedCompanyId = $request->header('X-Tenant-ID') ?? $request->query('scoped_company_id');

                if ($scopedCompanyId) {
                    $company = Company::find($scopedCompanyId);
                    if ($company) {
                        $this->tenantContext->setCompany($company);
                    }
                } else {
                    // Global Super Admin mode
                    $this->tenantContext->setBypassTenant(true);
                }
            } elseif ($user->company_id) {
                // Standard Tenant User
                $company = Company::find($user->company_id);

                if (!$company) {
                    auth()->logout();
                    return redirect()->route('login')->withErrors(['email' => 'Assigned transport company not found.']);
                }

                if ($company->status !== 'active') {
                    auth()->logout();
                    return redirect()->route('login')->withErrors([
                        'email' => sprintf('Your company account is currently [%s]. Please contact platform support.', $company->status)
                    ]);
                }

                $this->tenantContext->setCompany($company);

                // Resolve active branch
                $activeBranchId = session('active_branch_id', $user->branch_id);
                $this->tenantContext->setBranchId($activeBranchId ? (int) $activeBranchId : null);

                // Check branch-wide access permissions
                if ($user->isTenantAdmin() || $user->hasPermission('all_branches_access')) {
                    $this->tenantContext->setAllBranchAccess(true);
                }
            }
        }

        return $next($request);
    }
}


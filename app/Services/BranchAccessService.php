<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;

class BranchAccessService
{
    public function __construct(
        protected TenantContextService $tenantContext
    ) {}

    /**
     * Determine if a user has access to a specific branch.
     */
    public function userHasBranchAccess(User $user, int $branchId): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Branch must belong to user's company
        if ((int) $user->company_id !== (int) $this->getBranchCompanyId($branchId)) {
            return false;
        }

        // Company owner or admin has access to all branches
        if ($user->isTenantAdmin() || $user->hasPermission('all_branches_access')) {
            return true;
        }

        // Primary assigned branch
        if ((int) $user->branch_id === (int) $branchId) {
            return true;
        }

        // Secondary authorized branches (many-to-many user_branches)
        return $user->branches()->where('branches.id', $branchId)->exists();
    }

    /**
     * Get all branches a user is authorized to access within their company.
     */
    public function getUserAccessibleBranches(User $user): Collection
    {
        if ($user->isSuperAdmin()) {
            return Branch::all();
        }

        if (!$user->company_id) {
            return collect();
        }

        if ($user->isTenantAdmin() || $user->hasPermission('all_branches_access')) {
            return Branch::where('company_id', $user->company_id)->where('is_active', true)->get();
        }

        return Branch::where('company_id', $user->company_id)
            ->where('is_active', true)
            ->where(function ($query) use ($user) {
                $query->where('id', $user->branch_id)
                    ->orWhereIn('id', function ($subQuery) use ($user) {
                        $subQuery->select('branch_id')
                            ->from('user_branches')
                            ->where('user_id', $user->id);
                    });
            })
            ->get();
    }

    /**
     * Switch user's active branch session.
     *
     * @throws AuthorizationException
     */
    public function switchActiveBranch(User $user, int $branchId): void
    {
        if (!$this->userHasBranchAccess($user, $branchId)) {
            throw new AuthorizationException('You are not authorized to switch to this branch.');
        }

        $user->branch_id = $branchId;
        $user->saveQuietly();

        $this->tenantContext->setBranchId($branchId);
        session(['active_branch_id' => $branchId]);
    }

    /**
     * Get the company ID for a given branch.
     */
    protected function getBranchCompanyId(int $branchId): ?int
    {
        return Branch::withoutGlobalScopes()->where('id', $branchId)->value('company_id');
    }
}


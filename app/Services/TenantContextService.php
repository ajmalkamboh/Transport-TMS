<?php

namespace App\Services;

use App\Models\Company;

class TenantContextService
{
    protected ?Company $company = null;
    protected ?int $branchId = null;
    protected bool $bypassTenant = false;
    protected bool $allBranchAccess = false;

    /**
     * Set the active tenant company.
     */
    public function setCompany(?Company $company): void
    {
        $this->company = $company;
    }

    /**
     * Set full tenant context (company and branch).
     */
    public function setTenantContext(?Company $company, mixed $branch = null): void
    {
        $this->setCompany($company);
        if ($branch instanceof \App\Models\Branch) {
            $this->setBranchId($branch->id);
        } elseif (is_int($branch) || is_null($branch)) {
            $this->setBranchId($branch);
        }
    }

    /**
     * Get the active tenant company.
     */
    public function getCompany(): ?Company
    {
        return $this->company;
    }

    /**
     * Get the active company ID.
     */
    public function getCompanyId(): ?int
    {
        return $this->company?->id;
    }

    /**
     * Set the active branch ID.
     */
    public function setBranchId(?int $branchId): void
    {
        $this->branchId = $branchId;
    }

    /**
     * Get the active branch ID.
     */
    public function getBranchId(): ?int
    {
        return $this->branchId;
    }

    /**
     * Set whether the current context has all-branch access.
     */
    public function setAllBranchAccess(bool $access): void
    {
        $this->allBranchAccess = $access;
    }

    /**
     * Determine if current context can access all branches.
     */
    public function hasAllBranchAccess(): bool
    {
        return $this->allBranchAccess || $this->isSuperAdmin();
    }

    /**
     * Set tenant bypass mode (Super Admin only).
     */
    public function setBypassTenant(bool $bypass): void
    {
        $this->bypassTenant = $bypass;
    }

    /**
     * Check if tenant isolation is bypassed.
     */
    public function isBypassingTenant(): bool
    {
        return $this->bypassTenant;
    }

    /**
     * Check if authenticated user is Super Admin.
     */
    public function isSuperAdmin(): bool
    {
        if (auth()->check()) {
            return (bool) (auth()->user()->is_super_admin ?? false);
        }
        return false;
    }

    /**
     * Clear tenant and branch state.
     */
    public function clear(): void
    {
        $this->company = null;
        $this->branchId = null;
        $this->bypassTenant = false;
        $this->allBranchAccess = false;
    }
}


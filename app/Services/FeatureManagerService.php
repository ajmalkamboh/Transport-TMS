<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Auth\Access\AuthorizationException;

class FeatureManagerService
{
    /**
     * Check if a company has access to a specific feature.
     */
    public function hasFeature(Company|int $company, string $featureSlug): bool
    {
        if (is_int($company)) {
            $company = Company::find($company);
        }

        if (!$company) {
            return false;
        }

        return $company->hasFeature($featureSlug);
    }

    /**
     * Assert company has feature, throwing exception if disabled.
     *
     * @throws AuthorizationException
     */
    public function enforceFeature(Company|int $company, string $featureSlug): void
    {
        if (!$this->hasFeature($company, $featureSlug)) {
            throw new AuthorizationException(
                sprintf('Feature [%s] is not included in your current subscription plan.', $featureSlug)
            );
        }
    }

    /**
     * Enforce user quota for tenant.
     *
     * @throws AuthorizationException
     */
    public function checkUserLimit(Company $company): void
    {
        if (!$company->canAddUser()) {
            throw new AuthorizationException(
                sprintf('User quota exceeded. Your plan [%s] permits a maximum of %d users.', $company->plan?->name, $company->plan?->max_users)
            );
        }
    }

    /**
     * Enforce branch quota for tenant.
     *
     * @throws AuthorizationException
     */
    public function checkBranchLimit(Company $company): void
    {
        if (!$company->canAddBranch()) {
            throw new AuthorizationException(
                sprintf('Branch quota exceeded. Your plan [%s] permits a maximum of %d operational branches.', $company->plan?->name, $company->plan?->max_branches)
            );
        }
    }

    /**
     * Configure a tenant-specific feature override.
     */
    public function setFeatureOverride(Company $company, string $featureSlug, bool $enabled): void
    {
        $overrides = $company->feature_overrides ?? [];
        $overrides[$featureSlug] = $enabled;

        $company->feature_overrides = $overrides;
        $company->save();
    }

    /**
     * Remove a feature override, reverting to plan default.
     */
    public function removeFeatureOverride(Company $company, string $featureSlug): void
    {
        $overrides = $company->feature_overrides ?? [];
        unset($overrides[$featureSlug]);

        $company->feature_overrides = $overrides;
        $company->save();
    }
}

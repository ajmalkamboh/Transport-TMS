<?php

namespace App\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

class TenantAuthorizationService
{
    public function __construct(
        protected TenantContextService $tenantContext
    ) {}

    /**
     * Validate that a given model belongs to the active tenant.
     * Throws an AuthorizationException on any cross-tenant tampering attempt.
     *
     * @param Model|object $model
     * @param int|null $explicitCompanyId
     * @throws AuthorizationException
     */
    public function validateTenantOwnership(mixed $model, ?int $explicitCompanyId = null): void
    {
        // Super Admin in bypass mode has global access
        if ($this->tenantContext->isSuperAdmin() && $this->tenantContext->isBypassingTenant()) {
            return;
        }

        $activeCompanyId = $explicitCompanyId ?? $this->tenantContext->getCompanyId();

        if ($activeCompanyId === null) {
            throw new AuthorizationException('No active tenant context found for operation.');
        }

        $modelCompanyId = null;

        if ($model instanceof Model) {
            $modelCompanyId = $model->getAttribute('company_id');
        } elseif (is_object($model) && isset($model->company_id)) {
            $modelCompanyId = $model->company_id;
        } elseif (is_array($model) && isset($model['company_id'])) {
            $modelCompanyId = $model['company_id'];
        }

        if ($modelCompanyId !== null && (int) $modelCompanyId !== (int) $activeCompanyId) {
            throw new AuthorizationException(
                sprintf('Cross-tenant violation: Model belongs to company [%s], but active tenant is [%s].', $modelCompanyId, $activeCompanyId)
            );
        }
    }

    /**
     * Ensure request payload does not attempt to manipulate company_id across tenant boundaries.
     *
     * @param array $payload
     * @return array Sanitized payload
     * @throws AuthorizationException
     */
    public function sanitizeAndValidatePayload(array $payload): array
    {
        $activeCompanyId = $this->tenantContext->getCompanyId();

        if (isset($payload['company_id'])) {
            if (!$this->tenantContext->isSuperAdmin() && (int) $payload['company_id'] !== (int) $activeCompanyId) {
                throw new AuthorizationException('Manipulating tenant ownership via request data is strictly forbidden.');
            }
        }

        // Always lock company_id to the authenticated tenant
        if (!$this->tenantContext->isSuperAdmin() && $activeCompanyId !== null) {
            $payload['company_id'] = $activeCompanyId;
        }

        return $payload;
    }

    /**
     * Validate branch ownership if applicable.
     *
     * @param mixed $model
     * @throws AuthorizationException
     */
    public function validateBranchOwnership(mixed $model): void
    {
        if ($this->tenantContext->hasAllBranchAccess()) {
            return;
        }

        $activeBranchId = $this->tenantContext->getBranchId();

        if ($activeBranchId === null) {
            return;
        }

        $modelBranchId = null;
        if ($model instanceof Model) {
            $modelBranchId = $model->getAttribute('branch_id');
        } elseif (is_object($model) && isset($model->branch_id)) {
            $modelBranchId = $model->branch_id;
        }

        if ($modelBranchId !== null && (int) $modelBranchId !== (int) $activeBranchId) {
            throw new AuthorizationException('Cross-branch violation: You are not authorized to access this branch record.');
        }
    }
}


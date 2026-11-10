<?php

namespace App\Traits;

use App\Models\Branch;
use App\Services\TenantContextService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToBranch
{
    /**
     * Boot the BelongsToBranch trait.
     */
    protected static function bootBelongsToBranch(): void
    {
        static::addGlobalScope('branch', function (Builder $builder) {
            if (!app()->bound(TenantContextService::class)) {
                return;
            }

            /** @var TenantContextService $tenantContext */
            $tenantContext = app(TenantContextService::class);

            // If user has all-branch access or is Super Admin / Tenant Owner, skip branch filter
            if ($tenantContext->hasAllBranchAccess()) {
                return;
            }

            $branchId = $tenantContext->getBranchId();

            if ($branchId !== null) {
                $builder->where($builder->getModel()->getTable() . '.branch_id', $branchId);
            }
        });

        static::creating(function ($model) {
            if (empty($model->branch_id) && app()->bound(TenantContextService::class)) {
                $tenantContext = app(TenantContextService::class);
                $branchId = $tenantContext->getBranchId();

                if ($branchId !== null) {
                    $model->branch_id = $branchId;
                } elseif (auth()->check() && auth()->user()->branch_id) {
                    $model->branch_id = auth()->user()->branch_id;
                }
            }
        });
    }

    /**
     * Relationship to Branch.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * Scope to bypass branch filter.
     */
    public function scopeWithoutBranchScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('branch');
    }

    /**
     * Explicitly scope query to a specific branch.
     */
    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->withoutGlobalScope('branch')->where($query->getModel()->getTable() . '.branch_id', $branchId);
    }
}


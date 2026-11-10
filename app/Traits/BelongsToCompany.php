<?php

namespace App\Traits;

use App\Models\Company;
use App\Services\TenantContextService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCompany
{
    /**
     * Boot the BelongsToCompany trait.
     */
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            if (!app()->bound(TenantContextService::class)) {
                return;
            }

            /** @var TenantContextService $tenantContext */
            $tenantContext = app(TenantContextService::class);

            // Super Admin can view all records when explicitly bypassing tenant context
            if ($tenantContext->isSuperAdmin() && $tenantContext->isBypassingTenant()) {
                return;
            }

            $companyId = $tenantContext->getCompanyId();

            if ($companyId !== null) {
                $builder->where($builder->getModel()->getTable() . '.company_id', $companyId);
            } elseif (!app()->runningInConsole()) {
                // Fail-closed: If no tenant context is set in an HTTP request, return no records
                $builder->whereRaw('1 = 0');
            }
        });

        static::creating(function ($model) {
            if (empty($model->company_id) && app()->bound(TenantContextService::class)) {
                $tenantContext = app(TenantContextService::class);
                $companyId = $tenantContext->getCompanyId();

                if ($companyId !== null) {
                    $model->company_id = $companyId;
                } elseif (auth()->check() && auth()->user()->company_id) {
                    $model->company_id = auth()->user()->company_id;
                }
            }
        });
    }

    /**
     * Relationship to the owning Company.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Query scope to bypass the company global scope (administrative use only).
     */
    public function scopeWithoutCompanyScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('company');
    }
}


<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    protected $table = 'roles';

    protected $fillable = [
        'company_id',
        'name',
        'slug',
        'description',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    /**
     * Override boot to allow system roles (company_id is null) to be visible to all tenants.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('company_or_system', function (Builder $builder) {
            if (app()->bound(\App\Services\TenantContextService::class)) {
                $tenantContext = app(\App\Services\TenantContextService::class);
                $companyId = $tenantContext->getCompanyId();

                if ($companyId !== null) {
                    $builder->where(function ($q) use ($companyId) {
                        $q->where('roles.company_id', $companyId)
                          ->orWhereNull('roles.company_id');
                    });
                }
            }
        });
    }

    /**
     * Permissions assigned to this role.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions', 'role_id', 'permission_id')
            ->withTimestamps();
    }

    /**
     * Users assigned to this role.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Check if this role possesses a specific permission slug.
     */
    public function hasPermission(string $slug): bool
    {
        return $this->permissions->contains('slug', $slug);
    }
}


<?php

namespace App\Models;

use App\Services\BranchAccessService;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $table = 'users';

    protected $fillable = [
        'company_id',
        'branch_id',
        'customer_id',
        'vehicle_owner_id',
        'driver_id',
        'role_id',
        'name',
        'email',
        'password',
        'phone',
        'status', // active, inactive, suspended
        'is_super_admin',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_super_admin' => 'boolean',
        'last_login_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Booted method: apply tenant scoping unless Super Admin.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            if (!app()->bound(\App\Services\TenantContextService::class)) {
                return;
            }

            /** @var \App\Services\TenantContextService $tenantContext */
            $tenantContext = app(\App\Services\TenantContextService::class);

            if ($tenantContext->isBypassingTenant() || $tenantContext->isSuperAdmin()) {
                return;
            }

            $companyId = $tenantContext->getCompanyId();

            if ($companyId !== null) {
                $builder->where('users.company_id', $companyId);
            }
        });
    }

    /**
     * The transport company this user belongs to.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Primary default branch.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * Assigned role.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function vehicleOwner(): BelongsTo
    {
        return $this->belongsTo(VehicleOwner::class, 'vehicle_owner_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    /**
     * All authorized secondary branches (many-to-many).
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'user_branches', 'user_id', 'branch_id')
            ->withTimestamps();
    }

    /**
     * Audit logs created by this user.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Check if user is Platform Super Administrator.
     */
    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    /**
     * Check if user is Tenant Company Owner or Admin.
     */
    public function isTenantAdmin(): bool
    {
        if ($this->isSuperAdmin()) {
            return false;
        }

        return $this->role?->slug === 'tenant_admin' || $this->role?->slug === 'company_owner';
    }

    /**
     * Check if user possesses a specific permission.
     */
    public function hasPermission(string $permissionSlug): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->isTenantAdmin()) {
            return true;
        }

        return $this->role?->hasPermission($permissionSlug) ?? false;
    }

    /**
     * Check if user has a specific role slug.
     */
    public function hasRole(string $roleSlug): bool
    {
        return $this->role?->slug === $roleSlug;
    }

    /**
     * Check if user has authorized access to a given branch.
     */
    public function hasBranchAccess(int $branchId): bool
    {
        return app(BranchAccessService::class)->userHasBranchAccess($this, $branchId);
    }
}


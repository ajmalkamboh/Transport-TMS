<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VehicleOwner extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $table = 'vehicle_owners';

    protected $fillable = [
        'company_id',
        'name',
        'company_name',
        'cnic_number',
        'phone',
        'secondary_phone',
        'email',
        'address',
        'city',
        'bank_name',
        'bank_account_title',
        'bank_account_number',
        'bank_iban',
        'tax_number',
        'status', // 'active', 'inactive', 'blacklisted'
        'notes',
    ];

    /**
     * Vehicles owned by this third-party owner.
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'vehicle_owner_id');
    }

    /**
     * All commercial contracts with this owner.
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(OwnerContract::class, 'vehicle_owner_id')->orderByDesc('start_date');
    }

    /**
     * Currently active commercial contract.
     */
    public function activeContract(): HasOne
    {
        return $this->hasOne(OwnerContract::class, 'vehicle_owner_id')
            ->where('status', 'active')
            ->whereDate('start_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', now());
            });
    }

    /**
     * Verification and identity documents.
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(FleetDocument::class, 'documentable');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isBlacklisted(): bool
    {
        return $this->status === 'blacklisted';
    }
}


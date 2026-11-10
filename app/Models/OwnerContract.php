<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OwnerContract extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $table = 'owner_contracts';

    // 5 Standard Owner Contract Payment Models
    public const MODEL_FIXED_PER_TRIP = 'fixed_per_trip';
    public const MODEL_PERCENTAGE_OF_FREIGHT = 'percentage_of_freight';
    public const MODEL_PER_KM = 'per_km';
    public const MODEL_MONTHLY_RENTAL = 'monthly_rental';
    public const MODEL_ROUTE_SPECIFIC = 'route_specific';

    public const ALL_MODELS = [
        self::MODEL_FIXED_PER_TRIP => 'Fixed Rate Per Trip (Flat Hire)',
        self::MODEL_PERCENTAGE_OF_FREIGHT => 'Percentage of Freight (Commission Model)',
        self::MODEL_PER_KM => 'Per-Kilometre Rate',
        self::MODEL_MONTHLY_RENTAL => 'Fixed Monthly Rental (Dedicated Lease)',
        self::MODEL_ROUTE_SPECIFIC => 'Route-Specific Freight Agreement',
    ];

    protected $fillable = [
        'company_id',
        'vehicle_owner_id',
        'contract_number',
        'payment_model',
        'rate_amount',
        'freight_percentage',
        'commission_percentage',
        'deduct_fuel',
        'deduct_toll_taxes',
        'deduct_advances',
        'route_origin',
        'route_destination',
        'start_date',
        'end_date',
        'status', // 'active', 'expired', 'terminated'
        'terms_and_conditions',
    ];

    protected $casts = [
        'rate_amount' => 'decimal:2',
        'freight_percentage' => 'decimal:2',
        'commission_percentage' => 'decimal:2',
        'deduct_fuel' => 'boolean',
        'deduct_toll_taxes' => 'boolean',
        'deduct_advances' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Contracted vehicle owner.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(VehicleOwner::class, 'vehicle_owner_id');
    }

    /**
     * Vehicles linked to this contract.
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'active_contract_id');
    }

    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $now = now()->startOfDay();
        if ($this->start_date->isAfter($now)) {
            return false;
        }

        if ($this->end_date && $this->end_date->isBefore($now)) {
            return false;
        }

        return true;
    }
}


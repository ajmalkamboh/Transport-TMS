<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $table = 'vehicles';

    public const OWNERSHIP_COMPANY = 'company_owned';
    public const OWNERSHIP_ATTACHED = 'market_attached';
    public const OWNERSHIP_RENTED = 'rented';

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_ON_TRIP = 'on_trip';
    public const STATUS_MAINTENANCE = 'maintenance';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_RETIRED = 'retired';

    protected $fillable = [
        'company_id',
        'branch_id',
        'vehicle_owner_id',
        'active_contract_id',
        'registration_number',
        'fleet_number',
        'ownership_type',
        'vehicle_category',
        'vehicle_type',
        'make',
        'model',
        'manufacturing_year',
        'chassis_number',
        'engine_number',
        'payload_capacity_tons',
        'volume_capacity_cbm',
        'fuel_type',
        'fuel_tank_capacity_liters',
        'current_odometer_km',
        'registration_expiry_date',
        'insurance_expiry_date',
        'fitness_expiry_date',
        'route_permit_expiry_date',
        'status',
        'current_driver_id',
        'notes',
    ];

    protected $casts = [
        'manufacturing_year' => 'integer',
        'payload_capacity_tons' => 'decimal:2',
        'volume_capacity_cbm' => 'decimal:2',
        'fuel_tank_capacity_liters' => 'decimal:2',
        'current_odometer_km' => 'integer',
        'registration_expiry_date' => 'date',
        'insurance_expiry_date' => 'date',
        'fitness_expiry_date' => 'date',
        'route_permit_expiry_date' => 'date',
    ];

    /**
     * Home operational terminal / branch.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * Attached third-party vehicle owner.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(VehicleOwner::class, 'vehicle_owner_id');
    }

    /**
     * Commercial contract governing attached truck payments.
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(OwnerContract::class, 'active_contract_id');
    }

    /**
     * Currently assigned primary driver.
     */
    public function currentDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'current_driver_id');
    }

    /**
     * Driver assignment history.
     */
    public function driverAssignments(): HasMany
    {
        return $this->hasMany(VehicleDriverAssignment::class, 'vehicle_id')->orderByDesc('assigned_at');
    }

    /**
     * Active driver assignments (primary + co-driver).
     */
    public function activeDriverAssignments(): HasMany
    {
        return $this->hasMany(VehicleDriverAssignment::class, 'vehicle_id')->where('status', 'active');
    }

    public function getCurrentOdometerAttribute(): int
    {
        return (int) ($this->attributes['current_odometer_km'] ?? 0);
    }

    public function setCurrentOdometerAttribute($value): void
    {
        $this->attributes['current_odometer_km'] = (int) $value;
    }

    public function getMakeModelAttribute(): string
    {
        $make = $this->attributes['make'] ?? '';
        $model = $this->attributes['model'] ?? '';
        $combined = trim("{$make} {$model}");
        return $combined ?: ($this->attributes['vehicle_type'] ?? 'Truck');
    }

    public function getCapacityTonsAttribute(): float
    {
        return (float) ($this->attributes['payload_capacity_tons'] ?? 0.0);
    }

    /**
     * Vehicle documentation & compliance files.
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(FleetDocument::class, 'documentable');
    }

    public function isCompanyOwned(): bool
    {
        return $this->ownership_type === self::OWNERSHIP_COMPANY;
    }

    public function isMarketAttached(): bool
    {
        return in_array($this->ownership_type, [self::OWNERSHIP_ATTACHED, self::OWNERSHIP_RENTED]);
    }

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE;
    }
}


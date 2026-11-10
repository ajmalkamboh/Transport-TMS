<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Driver extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $table = 'drivers';

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_ON_TRIP = 'on_trip';
    public const STATUS_ON_DUTY = 'on_trip';
    public const STATUS_ON_LEAVE = 'on_leave';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_TERMINATED = 'terminated';

    protected $fillable = [
        'company_id',
        'branch_id',
        'current_vehicle_id',
        'name',
        'father_name',
        'cnic_number',
        'phone',
        'secondary_phone',
        'license_number',
        'license_category',
        'license_expiry_date',
        'license_issuing_authority',
        'date_of_birth',
        'blood_group',
        'address',
        'emergency_contact_name',
        'emergency_contact_relation',
        'emergency_contact_phone',
        'employment_type',
        'basic_salary',
        'per_trip_allowance',
        'daily_batta',
        'status',
        'total_advances_balance',
        'photo_path',
        'notes',
    ];

    protected $casts = [
        'license_expiry_date' => 'date',
        'date_of_birth' => 'date',
        'basic_salary' => 'decimal:2',
        'per_trip_allowance' => 'decimal:2',
        'daily_batta' => 'decimal:2',
        'total_advances_balance' => 'decimal:2',
    ];

    /**
     * Home terminal branch.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * Currently assigned truck.
     */
    public function currentVehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'current_vehicle_id');
    }

    /**
     * Vehicle assignment history.
     */
    public function vehicleAssignments(): HasMany
    {
        return $this->hasMany(VehicleDriverAssignment::class, 'driver_id')->orderByDesc('assigned_at');
    }

    /**
     * Trip and operational advances.
     */
    public function advances(): HasMany
    {
        return $this->hasMany(DriverAdvance::class, 'driver_id')->orderByDesc('date');
    }

    public function getCnicAttribute(): ?string
    {
        return $this->attributes['cnic_number'] ?? null;
    }

    public function setCnicAttribute($value): void
    {
        $this->attributes['cnic_number'] = $value;
    }

    /**
     * Driver documents (CNIC copy, Driving License, Police Verification).
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(FleetDocument::class, 'documentable');
    }

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE;
    }

    public function isLicenseExpired(): bool
    {
        return $this->license_expiry_date->isPast();
    }
}


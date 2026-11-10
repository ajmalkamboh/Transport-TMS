<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripResource extends Model
{
    use HasFactory, BelongsToCompany;

    protected $table = 'trip_resources';

    public const TYPE_VEHICLE = 'vehicle';
    public const TYPE_DRIVER = 'driver';

    public const ROLE_PRIMARY_VEHICLE = 'primary_vehicle';
    public const ROLE_TRAILER = 'trailer';
    public const ROLE_SECONDARY_VEHICLE = 'secondary_vehicle';
    public const ROLE_PRIMARY_DRIVER = 'primary_driver';
    public const ROLE_CO_DRIVER = 'co_driver';

    protected $fillable = [
        'company_id',
        'trip_id',
        'vehicle_id',
        'driver_id',
        'resource_type',
        'role',
        'assigned_at',
        'released_at',
        'is_active',
        'override_allowed',
        'override_reason',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'released_at' => 'datetime',
        'is_active' => 'boolean',
        'override_allowed' => 'boolean',
    ];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function release(): void
    {
        $this->is_active = false;
        $this->released_at = now();
        $this->save();

        if ($this->vehicle) {
            $this->vehicle->status = Vehicle::STATUS_AVAILABLE;
            $this->vehicle->save();
        }

        if ($this->driver) {
            $this->driver->status = Driver::STATUS_AVAILABLE;
            $this->driver->save();
        }
    }
}


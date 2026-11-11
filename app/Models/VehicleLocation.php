<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleLocation extends Model
{
    use HasFactory, BelongsToCompany;

    protected $table = 'vehicle_locations';

    protected $fillable = [
        'company_id',
        'vehicle_id',
        'trip_id',
        'gps_device_id',
        'latitude',
        'longitude',
        'altitude_meters',
        'speed_kmh',
        'heading_degrees',
        'ignition_status',
        'satellites_count',
        'odometer_km',
        'battery_level_percent',
        'raw_payload',
        'recorded_at',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'altitude_meters' => 'decimal:2',
        'speed_kmh' => 'decimal:2',
        'heading_degrees' => 'decimal:2',
        'ignition_status' => 'boolean',
        'satellites_count' => 'integer',
        'odometer_km' => 'integer',
        'battery_level_percent' => 'decimal:2',
        'raw_payload' => 'array',
        'recorded_at' => 'datetime',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(GpsDevice::class, 'gps_device_id');
    }
}


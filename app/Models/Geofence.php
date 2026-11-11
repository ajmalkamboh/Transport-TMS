<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Geofence extends Model
{
    use HasFactory, BelongsToCompany;

    protected $table = 'geofences';

    public const SHAPE_CIRCLE = 'circle';
    public const SHAPE_POLYGON = 'polygon';
    public const TYPE_CIRCLE = 'circle';
    public const TYPE_POLYGON = 'polygon';

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'shape_type',
        'fence_type',
        'center_latitude',
        'center_longitude',
        'radius_meters',
        'polygon_coordinates',
        'alert_on_entry',
        'alert_on_exit',
        'trigger_on_entry',
        'trigger_on_exit',
        'is_active',
    ];

    protected $casts = [
        'center_latitude' => 'decimal:7',
        'center_longitude' => 'decimal:7',
        'radius_meters' => 'integer',
        'polygon_coordinates' => 'array',
        'alert_on_entry' => 'boolean',
        'alert_on_exit' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(GeofenceEvent::class);
    }

    public function setFenceTypeAttribute($value): void
    {
        $this->attributes['shape_type'] = $value;
    }

    public function getFenceTypeAttribute(): ?string
    {
        return $this->attributes['shape_type'] ?? null;
    }

    public function setTriggerOnEntryAttribute($value): void
    {
        $this->attributes['alert_on_entry'] = $value;
    }

    public function getTriggerOnEntryAttribute(): ?bool
    {
        return (bool)($this->attributes['alert_on_entry'] ?? false);
    }

    public function setTriggerOnExitAttribute($value): void
    {
        $this->attributes['alert_on_exit'] = $value;
    }

    public function getTriggerOnExitAttribute(): ?bool
    {
        return (bool)($this->attributes['alert_on_exit'] ?? false);
    }

    /**
     * Check if a given latitude and longitude is inside this circular geofence.
     */
    public function containsPoint(float $lat, float $lng): bool
    {
        if ($this->shape_type === self::SHAPE_CIRCLE && $this->center_latitude && $this->center_longitude) {
            $earthRadius = 6371000; // in meters
            $latFrom = deg2rad((float)$this->center_latitude);
            $lonFrom = deg2rad((float)$this->center_longitude);
            $latTo = deg2rad($lat);
            $lonTo = deg2rad($lng);

            $latDelta = $latTo - $latFrom;
            $lonDelta = $lonTo - $lonFrom;

            $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
                cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));

            $distanceMeters = $angle * $earthRadius;
            return $distanceMeters <= $this->radius_meters;
        }

        return false;
    }
}

<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripRouteDeviation extends Model
{
    use HasFactory, BelongsToCompany;

    protected $table = 'trip_route_deviations';

    public const SEVERITY_LOW = 'low';
    public const SEVERITY_MEDIUM = 'medium';
    public const SEVERITY_HIGH = 'high';
    public const SEVERITY_CRITICAL = 'critical';

    protected $fillable = [
        'company_id',
        'trip_id',
        'vehicle_id',
        'deviation_point_lat',
        'deviation_point_lng',
        'deviation_distance_meters',
        'deviation_started_at',
        'deviation_ended_at',
        'severity',
        'notes',
    ];

    protected $casts = [
        'deviation_point_lat' => 'decimal:7',
        'deviation_point_lng' => 'decimal:7',
        'deviation_distance_meters' => 'integer',
        'deviation_started_at' => 'datetime',
        'deviation_ended_at' => 'datetime',
    ];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}


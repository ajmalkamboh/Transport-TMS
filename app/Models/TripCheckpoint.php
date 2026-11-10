<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripCheckpoint extends Model
{
    use HasFactory, BelongsToCompany;

    protected $table = 'trip_checkpoints';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PASSED = 'passed';
    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'company_id',
        'trip_id',
        'checkpoint_name',
        'stop_order',
        'planned_arrival',
        'planned_departure',
        'actual_arrival',
        'actual_departure',
        'delay_minutes',
        'delay_reason',
        'odometer_km',
        'notes',
        'status',
    ];

    protected $casts = [
        'stop_order' => 'integer',
        'planned_arrival' => 'datetime',
        'planned_departure' => 'datetime',
        'actual_arrival' => 'datetime',
        'actual_departure' => 'datetime',
        'delay_minutes' => 'integer',
        'odometer_km' => 'integer',
    ];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function markPassed(?int $odometer = null, int $delay = 0, ?string $delayReason = null): void
    {
        $this->status = self::STATUS_PASSED;
        $this->actual_arrival = now();
        $this->actual_departure = now();
        $this->odometer_km = $odometer;
        $this->delay_minutes = $delay;
        $this->delay_reason = $delayReason;
        $this->save();
    }
}

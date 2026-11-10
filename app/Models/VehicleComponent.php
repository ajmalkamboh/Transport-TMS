<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VehicleComponent extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $table = 'vehicle_components';

    public const TYPE_TYRE = 'tyre';
    public const TYPE_BATTERY = 'battery';
    public const TYPE_ENGINE_OIL = 'engine_oil';
    public const TYPE_FILTER = 'filter';
    public const TYPE_BRAKE_PAD = 'brake_pad';
    public const TYPE_CLUTCH = 'clutch';
    public const TYPE_OTHER = 'other';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_WORN = 'worn';
    public const STATUS_REPLACED = 'replaced';
    public const STATUS_SCRAPPED = 'scrapped';

    protected $fillable = [
        'company_id',
        'vehicle_id',
        'job_card_id',
        'component_type',
        'brand_name',
        'model_or_size',
        'serial_number',
        'position',
        'installed_date',
        'installed_odometer_km',
        'expected_life_km',
        'expected_life_days',
        'removed_date',
        'removed_odometer_km',
        'status',
        'notes',
    ];

    protected $casts = [
        'installed_date' => 'date',
        'installed_odometer_km' => 'integer',
        'expected_life_km' => 'integer',
        'expected_life_days' => 'integer',
        'removed_date' => 'date',
        'removed_odometer_km' => 'integer',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function jobCard(): BelongsTo
    {
        return $this->belongsTo(JobCard::class);
    }

    public function getUsageKm(int $currentOdometer): int
    {
        if ($this->removed_odometer_km) {
            return max(0, $this->removed_odometer_km - $this->installed_odometer_km);
        }

        return max(0, $currentOdometer - $this->installed_odometer_km);
    }

    public function isLifeExceeded(int $currentOdometer): bool
    {
        if ($this->expected_life_km && $this->getUsageKm($currentOdometer) >= $this->expected_life_km) {
            return true;
        }

        if ($this->expected_life_days && now()->diffInDays($this->installed_date) >= $this->expected_life_days) {
            return true;
        }

        return false;
    }
}

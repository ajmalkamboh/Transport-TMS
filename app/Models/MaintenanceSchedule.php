<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceSchedule extends Model
{
    use HasFactory, BelongsToCompany;

    protected $table = 'maintenance_schedules';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_DUE = 'due';
    public const STATUS_OVERDUE = 'overdue';
    public const STATUS_INACTIVE = 'inactive';

    public const CAT_OIL_CHANGE = 'oil_change';
    public const CAT_SERVICE_INTERVAL = 'service_interval';
    public const CAT_BRAKE_INSPECTION = 'brake_inspection';
    public const CAT_TYRE_ROTATION = 'tyre_rotation';
    public const CAT_BATTERY_CHECK = 'battery_check';
    public const CAT_GENERAL_OVERHAUL = 'general_overhaul';
    public const CAT_FILTER_REPLACEMENT = 'filter_replacement';

    protected $fillable = [
        'company_id',
        'vehicle_id',
        'title',
        'maintenance_category',
        'interval_km',
        'interval_days',
        'last_performed_at',
        'last_performed_odometer_km',
        'next_due_date',
        'next_due_odometer_km',
        'alert_lead_km',
        'alert_lead_days',
        'status',
    ];

    protected $casts = [
        'interval_km' => 'integer',
        'interval_days' => 'integer',
        'last_performed_at' => 'date',
        'last_performed_odometer_km' => 'integer',
        'next_due_date' => 'date',
        'next_due_odometer_km' => 'integer',
        'alert_lead_km' => 'integer',
        'alert_lead_days' => 'integer',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function jobCards(): HasMany
    {
        return $this->hasMany(JobCard::class);
    }

    /**
     * Determine if maintenance is due based on vehicle current odometer and current date.
     */
    public function evaluateStatus(int $currentOdometer): string
    {
        if ($this->status === self::STATUS_INACTIVE) {
            return self::STATUS_INACTIVE;
        }

        $dueByOdo = $this->next_due_odometer_km && $currentOdometer >= $this->next_due_odometer_km;
        $dueByDate = $this->next_due_date && $this->next_due_date->isPast();

        if ($dueByOdo || $dueByDate) {
            return self::STATUS_OVERDUE;
        }

        $leadByOdo = $this->next_due_odometer_km && ($currentOdometer + $this->alert_lead_km) >= $this->next_due_odometer_km;
        $leadByDate = $this->next_due_date && now()->diffInDays($this->next_due_date, false) <= $this->alert_lead_days;

        if ($leadByOdo || $leadByDate) {
            return self::STATUS_DUE;
        }

        return self::STATUS_ACTIVE;
    }
}

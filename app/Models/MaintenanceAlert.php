<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceAlert extends Model
{
    use HasFactory, BelongsToCompany;

    protected $table = 'maintenance_alerts';

    public const TYPE_OIL_CHANGE_DUE = 'oil_change_due';
    public const TYPE_SERVICE_DUE = 'service_due';
    public const TYPE_INSURANCE_EXPIRY = 'insurance_expiry';
    public const TYPE_REGISTRATION_EXPIRY = 'registration_expiry';
    public const TYPE_FITNESS_EXPIRY = 'fitness_expiry';
    public const TYPE_SUSPICIOUS_MILEAGE = 'suspicious_mileage';
    public const TYPE_FUEL_ANOMALY = 'fuel_anomaly';
    public const TYPE_COMPONENT_LIFE = 'component_life_warning';

    public const SEVERITY_INFO = 'info';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_CRITICAL = 'critical';

    protected $fillable = [
        'company_id',
        'vehicle_id',
        'alert_type',
        'title',
        'description',
        'severity',
        'due_date',
        'due_odometer_km',
        'is_resolved',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'due_date' => 'date',
        'due_odometer_km' => 'integer',
        'is_resolved' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function markResolved(?int $userId = null): void
    {
        $this->is_resolved = true;
        $this->resolved_at = now();
        $this->resolved_by = $userId ?? auth()->id();
        $this->save();
    }
}

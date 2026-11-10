<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobCard extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $table = 'job_cards';

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const WORKSHOP_IN_HOUSE = 'in_house';
    public const WORKSHOP_THIRD_PARTY = 'third_party';

    public const SERVICE_PREVENTIVE = 'preventive';
    public const SERVICE_BREAKDOWN = 'breakdown';
    public const SERVICE_SCHEDULED = 'scheduled';
    public const SERVICE_REPAIR = 'repair';
    public const SERVICE_TYRE = 'tyre_service';
    public const SERVICE_OIL_LUBE = 'oil_lube';
    public const SERVICE_BODY_WORK = 'body_work';

    protected $fillable = [
        'company_id',
        'branch_id',
        'job_card_number',
        'vehicle_id',
        'driver_id',
        'maintenance_schedule_id',
        'workshop_type',
        'workshop_name',
        'service_type',
        'priority',
        'odometer_km',
        'scheduled_date',
        'started_at',
        'completed_at',
        'downtime_hours',
        'labour_charges',
        'parts_total_cost',
        'tax_amount',
        'total_cost',
        'payment_status',
        'status',
        'issue_description',
        'work_performed',
        'supervisor_notes',
        'technician_name',
        'created_by',
        'completed_by',
    ];

    protected $casts = [
        'odometer_km' => 'integer',
        'scheduled_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'downtime_hours' => 'decimal:2',
        'labour_charges' => 'decimal:2',
        'parts_total_cost' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(MaintenanceSchedule::class, 'maintenance_schedule_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(JobCardItem::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(VehicleComponent::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function recalculateTotals(): void
    {
        $this->parts_total_cost = $this->items()->sum('total_cost');
        $this->total_cost = (float) $this->parts_total_cost + (float) $this->labour_charges + (float) $this->tax_amount;
        $this->save();
    }
}


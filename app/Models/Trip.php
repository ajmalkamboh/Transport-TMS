<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Trip extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $table = 'trips';

    public const STATUS_PLANNED = 'planned';
    public const STATUS_LOADING = 'loading';
    public const STATUS_DISPATCHED = 'dispatched';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_OPERATIONALLY_COMPLETED = 'operationally_completed';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_CANCELLED = 'cancelled';

    public const FINANCIAL_PENDING = 'pending';
    public const FINANCIAL_PARTIALLY_SETTLED = 'partially_settled';
    public const FINANCIAL_SETTLED = 'settled';
    public const FINANCIAL_CLOSED = 'closed';

    protected $fillable = [
        'company_id',
        'branch_id',
        'trip_number',
        'trip_date',
        'origin_city',
        'destination_city',
        'planned_route',
        'estimated_departure',
        'estimated_arrival',
        'actual_departure',
        'actual_arrival',
        'departure_odometer_km',
        'arrival_odometer_km',
        'total_distance_km',
        'total_consignments_count',
        'total_packages_count',
        'total_weight_tons',
        'trip_status',
        'financial_status',
        'loading_started_at',
        'loading_completed_at',
        'loading_supervisor',
        'assigned_dispatcher_id',
        'dispatched_by',
        'operationally_completed_at',
        'financially_closed_at',
        'trip_notes',
    ];

    protected $casts = [
        'trip_date' => 'date',
        'estimated_departure' => 'datetime',
        'estimated_arrival' => 'datetime',
        'actual_departure' => 'datetime',
        'actual_arrival' => 'datetime',
        'departure_odometer_km' => 'integer',
        'arrival_odometer_km' => 'integer',
        'total_distance_km' => 'integer',
        'total_consignments_count' => 'integer',
        'total_packages_count' => 'integer',
        'total_weight_tons' => 'decimal:2',
        'loading_started_at' => 'datetime',
        'loading_completed_at' => 'datetime',
        'operationally_completed_at' => 'datetime',
        'financially_closed_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function dispatcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_dispatcher_id');
    }

    public function dispatchedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function tripConsignments(): HasMany
    {
        return $this->hasMany(TripConsignment::class);
    }

    public function consignments(): BelongsToMany
    {
        return $this->belongsToMany(Consignment::class, 'trip_consignments')
            ->withPivot([
                'id',
                'assigned_quantity',
                'weight_kg',
                'loading_status',
                'delivered_quantity',
                'damaged_quantity',
                'short_quantity',
                'delivery_status',
                'delivery_time',
                'receiver_name',
                'receiver_phone',
                'delivery_notes',
            ])
            ->withTimestamps();
    }

    public function resources(): HasMany
    {
        return $this->hasMany(TripResource::class);
    }

    public function activeResources(): HasMany
    {
        return $this->hasMany(TripResource::class)->where('is_active', true);
    }

    public function checkpoints(): HasMany
    {
        return $this->hasMany(TripCheckpoint::class)->orderBy('stop_order');
    }

    public function proofOfDeliveries(): HasMany
    {
        return $this->hasMany(ProofOfDelivery::class);
    }

    public function fuelEntries(): HasMany
    {
        return $this->hasMany(FuelEntry::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(TripExpense::class);
    }

    public function ownerSettlements(): HasMany
    {
        return $this->hasMany(OwnerSettlement::class);
    }

    public function latestOwnerSettlement(): ?OwnerSettlement
    {
        return $this->ownerSettlements()->latest('id')->first();
    }

    public function primaryVehicle(): ?Vehicle
    {
        return $this->resources()
            ->where('resource_type', 'vehicle')
            ->where('role', 'primary_vehicle')
            ->where('is_active', true)
            ->first()
            ?->vehicle;
    }

    public function primaryDriver(): ?Driver
    {
        return $this->resources()
            ->where('resource_type', 'driver')
            ->where('role', 'primary_driver')
            ->where('is_active', true)
            ->first()
            ?->driver;
    }

    public function isOperationallyCompleted(): bool
    {
        return in_array($this->trip_status, [
            self::STATUS_OPERATIONALLY_COMPLETED,
            self::STATUS_CLOSED,
        ]);
    }

    public function isFinanciallyClosed(): bool
    {
        return $this->financial_status === self::FINANCIAL_CLOSED;
    }

    /**
     * Refresh cumulative packages and weight loaded across assigned consignments.
     */
    public function refreshCumulativeTotals(): void
    {
        $allocations = $this->tripConsignments()->get();

        $this->total_consignments_count = $allocations->count();
        $this->total_packages_count = $allocations->sum('assigned_quantity');
        $this->total_weight_tons = round($allocations->sum('weight_kg') / 1000, 2);
        $this->save();
    }
}

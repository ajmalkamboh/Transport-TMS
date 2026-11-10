<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TripConsignment extends Model
{
    use HasFactory, BelongsToCompany;

    protected $table = 'trip_consignments';

    public const LOADING_PLANNED = 'planned';
    public const LOADING_LOADED = 'loaded';
    public const LOADING_DISPATCHED = 'dispatched';
    public const LOADING_DELIVERED = 'delivered';
    public const LOADING_REJECTED = 'rejected';

    public const DELIVERY_PENDING = 'pending';
    public const DELIVERY_FULL = 'delivered_full';
    public const DELIVERY_PARTIAL = 'delivered_partial';
    public const DELIVERY_FAILED = 'failed';
    public const DELIVERY_REJECTED = 'rejected';

    protected $fillable = [
        'company_id',
        'trip_id',
        'consignment_id',
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
    ];

    protected $casts = [
        'assigned_quantity' => 'integer',
        'weight_kg' => 'decimal:2',
        'delivered_quantity' => 'integer',
        'damaged_quantity' => 'integer',
        'short_quantity' => 'integer',
        'delivery_time' => 'datetime',
    ];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function consignment(): BelongsTo
    {
        return $this->belongsTo(Consignment::class);
    }

    public function proofOfDeliveries(): HasMany
    {
        return $this->hasMany(ProofOfDelivery::class);
    }

    /**
     * Record physical delivery execution for this trip allocation.
     */
    public function recordDelivery(
        int $deliveredQty,
        int $damagedQty = 0,
        int $shortQty = 0,
        ?string $receiverName = null,
        ?string $receiverPhone = null,
        ?string $notes = null
    ): void {
        $this->delivered_quantity = $deliveredQty;
        $this->damaged_quantity = $damagedQty;
        $this->short_quantity = $shortQty;
        $this->delivery_time = now();
        $this->receiver_name = $receiverName;
        $this->receiver_phone = $receiverPhone;
        $this->delivery_notes = $notes;

        if ($deliveredQty >= $this->assigned_quantity && $damagedQty === 0 && $shortQty === 0) {
            $this->delivery_status = self::DELIVERY_FULL;
            $this->loading_status = self::LOADING_DELIVERED;
        } elseif ($deliveredQty > 0) {
            $this->delivery_status = self::DELIVERY_PARTIAL;
            $this->loading_status = self::LOADING_DELIVERED;
        } else {
            $this->delivery_status = self::DELIVERY_FAILED;
        }

        $this->save();

        // Update master consignment delivered units
        if ($this->consignment && $deliveredQty > 0) {
            $this->consignment->recordDelivery($deliveredQty);
        }
    }
}


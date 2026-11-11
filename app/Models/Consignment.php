<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Consignment extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $table = 'consignments';

    public const STATUS_BOOKED = 'booked';
    public const STATUS_AT_ORIGIN_HUB = 'at_origin_hub';
    public const STATUS_DISPATCHED_IN_TRANSIT = 'dispatched_in_transit';
    public const STATUS_DISPATCHED = 'dispatched_in_transit';
    public const STATUS_IN_TRANSIT = 'dispatched_in_transit';
    public const STATUS_AT_DESTINATION_HUB = 'at_destination_hub';
    public const STATUS_OUT_FOR_DELIVERY = 'out_for_delivery';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_CANCELLED = 'cancelled';

    public const PACKAGING_BAGS = 'bags';
    public const PACKAGING_CARTONS = 'cartons';
    public const PACKAGING_PALLETS = 'pallets';
    public const PACKAGING_DRUMS = 'drums';
    public const PACKAGING_LOOSE = 'loose';
    public const PACKAGING_WOODEN_CRATES = 'wooden_crates';
    public const PACKAGING_CONTAINERS = 'containers';

    protected $fillable = [
        'company_id',
        'branch_id',
        'booking_id',
        'customer_id',
        'created_by',
        'consignment_number',
        'consignment_date',
        'consignee_name',
        'consignee_phone',
        'consignee_cnic_or_ntn',
        'consignee_address',
        'consignee_city',
        'pickup_point',
        'delivery_point',
        'cargo_description',
        'cargo_category',
        'packaging_type',
        'declared_value',
        'gross_weight_kg',
        'chargeable_weight_kg',
        'volume_cbm',
        'total_quantity',
        'dispatched_quantity',
        'delivered_quantity',
        'remaining_quantity',
        'freight_rate',
        'freight_charge',
        'handling_charge',
        'insurance_charge',
        'total_charges',
        'payment_mode',
        'delivery_instructions',
        'status',
    ];

    protected $casts = [
        'consignment_date' => 'date',
        'declared_value' => 'decimal:2',
        'gross_weight_kg' => 'decimal:2',
        'chargeable_weight_kg' => 'decimal:2',
        'volume_cbm' => 'decimal:2',
        'total_quantity' => 'integer',
        'dispatched_quantity' => 'integer',
        'delivered_quantity' => 'integer',
        'remaining_quantity' => 'integer',
        'freight_rate' => 'decimal:2',
        'freight_charge' => 'decimal:2',
        'handling_charge' => 'decimal:2',
        'insurance_charge' => 'decimal:2',
        'total_charges' => 'decimal:2',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ConsignmentItem::class);
    }

    public function tripConsignments(): HasMany
    {
        return $this->hasMany(TripConsignment::class);
    }

    public function trips(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Trip::class, 'trip_consignments')
            ->withPivot(['id', 'assigned_quantity', 'loading_status', 'delivered_quantity', 'delivery_status'])
            ->withTimestamps();
    }

    /**
     * Check if a given dispatch quantity is permitted.
     * Rule: Dispatch quantity must not exceed remaining undelivered quantity.
     */
    public function canDispatch(int $quantity): bool
    {
        if ($quantity <= 0) {
            return false;
        }

        return $quantity <= $this->remaining_quantity;
    }

    /**
     * Record dispatch quantity and transition status.
     *
     * @throws \InvalidArgumentException
     */
    public function recordDispatch(int $quantity): void
    {
        if (!$this->canDispatch($quantity)) {
            throw new \InvalidArgumentException(
                sprintf('Dispatch quantity [%d] exceeds remaining quantity [%d] for Consignment [%s].', $quantity, $this->remaining_quantity, $this->consignment_number)
            );
        }

        $this->dispatched_quantity += $quantity;
        $this->remaining_quantity = max(0, $this->total_quantity - $this->dispatched_quantity);

        if ($this->remaining_quantity === 0) {
            $this->status = self::STATUS_DISPATCHED_IN_TRANSIT;
        } else {
            $this->status = self::STATUS_DISPATCHED_IN_TRANSIT;
        }

        $this->save();

        // Update parent booking status if needed
        if ($this->booking) {
            $this->booking->status = Booking::STATUS_PARTIALLY_DISPATCHED;
            $this->booking->save();
        }
    }

    /**
     * Record delivery completion.
     */
    public function recordDelivery(int $quantity): void
    {
        $this->delivered_quantity += $quantity;

        if ($this->delivered_quantity >= $this->total_quantity) {
            $this->status = self::STATUS_DELIVERED;
        }

        $this->save();
    }
}


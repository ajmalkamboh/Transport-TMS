<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $table = 'bookings';

    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_PARTIALLY_DISPATCHED = 'partially_dispatched';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const TERMS_TO_PAY = 'to_pay';
    public const TERMS_PAID = 'paid';
    public const TERMS_CREDIT = 'credit';
    public const TERMS_BILL_TO_COMPANY = 'bill_to_company';

    protected $fillable = [
        'company_id',
        'branch_id',
        'customer_id',
        'quotation_id',
        'customer_contract_id',
        'created_by',
        'booking_number',
        'booking_date',
        'origin_city',
        'destination_city',
        'origin_address',
        'destination_address',
        'expected_dispatch_date',
        'expected_delivery_date',
        'payment_terms',
        'total_freight',
        'additional_charges',
        'discount_amount',
        'tax_amount',
        'net_amount',
        'advance_received',
        'advance_payment_method',
        'advance_receipt_reference',
        'advance_recorded_at',
        'balance_amount',
        'total_consignments_count',
        'total_packages_count',
        'total_weight_tons',
        'special_instructions',
        'status',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'expected_dispatch_date' => 'date',
        'expected_delivery_date' => 'date',
        'total_freight' => 'decimal:2',
        'additional_charges' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'advance_received' => 'decimal:2',
        'balance_amount' => 'decimal:2',
        'total_weight_tons' => 'decimal:2',
        'total_consignments_count' => 'integer',
        'total_packages_count' => 'integer',
        'advance_recorded_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(CustomerContract::class, 'customer_contract_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function consignments(): HasMany
    {
        return $this->hasMany(Consignment::class);
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Check if booking can be safely cancelled.
     * Rule: Cannot cancel if any consignments have already been dispatched or delivered.
     */
    public function canBeCancelled(): bool
    {
        if ($this->isCancelled()) {
            return false;
        }

        $activeDispatches = $this->consignments()
            ->whereIn('status', [
                Consignment::STATUS_DISPATCHED_IN_TRANSIT,
                Consignment::STATUS_AT_DESTINATION_HUB,
                Consignment::STATUS_OUT_FOR_DELIVERY,
                Consignment::STATUS_DELIVERED,
            ])
            ->exists();

        return !$activeDispatches;
    }

    /**
     * Refresh cumulative statistics from linked consignments.
     */
    public function refreshStatistics(): void
    {
        $consignments = $this->consignments()->get();

        $this->total_consignments_count = $consignments->count();
        $this->total_packages_count = $consignments->sum('total_quantity');
        $this->total_weight_tons = round($consignments->sum('chargeable_weight_kg') / 1000, 2);
        $this->save();
    }
}


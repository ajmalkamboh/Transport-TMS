<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $table = 'quotations';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CONVERTED = 'converted';

    protected $fillable = [
        'company_id',
        'branch_id',
        'customer_id',
        'customer_contract_id',
        'created_by',
        'approved_by',
        'converted_booking_id',
        'quotation_number',
        'quotation_date',
        'valid_until',
        'origin_city',
        'destination_city',
        'origin_address',
        'destination_address',
        'cargo_description',
        'cargo_category',
        'weight_tons',
        'volume_cbm',
        'package_count',
        'required_vehicle_category',
        'required_vehicle_type',
        'rate_type',
        'base_rate',
        'distance_km',
        'freight_amount',
        'loading_charges',
        'unloading_charges',
        'toll_charges',
        'detention_charges',
        'other_charges',
        'discount_amount',
        'tax_percentage',
        'tax_amount',
        'net_total_amount',
        'status',
        'approved_at',
        'terms_and_conditions',
        'notes',
    ];

    protected $casts = [
        'quotation_date' => 'date',
        'valid_until' => 'date',
        'weight_tons' => 'decimal:2',
        'volume_cbm' => 'decimal:2',
        'package_count' => 'integer',
        'base_rate' => 'decimal:2',
        'distance_km' => 'decimal:2',
        'freight_amount' => 'decimal:2',
        'loading_charges' => 'decimal:2',
        'unloading_charges' => 'decimal:2',
        'toll_charges' => 'decimal:2',
        'detention_charges' => 'decimal:2',
        'other_charges' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_percentage' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'net_total_amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function convertedBooking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'converted_booking_id');
    }

    public function isExpired(): bool
    {
        return $this->valid_until && $this->valid_until->isPast() && $this->status !== self::STATUS_CONVERTED;
    }

    public function isConvertible(): bool
    {
        return ($this->status === self::STATUS_APPROVED || $this->status === self::STATUS_SENT) && !$this->converted_booking_id;
    }
}


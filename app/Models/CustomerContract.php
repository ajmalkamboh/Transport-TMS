<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerContract extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $table = 'customer_contracts';

    public const RATE_FIXED = 'fixed';
    public const RATE_PER_TON = 'per_ton';
    public const RATE_PER_KM = 'per_km';
    public const RATE_PER_VEHICLE = 'per_vehicle';
    public const RATE_PER_QUANTITY = 'per_quantity';
    public const RATE_ROUTE_BASED = 'route_based';
    public const RATE_NEGOTIATED = 'negotiated';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_TERMINATED = 'terminated';

    protected $fillable = [
        'company_id',
        'customer_id',
        'contract_number',
        'contract_title',
        'rate_type',
        'base_rate',
        'payment_terms',
        'credit_days',
        'credit_limit',
        'discount_percentage',
        'tax_percentage',
        'loading_charges',
        'unloading_charges',
        'detention_rate_per_day',
        'demurrage_rate_per_day',
        'route_rates',
        'cargo_rates',
        'start_date',
        'end_date',
        'status',
        'terms_and_conditions',
        'notes',
    ];

    protected $casts = [
        'base_rate' => 'decimal:2',
        'credit_days' => 'integer',
        'credit_limit' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'tax_percentage' => 'decimal:2',
        'loading_charges' => 'decimal:2',
        'unloading_charges' => 'decimal:2',
        'detention_rate_per_day' => 'decimal:2',
        'demurrage_rate_per_day' => 'decimal:2',
        'route_rates' => 'array',
        'cargo_rates' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function isActive(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        $now = now()->startOfDay();
        if ($this->start_date && $this->start_date->gt($now)) {
            return false;
        }

        if ($this->end_date && $this->end_date->lt($now)) {
            return false;
        }

        return true;
    }

    /**
     * Resolve agreed route rate for a given corridor, or fallback to base_rate.
     */
    public function getRateForRoute(string $origin, string $destination): float
    {
        if (is_array($this->route_rates)) {
            foreach ($this->route_rates as $item) {
                if (
                    strcasecmp($item['origin'] ?? '', $origin) === 0 &&
                    strcasecmp($item['destination'] ?? '', $destination) === 0
                ) {
                    return (float) ($item['rate'] ?? 0.00);
                }
            }
        }

        return (float) $this->base_rate;
    }
}


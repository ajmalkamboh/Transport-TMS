<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $table = 'customers';

    public const TYPE_CORPORATE = 'corporate';
    public const TYPE_INDIVIDUAL = 'individual';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_BLACKLISTED = 'blacklisted';

    public const TERMS_TO_PAY = 'to_pay';
    public const TERMS_PAID = 'paid';
    public const TERMS_CREDIT_15 = 'credit_15_days';
    public const TERMS_CREDIT_30 = 'credit_30_days';
    public const TERMS_CREDIT_60 = 'credit_60_days';
    public const TERMS_BILL_TO_CUSTOMER = 'bill_to_customer';

    protected $fillable = [
        'company_id',
        'branch_id',
        'customer_code',
        'customer_type',
        'name',
        'company_name',
        'contact_person',
        'designation',
        'phone',
        'mobile',
        'email',
        'tax_ntn',
        'tax_strn',
        'is_tax_exempt',
        'billing_address',
        'billing_city',
        'billing_state',
        'billing_postal_code',
        'credit_limit',
        'credit_days',
        'payment_terms',
        'opening_balance',
        'current_balance',
        'status',
        'notes',
    ];

    protected $casts = [
        'is_tax_exempt' => 'boolean',
        'credit_limit' => 'decimal:2',
        'credit_days' => 'integer',
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function deliveryAddresses(): HasMany
    {
        return $this->hasMany(CustomerDeliveryAddress::class);
    }

    public function defaultDeliveryAddress(): HasOne
    {
        return $this->hasOne(CustomerDeliveryAddress::class)->where('is_default', true);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(CustomerContract::class);
    }

    public function activeContract(): HasOne
    {
        return $this->hasOne(CustomerContract::class)
            ->where('status', 'active')
            ->where('start_date', '<=', now()->toDateString())
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now()->toDateString());
            })
            ->latest();
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function consignments(): HasMany
    {
        return $this->hasMany(Consignment::class);
    }

    public function isCorporate(): bool
    {
        return $this->customer_type === self::TYPE_CORPORATE;
    }

    public function isIndividual(): bool
    {
        return $this->customer_type === self::TYPE_INDIVIDUAL;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isBlacklisted(): bool
    {
        return $this->status === self::STATUS_BLACKLISTED;
    }

    /**
     * Determine whether the customer has available credit capacity.
     */
    public function hasAvailableCredit(float $additionalAmount = 0.00): bool
    {
        if ((float) $this->credit_limit <= 0) {
            return true; // No limit configured / cash customer
        }

        return ((float) $this->current_balance + $additionalAmount) <= (float) $this->credit_limit;
    }
}


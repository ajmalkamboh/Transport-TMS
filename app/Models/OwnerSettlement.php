<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OwnerSettlement extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $table = 'owner_settlements';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_POSTED = 'posted';
    public const STATUS_CONFIRMED = 'posted';
    public const STATUS_PAID = 'paid';
    public const STATUS_PARTIALLY_PAID = 'partially_paid';
    public const STATUS_REVERSED = 'reversed';
    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_UNPAID = 'unpaid';
    public const PAYMENT_PARTIALLY_PAID = 'partially_paid';
    public const PAYMENT_PAID = 'paid';

    public const MODEL_FIXED_PER_TRIP = 'fixed_per_trip';
    public const MODEL_PERCENTAGE_OF_FREIGHT = 'percentage_of_freight';
    public const MODEL_PER_KM = 'per_km';
    public const MODEL_MONTHLY_RENTAL = 'monthly_rental';
    public const MODEL_ROUTE_SPECIFIC = 'route_specific';
    public const MODEL_NEGOTIATED = 'negotiated';

    protected $fillable = [
        'company_id',
        'branch_id',
        'vehicle_owner_id',
        'vehicle_id',
        'trip_id',
        'owner_contract_id',
        'settlement_number',
        'settlement_date',
        'payment_model',
        'customer_freight_total',
        'agreed_owner_payable',
        'fuel_deduction',
        'repair_deduction',
        'advance_deduction',
        'advances',
        'penalty_deduction',
        'other_deductions',
        'total_deductions',
        'approved_deductions',
        'additional_charges',
        'net_payable',
        'paid_amount',
        'balance_due',
        'contract_snapshot',
        'calculation_details',
        'status',
        'payment_status',
        'prepared_by',
        'approved_by',
        'approved_at',
        'posted_by',
        'posted_at',
        'reversed_by',
        'reversed_at',
        'reversal_reason',
        'notes',
    ];

    protected $casts = [
        'settlement_date' => 'date',
        'customer_freight_total' => 'decimal:2',
        'agreed_owner_payable' => 'decimal:2',
        'fuel_deduction' => 'decimal:2',
        'repair_deduction' => 'decimal:2',
        'advance_deduction' => 'decimal:2',
        'penalty_deduction' => 'decimal:2',
        'other_deductions' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'additional_charges' => 'decimal:2',
        'net_payable' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance_due' => 'decimal:2',
        'contract_snapshot' => 'array',
        'calculation_details' => 'array',
        'approved_at' => 'datetime',
        'posted_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(VehicleOwner::class, 'vehicle_owner_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class, 'trip_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(OwnerContract::class, 'owner_contract_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function deductionItems(): HasMany
    {
        return $this->hasMany(OwnerSettlementDeduction::class, 'owner_settlement_id');
    }

    public function deductions(): HasMany
    {
        return $this->deductionItems();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(OwnerPayment::class, 'owner_settlement_id');
    }

    public function preparedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function postedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function reversedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function canSubmit(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canApprove(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SUBMITTED]);
    }

    public function canPost(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function canReverse(): bool
    {
        return in_array($this->status, [self::STATUS_POSTED, self::STATUS_PARTIALLY_PAID, self::STATUS_PAID]);
    }

    public function isPosted(): bool
    {
        return in_array($this->status, [self::STATUS_POSTED, self::STATUS_PARTIALLY_PAID, self::STATUS_PAID]);
    }

    public function isReversed(): bool
    {
        return $this->status === self::STATUS_REVERSED;
    }

    /**
     * Recalculate deductions, net payable, and balance due.
     */
    public function recalculateTotals(): void
    {
        $this->total_deductions = round(
            $this->fuel_deduction +
            $this->repair_deduction +
            $this->advance_deduction +
            $this->penalty_deduction +
            $this->other_deductions,
            2
        );

        $this->net_payable = round(
            $this->agreed_owner_payable - $this->total_deductions + $this->additional_charges,
            2
        );

        $this->balance_due = round(
            max(0, $this->net_payable - $this->paid_amount),
            2
        );

        if ($this->paid_amount >= $this->net_payable && $this->net_payable > 0) {
            $this->payment_status = self::PAYMENT_PAID;
            if (in_array($this->status, [self::STATUS_POSTED, self::STATUS_PARTIALLY_PAID])) {
                $this->status = self::STATUS_PAID;
            }
        } elseif ($this->paid_amount > 0) {
            $this->payment_status = self::PAYMENT_PARTIALLY_PAID;
            if ($this->status === self::STATUS_POSTED) {
                $this->status = self::STATUS_PARTIALLY_PAID;
            }
        } else {
            $this->payment_status = self::PAYMENT_UNPAID;
        }

        $this->save();
    }

    public function getApprovedDeductionsAttribute(): float
    {
        return (float) ($this->attributes['total_deductions'] ?? 0);
    }

    public function setApprovedDeductionsAttribute($value): void
    {
        $this->attributes['total_deductions'] = $value;
    }

    public function getAdvancesAttribute(): float
    {
        return (float) ($this->attributes['advance_deduction'] ?? 0);
    }

    public function setAdvancesAttribute($value): void
    {
        $this->attributes['advance_deduction'] = $value;
    }
}


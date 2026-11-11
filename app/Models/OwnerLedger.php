<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OwnerLedger extends Model
{
    use HasFactory, BelongsToCompany;

    protected $table = 'owner_ledgers';

    public const TYPE_OPENING_BALANCE = 'opening_balance';
    public const TYPE_TRIP_PAYABLE = 'trip_payable';
    public const TYPE_DEDUCTION = 'deduction';
    public const TYPE_ADDITIONAL_CHARGE = 'additional_charge';
    public const TYPE_ADVANCE = 'advance';
    public const TYPE_PAYMENT = 'payment';
    public const TYPE_ADJUSTMENT_CREDIT = 'adjustment_credit';
    public const TYPE_ADJUSTMENT_DEBIT = 'adjustment_debit';
    public const TYPE_REVERSAL = 'reversal';

    public const ENTRY_CREDIT = 'credit'; // Increases amount payable to owner
    public const ENTRY_DEBIT = 'debit';   // Decreases amount payable to owner

    protected $fillable = [
        'company_id',
        'branch_id',
        'vehicle_owner_id',
        'trip_id',
        'transaction_date',
        'transaction_type',
        'entry_type',
        'amount',
        'running_balance',
        'reference_type',
        'reference_id',
        'reference_number',
        'description',
        'created_by',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
        'running_balance' => 'decimal:2',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(VehicleOwner::class, 'vehicle_owner_id');
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class, 'trip_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCredit(): bool
    {
        return $this->entry_type === self::ENTRY_CREDIT;
    }

    public function isDebit(): bool
    {
        return $this->entry_type === self::ENTRY_DEBIT;
    }
}


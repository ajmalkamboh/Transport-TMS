<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OwnerPayment extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $table = 'owner_payments';

    public const METHOD_CASH = 'cash';
    public const METHOD_BANK_TRANSFER = 'bank_transfer';
    public const METHOD_CHEQUE = 'cheque';
    public const METHOD_ONLINE = 'online';
    public const METHOD_OTHER = 'other';

    public const STATUS_COMPLETED = 'completed';
    public const STATUS_PENDING = 'pending';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_BOUNCED = 'bounced';

    protected $fillable = [
        'company_id',
        'branch_id',
        'vehicle_owner_id',
        'owner_settlement_id',
        'payment_number',
        'payment_date',
        'amount',
        'payment_method',
        'payment_reference',
        'cheque_number',
        'cheque_date',
        'bank_name',
        'bank_account_number',
        'status',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'cheque_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(VehicleOwner::class, 'vehicle_owner_id');
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(OwnerSettlement::class, 'owner_settlement_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}


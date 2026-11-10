<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TripExpense extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $table = 'trip_expenses';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_POSTED = 'posted';

    public const CAT_TOLL_TAX = 'toll_tax';
    public const CAT_DRIVER_ALLOWANCE = 'driver_allowance';
    public const CAT_FOOD = 'food';
    public const CAT_ACCOMMODATION = 'accommodation';
    public const CAT_PARKING = 'parking';
    public const CAT_LOADING_UNLOADING = 'loading_unloading';
    public const CAT_ROUTE_EXPENSE = 'route_expense';
    public const CAT_EMERGENCY_REPAIR = 'emergency_repair';
    public const CAT_FUEL_SURCHARGE = 'fuel_surcharge';
    public const CAT_POLICE_FINE = 'police_fine';
    public const CAT_WEIGHT_BRIDGE = 'weight_bridge';
    public const CAT_OTHER = 'other';

    protected $fillable = [
        'company_id',
        'branch_id',
        'voucher_number',
        'expense_date',
        'trip_id',
        'vehicle_id',
        'driver_id',
        'expense_category',
        'amount',
        'payment_method',
        'supporting_document_path',
        'status',
        'rejection_reason',
        'recorded_by',
        'approved_by',
        'approved_at',
        'posted_by',
        'posted_at',
        'notes',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'posted_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    public function canApprove(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function canPost(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }
}

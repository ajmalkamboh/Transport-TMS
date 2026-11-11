<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class OwnerSettlementDeduction extends Model
{
    use HasFactory, BelongsToCompany;

    protected $table = 'owner_settlement_deductions';

    public const TYPE_FUEL = 'fuel';
    public const TYPE_REPAIR = 'repair';
    public const TYPE_ADVANCE = 'advance';
    public const TYPE_PENALTY = 'penalty';
    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'company_id',
        'owner_settlement_id',
        'deduction_type',
        'amount',
        'reference_type',
        'reference_id',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(OwnerSettlement::class, 'owner_settlement_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}


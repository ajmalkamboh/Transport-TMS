<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverAdvance extends Model
{
    use HasFactory, BelongsToCompany;

    protected $table = 'driver_advances';

    protected $fillable = [
        'company_id',
        'driver_id',
        'advance_number',
        'date',
        'amount',
        'purpose',
        'payment_method',
        'reference',
        'status', // 'disbursed', 'settled', 'partially_settled', 'cancelled'
        'settled_amount',
        'balance_amount',
        'trip_id',
        'disbursed_by',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'settled_amount' => 'decimal:2',
        'balance_amount' => 'decimal:2',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function disburser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disbursed_by');
    }
}

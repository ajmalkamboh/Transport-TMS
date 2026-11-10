<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FuelCard extends Model
{
    use HasFactory, BelongsToCompany;

    protected $table = 'fuel_cards';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'company_id',
        'fuel_supplier_id',
        'card_number',
        'assigned_vehicle_id',
        'assigned_driver_id',
        'monthly_limit',
        'current_balance',
        'expiry_date',
        'status',
    ];

    protected $casts = [
        'monthly_limit' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'expiry_date' => 'date',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(FuelSupplier::class, 'fuel_supplier_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'assigned_vehicle_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'assigned_driver_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(FuelEntry::class);
    }
}

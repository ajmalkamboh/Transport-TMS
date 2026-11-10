<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FuelStation extends Model
{
    use HasFactory, BelongsToCompany;

    protected $table = 'fuel_stations';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'company_id',
        'fuel_supplier_id',
        'name',
        'code',
        'city',
        'address',
        'phone',
        'status',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(FuelSupplier::class, 'fuel_supplier_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(FuelEntry::class);
    }
}


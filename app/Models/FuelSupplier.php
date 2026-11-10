<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FuelSupplier extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $table = 'fuel_suppliers';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'contact_person',
        'phone',
        'email',
        'ntn_number',
        'address',
        'credit_limit',
        'balance',
        'payment_terms_days',
        'status',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'balance' => 'decimal:2',
        'payment_terms_days' => 'integer',
    ];

    public function stations(): HasMany
    {
        return $this->hasMany(FuelStation::class);
    }

    public function cards(): HasMany
    {
        return $this->hasMany(FuelCard::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(FuelEntry::class);
    }
}


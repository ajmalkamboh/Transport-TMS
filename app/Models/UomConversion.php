<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UomConversion extends Model
{
    use HasFactory, BelongsToCompany;

    protected $table = 'uom_conversions';

    protected $fillable = [
        'company_id',
        'from_uom_id',
        'to_uom_id',
        'conversion_factor',
    ];

    protected $casts = [
        'conversion_factor' => 'decimal:4',
    ];

    public function fromUom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasurement::class, 'from_uom_id');
    }

    public function toUom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasurement::class, 'to_uom_id');
    }
}


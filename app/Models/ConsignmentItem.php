<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsignmentItem extends Model
{
    use HasFactory;

    protected $table = 'consignment_items';

    protected $fillable = [
        'consignment_id',
        'item_description',
        'package_type',
        'quantity',
        'weight_kg',
        'declared_value',
        'dimensions',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'weight_kg' => 'decimal:2',
        'declared_value' => 'decimal:2',
    ];

    public function consignment(): BelongsTo
    {
        return $this->belongsTo(Consignment::class);
    }
}

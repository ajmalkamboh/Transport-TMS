<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseStock extends Model
{
    use HasFactory, BelongsToCompany;

    protected $table = 'warehouse_stocks';

    protected $fillable = [
        'company_id',
        'warehouse_id',
        'inventory_item_id',
        'quantity_on_hand',
        'quantity_allocated',
        'average_unit_cost',
        'total_valuation',
    ];

    protected $casts = [
        'quantity_on_hand' => 'decimal:2',
        'quantity_allocated' => 'decimal:2',
        'average_unit_cost' => 'decimal:2',
        'total_valuation' => 'decimal:2',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /**
     * Quantity available to be issued or transferred without exceeding stock.
     */
    public function getAvailableQuantityAttribute(): float
    {
        return max(0, (float) $this->quantity_on_hand - (float) $this->quantity_allocated);
    }
}

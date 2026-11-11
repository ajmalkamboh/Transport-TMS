<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryItem extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $table = 'inventory_items';

    protected $fillable = [
        'company_id',
        'category_id',
        'primary_uom_id',
        'item_code',
        'sku',
        'name',
        'description',
        'reorder_level',
        'safety_stock',
        'preferred_supplier_id',
        'average_cost',
        'last_purchase_cost',
        'is_active',
    ];

    protected $casts = [
        'reorder_level' => 'decimal:2',
        'safety_stock' => 'decimal:2',
        'average_cost' => 'decimal:2',
        'last_purchase_cost' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id');
    }

    public function primaryUom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasurement::class, 'primary_uom_id');
    }

    public function preferredSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'preferred_supplier_id');
    }

    public function warehouseStocks(): HasMany
    {
        return $this->hasMany(WarehouseStock::class, 'inventory_item_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(StockLedgerEntry::class, 'inventory_item_id');
    }

    /**
     * Get aggregate total quantity on hand across all warehouses.
     */
    public function getTotalStockOnHandAttribute(): float
    {
        return (float) $this->warehouseStocks()->sum('quantity_on_hand');
    }

    /**
     * Get aggregate total available quantity across all warehouses.
     */
    public function getTotalStockAvailableAttribute(): float
    {
        return (float) $this->warehouseStocks()->selectRaw('SUM(quantity_on_hand - quantity_allocated) as total_available')->value('total_available') ?? 0.0;
    }

    /**
     * Check if total stock is below or equal to reorder level.
     */
    public function isLowStock(): bool
    {
        return $this->reorder_level > 0 && $this->total_stock_available <= $this->reorder_level;
    }
}

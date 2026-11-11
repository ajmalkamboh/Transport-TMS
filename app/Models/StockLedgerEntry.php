<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLedgerEntry extends Model
{
    use HasFactory, BelongsToCompany;

    protected $table = 'stock_ledger_entries';

    public const TYPE_OPENING_STOCK = 'opening_stock';
    public const TYPE_GRN = 'grn';
    public const TYPE_ISSUE_MAINTENANCE = 'issue_maintenance';
    public const TYPE_ISSUE_GENERAL = 'issue_general';
    public const TYPE_TRANSFER_IN = 'transfer_in';
    public const TYPE_TRANSFER_OUT = 'transfer_out';
    public const TYPE_PURCHASE_RETURN = 'purchase_return';
    public const TYPE_ADJUSTMENT_GAIN = 'adjustment_gain';
    public const TYPE_ADJUSTMENT_LOSS = 'adjustment_loss';
    public const TYPE_DAMAGE = 'damage';
    public const TYPE_WASTAGE = 'wastage';
    public const TYPE_PHYSICAL_COUNT = 'physical_count';

    protected $fillable = [
        'company_id',
        'warehouse_id',
        'inventory_item_id',
        'transaction_date',
        'transaction_type',
        'reference_type',
        'reference_id',
        'reference_number',
        'quantity_in',
        'quantity_out',
        'unit_cost',
        'total_cost',
        'balance_quantity',
        'balance_valuation',
        'user_id',
        'notes',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'quantity_in' => 'decimal:2',
        'quantity_out' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'balance_quantity' => 'decimal:2',
        'balance_valuation' => 'decimal:2',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

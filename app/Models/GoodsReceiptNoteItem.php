<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptNoteItem extends Model
{
    use HasFactory;

    protected $table = 'goods_receipt_note_items';

    public const CONDITION_GOOD = 'good';
    public const CONDITION_DAMAGED = 'damaged';
    public const CONDITION_REJECTED = 'rejected';

    protected $fillable = [
        'goods_receipt_note_id',
        'inventory_item_id',
        'purchase_order_item_id',
        'quantity_received',
        'unit_rate',
        'total_amount',
        'batch_or_serial_number',
        'condition_status',
        'notes',
    ];

    protected $casts = [
        'quantity_received' => 'decimal:2',
        'unit_rate' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function grn(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptNote::class, 'goods_receipt_note_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }
}

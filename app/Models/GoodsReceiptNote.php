<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoodsReceiptNote extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $table = 'goods_receipt_notes';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
        'branch_id',
        'warehouse_id',
        'supplier_id',
        'purchase_order_id',
        'grn_number',
        'received_date',
        'supplier_challan_number',
        'status',
        'total_received_amount',
        'received_by',
        'remarks',
    ];

    protected $casts = [
        'received_date' => 'date',
        'total_received_amount' => 'decimal:2',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptNoteItem::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(SupplierBill::class);
    }
}


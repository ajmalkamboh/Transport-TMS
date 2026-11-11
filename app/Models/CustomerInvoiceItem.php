<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerInvoiceItem extends Model
{
    use HasFactory, BelongsToCompany;

    protected $table = 'customer_invoice_items';

    protected $fillable = [
        'company_id',
        'customer_invoice_id',
        'consignment_id',
        'item_description',
        'description',
        'quantity',
        'unit_rate',
        'amount',
    ];

    public function getDescriptionAttribute(): ?string
    {
        return $this->item_description;
    }

    public function setDescriptionAttribute($value): void
    {
        $this->attributes['item_description'] = $value;
    }

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_rate' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(CustomerInvoice::class, 'customer_invoice_id');
    }

    public function consignment(): BelongsTo
    {
        return $this->belongsTo(Consignment::class, 'consignment_id');
    }
}

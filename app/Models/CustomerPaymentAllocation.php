<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPaymentAllocation extends Model
{
    use HasFactory, BelongsToCompany;

    protected $table = 'customer_payment_allocations';

    protected $fillable = [
        'company_id',
        'customer_payment_id',
        'customer_invoice_id',
        'allocated_amount',
        'allocated_at',
        'allocated_by',
    ];

    protected $casts = [
        'allocated_amount' => 'decimal:2',
        'allocated_at' => 'date',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(CustomerPayment::class, 'customer_payment_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(CustomerInvoice::class, 'customer_invoice_id');
    }

    public function allocatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }
}

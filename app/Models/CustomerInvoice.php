<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerInvoice extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $table = 'customer_invoices';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_POSTED = 'posted';
    public const STATUS_PAID = 'paid';
    public const STATUS_PARTIALLY_PAID = 'partially_paid';
    public const STATUS_UNPAID = 'unpaid';
    public const STATUS_PARTIAL = 'partially_paid';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REVERSED = 'reversed';

    public const PAYMENT_UNPAID = 'unpaid';
    public const PAYMENT_PARTIALLY_PAID = 'partially_paid';
    public const PAYMENT_PAID = 'paid';

    protected $fillable = [
        'company_id',
        'branch_id',
        'customer_id',
        'trip_id',
        'invoice_number',
        'invoice_date',
        'due_date',
        'subtotal',
        'tax_rate_percent',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'paid_amount',
        'balance_due',
        'status',
        'payment_status',
        'journal_voucher_id',
        'created_by',
        'posted_by',
        'posted_at',
        'notes',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_rate_percent' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance_due' => 'decimal:2',
        'posted_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class, 'trip_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CustomerInvoiceItem::class, 'customer_invoice_id');
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(JournalVoucher::class, 'journal_voucher_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CustomerPaymentAllocation::class, 'customer_invoice_id');
    }

    public function recalculateTotals(): void
    {
        $this->subtotal = round((float) $this->items()->sum('amount'), 2);
        
        if ($this->tax_rate_percent > 0) {
            $this->tax_amount = round($this->subtotal * ($this->tax_rate_percent / 100.0), 2);
        }

        $this->total_amount = round($this->subtotal + $this->tax_amount - $this->discount_amount, 2);
        $this->balance_due = round(max(0, $this->total_amount - $this->paid_amount), 2);

        if ($this->paid_amount >= $this->total_amount && $this->total_amount > 0) {
            $this->payment_status = self::PAYMENT_PAID;
            if (in_array($this->status, [self::STATUS_POSTED, self::STATUS_PARTIALLY_PAID])) {
                $this->status = self::STATUS_PAID;
            }
        } elseif ($this->paid_amount > 0) {
            $this->payment_status = self::PAYMENT_PARTIALLY_PAID;
            if ($this->status === self::STATUS_POSTED) {
                $this->status = self::STATUS_PARTIALLY_PAID;
            }
        } else {
            $this->payment_status = self::PAYMENT_UNPAID;
        }

        $this->save();
    }

    public function isPosted(): bool
    {
        return in_array($this->status, [self::STATUS_POSTED, self::STATUS_PARTIALLY_PAID, self::STATUS_PAID]);
    }
}


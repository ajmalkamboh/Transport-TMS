<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerPayment extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $table = 'customer_payments';

    public const TYPE_INVOICE_PAYMENT = 'invoice_payment';
    public const TYPE_CUSTOMER_ADVANCE = 'customer_advance';
    public const TYPE_ON_ACCOUNT = 'on_account';

    public const METHOD_CASH = 'cash';
    public const METHOD_BANK_TRANSFER = 'bank_transfer';
    public const METHOD_CHEQUE = 'cheque';
    public const METHOD_ONLINE = 'online';
    public const METHOD_OTHER = 'other';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_POSTED = 'posted';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_BOUNCED = 'bounced';

    protected $fillable = [
        'company_id',
        'branch_id',
        'customer_id',
        'customer_invoice_id',
        'bank_account_id',
        'receipt_number',
        'receipt_date',
        'amount',
        'payment_type',
        'unapplied_amount',
        'payment_method',
        'reference_number',
        'cheque_number',
        'cheque_date',
        'bank_name',
        'status',
        'journal_voucher_id',
        'received_by',
        'notes',
    ];

    protected $casts = [
        'receipt_date' => 'date',
        'cheque_date' => 'date',
        'amount' => 'decimal:2',
        'unapplied_amount' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(CustomerInvoice::class, 'customer_invoice_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(JournalVoucher::class, 'journal_voucher_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CustomerPaymentAllocation::class, 'customer_payment_id');
    }

    public function isAdvance(): bool
    {
        return $this->payment_type === self::TYPE_CUSTOMER_ADVANCE;
    }
}

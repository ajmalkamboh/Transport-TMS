<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankReconciliation extends Model
{
    use HasFactory, BelongsToCompany;

    protected $table = 'bank_reconciliations';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_RECONCILED = 'reconciled';

    protected $fillable = [
        'company_id',
        'bank_account_id',
        'statement_date',
        'statement_balance',
        'book_balance',
        'reconciled_balance',
        'difference',
        'status',
        'notes',
        'reconciled_by',
        'reconciled_at',
    ];

    protected $casts = [
        'statement_date' => 'date',
        'statement_balance' => 'decimal:2',
        'book_balance' => 'decimal:2',
        'reconciled_balance' => 'decimal:2',
        'difference' => 'decimal:2',
        'reconciled_at' => 'datetime',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function reconciledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    public function isReconciled(): bool
    {
        return $this->status === self::STATUS_RECONCILED && abs($this->difference) < 0.01;
    }
}

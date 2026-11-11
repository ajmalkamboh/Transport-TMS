<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankAccount extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $table = 'bank_accounts';

    public const TYPE_BANK = 'bank';
    public const TYPE_CASH_IN_HAND = 'cash_in_hand';
    public const TYPE_PETTY_CASH = 'petty_cash';

    protected $fillable = [
        'company_id',
        'branch_id',
        'chart_of_account_id',
        'account_name',
        'account_type',
        'bank_name',
        'branch_name',
        'account_number',
        'iban',
        'currency',
        'opening_balance',
        'current_balance',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(BankReconciliation::class, 'bank_account_id');
    }
}

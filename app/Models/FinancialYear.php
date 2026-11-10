<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialYear extends Model
{
    use HasFactory, BelongsToCompany;

    protected $table = 'financial_years';

    protected $fillable = [
        'company_id',
        'name',
        'start_date',
        'end_date',
        'status', // 'open', 'closed'
        'is_current',
        'opening_balances_locked',
        'closing_notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
        'opening_balances_locked' => 'boolean',
    ];

    /**
     * Accounting periods within this financial year.
     */
    public function periods(): HasMany
    {
        return $this->hasMany(AccountingPeriod::class, 'financial_year_id')->orderBy('period_number');
    }

    /**
     * Check if financial year is open for transactions.
     */
    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Check if financial year is closed.
     */
    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /**
     * Check if a given date falls within this financial year's bounds.
     */
    public function containsDate(string|Carbon $date): bool
    {
        $carbonDate = is_string($date) ? Carbon::parse($date) : $date;
        return $carbonDate->betweenIncluded($this->start_date, $this->end_date);
    }
}


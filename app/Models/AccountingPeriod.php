<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingPeriod extends Model
{
    use HasFactory, BelongsToCompany;

    protected $table = 'accounting_periods';

    protected $fillable = [
        'company_id',
        'financial_year_id',
        'name',
        'period_number',
        'start_date',
        'end_date',
        'status', // 'open', 'closed'
        'closed_at',
        'closed_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'closed_at' => 'datetime',
        'period_number' => 'integer',
    ];

    /**
     * Parent financial year.
     */
    public function financialYear(): BelongsTo
    {
        return $this->belongsTo(FinancialYear::class, 'financial_year_id');
    }

    /**
     * User who closed this accounting period.
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Check if period is open for posting.
     */
    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Check if period is closed.
     */
    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /**
     * Check if a given date falls within this accounting period.
     */
    public function containsDate(string|Carbon $date): bool
    {
        $carbonDate = is_string($date) ? Carbon::parse($date) : $date;
        return $carbonDate->betweenIncluded($this->start_date, $this->end_date);
    }
}

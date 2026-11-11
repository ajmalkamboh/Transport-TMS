<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DriverSettlement extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $table = 'driver_settlements';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_POSTED = 'posted';
    public const STATUS_SETTLED = 'settled';

    protected $fillable = [
        'company_id',
        'branch_id',
        'driver_id',
        'trip_id',
        'settlement_number',
        'settlement_date',
        'total_advances',
        'driver_allowance',
        'out_of_pocket_expenses',
        'net_payable',
        'net_recoverable',
        'status',
        'journal_voucher_id',
        'settled_by',
        'settled_at',
        'notes',
    ];

    protected $casts = [
        'settlement_date' => 'date',
        'total_advances' => 'decimal:2',
        'driver_allowance' => 'decimal:2',
        'out_of_pocket_expenses' => 'decimal:2',
        'net_payable' => 'decimal:2',
        'net_recoverable' => 'decimal:2',
        'settled_at' => 'datetime',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class, 'trip_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(JournalVoucher::class, 'journal_voucher_id');
    }

    public function recalculateTotals(): void
    {
        $creditTotal = (float) $this->driver_allowance + (float) $this->out_of_pocket_expenses;
        $debitTotal = (float) $this->total_advances;

        if ($creditTotal >= $debitTotal) {
            $this->net_payable = round($creditTotal - $debitTotal, 2);
            $this->net_recoverable = 0.00;
        } else {
            $this->net_payable = 0.00;
            $this->net_recoverable = round($debitTotal - $creditTotal, 2);
        }

        $this->save();
    }
}


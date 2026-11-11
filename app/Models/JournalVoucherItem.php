<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalVoucherItem extends Model
{
    use HasFactory, BelongsToCompany;

    protected $table = 'journal_voucher_items';

    protected $fillable = [
        'company_id',
        'journal_voucher_id',
        'chart_of_account_id',
        'entry_type',
        'debit',
        'credit',
        'description',
        'customer_id',
        'vehicle_owner_id',
        'driver_id',
        'fuel_supplier_id',
        'vehicle_id',
        'trip_id',
        'reconciled',
        'reconciled_at',
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
        'reconciled' => 'boolean',
        'reconciled_at' => 'datetime',
    ];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(JournalVoucher::class, 'journal_voucher_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(VehicleOwner::class, 'vehicle_owner_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(FuelSupplier::class, 'fuel_supplier_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class, 'trip_id');
    }

    public function isDebit(): bool
    {
        return $this->entry_type === 'debit';
    }

    public function isCredit(): bool
    {
        return $this->entry_type === 'credit';
    }
}

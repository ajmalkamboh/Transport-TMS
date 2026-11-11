<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChartOfAccount extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $table = 'chart_of_accounts';

    public const TYPE_ASSET = 'asset';
    public const TYPE_LIABILITY = 'liability';
    public const TYPE_EQUITY = 'equity';
    public const TYPE_REVENUE = 'revenue';
    public const TYPE_EXPENSE = 'expense';

    public const BALANCE_DEBIT = 'debit';
    public const BALANCE_CREDIT = 'credit';

    // Standard Core System Account Codes
    public const CODE_CASH_IN_HAND = '1010';
    public const CODE_BANK_ACCOUNTS = '1020';
    public const CODE_ACCOUNTS_RECEIVABLE = '1100';
    public const CODE_DRIVER_ADVANCE_CLEARING = '1150';
    public const CODE_FUEL_ADVANCE_CLEARING = '1160';
    public const CODE_INVENTORY_SPARES = '1300';
    public const CODE_FLEET_VEHICLES = '1500';
    public const CODE_ACCUMULATED_DEPRECIATION = '1590';
    
    public const CODE_AP_FUEL_SUPPLIERS = '2100';
    public const CODE_AP_TRADE_SUPPLIERS = '2110';
    public const CODE_AP_WORKSHOPS = '2120';
    public const CODE_AP_VEHICLE_OWNERS = '2150';
    public const CODE_CUSTOMER_ADVANCES = '2200';
    public const CODE_SALARIES_PAYABLE = '2300';
    
    public const CODE_OWNERS_CAPITAL = '3000';
    public const CODE_RETAINED_EARNINGS = '3100';
    
    public const CODE_FREIGHT_REVENUE = '4100';
    public const CODE_DEMURRAGE_INCOME = '4200';
    public const CODE_PENALTIES_INCOME = '4900';
    
    public const CODE_DIRECT_FUEL = '5100';
    public const CODE_TRIP_EXPENSES = '5200';
    public const CODE_DRIVER_ALLOWANCES = '5210';
    public const CODE_ATTACHED_TRUCK_HIRE = '5300';
    public const CODE_VEHICLE_MAINTENANCE = '5400';
    public const CODE_INVENTORY_WRITE_OFF = '5490';
    
    public const CODE_DEPRECIATION_EXPENSE = '6100';
    public const CODE_ADMIN_EXPENSES = '6200';

    protected $fillable = [
        'company_id',
        'parent_id',
        'account_code',
        'account_name',
        'account_type',
        'account_group',
        'normal_balance',
        'opening_balance',
        'current_balance',
        'is_system',
        'is_reconcilable',
        'is_active',
        'description',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'is_system' => 'boolean',
        'is_reconcilable' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function voucherItems(): HasMany
    {
        return $this->hasMany(JournalVoucherItem::class, 'chart_of_account_id');
    }

    public function isDebitNature(): bool
    {
        return $this->normal_balance === self::BALANCE_DEBIT;
    }

    public function isCreditNature(): bool
    {
        return $this->normal_balance === self::BALANCE_CREDIT;
    }
}


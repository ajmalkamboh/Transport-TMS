<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentSequence extends Model
{
    use HasFactory, BelongsToCompany;

    protected $table = 'document_sequences';

    // 11 Core Transport Business Document Types
    public const TYPE_QUOTATION = 'quotation';
    public const TYPE_BOOKING = 'booking';
    public const TYPE_CONSIGNMENT = 'consignment';
    public const TYPE_TRIP = 'trip';
    public const TYPE_INVOICE = 'invoice';
    public const TYPE_RECEIPT = 'receipt';
    public const TYPE_PAYMENT = 'payment';
    public const TYPE_EXPENSE_VOUCHER = 'expense_voucher';
    public const TYPE_JOURNAL_VOUCHER = 'journal_voucher';
    public const TYPE_OWNER_SETTLEMENT = 'owner_settlement';
    public const TYPE_PURCHASE_ORDER = 'purchase_order';
    public const TYPE_FUEL_ENTRY = 'fuel_entry';
    public const TYPE_JOB_CARD = 'job_card';
    public const TYPE_DRIVER_SETTLEMENT = 'driver_settlement';
    public const TYPE_CONTRA_VOUCHER = 'contra_voucher';

    public const CORE_ELEVEN_TYPES = [
        self::TYPE_QUOTATION => ['label' => 'Freight Quotation', 'default_prefix' => 'QT'],
        self::TYPE_BOOKING => ['label' => 'Cargo Booking Order', 'default_prefix' => 'BK'],
        self::TYPE_CONSIGNMENT => ['label' => 'Consignment / Lorry Receipt (Bilty)', 'default_prefix' => 'LR'],
        self::TYPE_TRIP => ['label' => 'Trip Manifest & Dispatch', 'default_prefix' => 'TR'],
        self::TYPE_INVOICE => ['label' => 'Freight Customer Invoice', 'default_prefix' => 'INV'],
        self::TYPE_RECEIPT => ['label' => 'Money Collection Receipt', 'default_prefix' => 'RCP'],
        self::TYPE_PAYMENT => ['label' => 'Payment Voucher', 'default_prefix' => 'PAY'],
        self::TYPE_EXPENSE_VOUCHER => ['label' => 'Trip Expense Voucher', 'default_prefix' => 'EXP'],
        self::TYPE_JOURNAL_VOUCHER => ['label' => 'General Journal Voucher', 'default_prefix' => 'JV'],
        self::TYPE_OWNER_SETTLEMENT => ['label' => 'Vehicle Owner Settlement', 'default_prefix' => 'STL'],
        self::TYPE_PURCHASE_ORDER => ['label' => 'Parts & Fuel Purchase Order', 'default_prefix' => 'PO'],
    ];

    public const ALL_TYPES = [
        self::TYPE_QUOTATION => ['label' => 'Freight Quotation', 'default_prefix' => 'QT'],
        self::TYPE_BOOKING => ['label' => 'Cargo Booking Order', 'default_prefix' => 'BK'],
        self::TYPE_CONSIGNMENT => ['label' => 'Consignment / Lorry Receipt (Bilty)', 'default_prefix' => 'LR'],
        self::TYPE_TRIP => ['label' => 'Trip Manifest & Dispatch', 'default_prefix' => 'TR'],
        self::TYPE_INVOICE => ['label' => 'Freight Customer Invoice', 'default_prefix' => 'INV'],
        self::TYPE_RECEIPT => ['label' => 'Money Collection Receipt', 'default_prefix' => 'RCP'],
        self::TYPE_PAYMENT => ['label' => 'Payment Voucher', 'default_prefix' => 'PAY'],
        self::TYPE_EXPENSE_VOUCHER => ['label' => 'Trip Expense Voucher', 'default_prefix' => 'EXP'],
        self::TYPE_JOURNAL_VOUCHER => ['label' => 'General Journal Voucher', 'default_prefix' => 'JV'],
        self::TYPE_OWNER_SETTLEMENT => ['label' => 'Vehicle Owner Settlement', 'default_prefix' => 'STL'],
        self::TYPE_PURCHASE_ORDER => ['label' => 'Parts & Fuel Purchase Order', 'default_prefix' => 'PO'],
        self::TYPE_FUEL_ENTRY => ['label' => 'Fuel Refuel & Issue Slip', 'default_prefix' => 'FL'],
        self::TYPE_JOB_CARD => ['label' => 'Workshop Job Card', 'default_prefix' => 'JC'],
        self::TYPE_DRIVER_SETTLEMENT => ['label' => 'Driver Voyage Settlement', 'default_prefix' => 'DS'],
        self::TYPE_CONTRA_VOUCHER => ['label' => 'Bank / Cash Contra Voucher', 'default_prefix' => 'CV'],
    ];

    protected $fillable = [
        'company_id',
        'branch_id',
        'document_type',
        'year',
        'prefix',
        'padding',
        'suffix',
        'reset_frequency',
        'last_number',
    ];

    protected $casts = [
        'year' => 'integer',
        'last_number' => 'integer',
        'padding' => 'integer',
    ];

    /**
     * Branch this sequence belongs to (null if company-wide).
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}

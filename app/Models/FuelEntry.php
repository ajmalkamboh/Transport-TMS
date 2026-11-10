<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FuelEntry extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $table = 'fuel_entries';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_POSTED = 'posted';

    public const FUEL_DIESEL = 'diesel';
    public const FUEL_PETROL = 'petrol';
    public const FUEL_CNG = 'cng';
    public const FUEL_ADBLUE = 'adblue';

    protected $fillable = [
        'company_id',
        'branch_id',
        'entry_number',
        'entry_date',
        'fuel_supplier_id',
        'fuel_station_id',
        'fuel_card_id',
        'vehicle_id',
        'driver_id',
        'trip_id',
        'fuel_type',
        'quantity_liters',
        'rate_per_liter',
        'total_amount',
        'payment_method',
        'payment_status',
        'odometer_km',
        'previous_odometer_km',
        'distance_km',
        'fuel_consumed_liters',
        'fuel_economy_km_per_liter',
        'cost_per_km',
        'is_full_tank',
        'is_suspicious',
        'anomaly_reason',
        'receipt_photo_path',
        'invoice_reference',
        'status',
        'created_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'quantity_liters' => 'decimal:2',
        'rate_per_liter' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'odometer_km' => 'integer',
        'previous_odometer_km' => 'integer',
        'distance_km' => 'integer',
        'fuel_consumed_liters' => 'decimal:2',
        'fuel_economy_km_per_liter' => 'decimal:2',
        'cost_per_km' => 'decimal:2',
        'is_full_tank' => 'boolean',
        'is_suspicious' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(FuelSupplier::class, 'fuel_supplier_id');
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(FuelStation::class, 'fuel_station_id');
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(FuelCard::class, 'fuel_card_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

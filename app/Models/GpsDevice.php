<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GpsDevice extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $table = 'gps_devices';

    public const STATUS_ONLINE = 'online';
    public const STATUS_OFFLINE = 'offline';
    public const STATUS_DORMANT = 'dormant';
    public const STATUS_FAULTY = 'faulty';

    public const PROVIDER_GENERIC = 'generic';
    public const PROVIDER_TRACCAR = 'traccar';
    public const PROVIDER_TELTONIKA = 'teltonika';

    protected $fillable = [
        'company_id',
        'vehicle_id',
        'device_identifier',
        'imei',
        'sim_card_number',
        'protocol_provider',
        'model_name',
        'status',
        'last_ping_at',
        'is_active',
    ];

    protected $casts = [
        'last_ping_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(VehicleLocation::class, 'gps_device_id');
    }

    public function latestLocation(): ?VehicleLocation
    {
        return $this->locations()->latest('recorded_at')->first();
    }
}


<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProofOfDelivery extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $table = 'proof_of_deliveries';

    public const STATUS_VERIFIED = 'verified';
    public const STATUS_PENDING_VERIFICATION = 'pending_verification';
    public const STATUS_DISPUTED = 'disputed';

    protected $fillable = [
        'company_id',
        'trip_id',
        'consignment_id',
        'trip_consignment_id',
        'receiver_name',
        'receiver_phone',
        'receiver_cnic',
        'delivery_datetime',
        'delivered_quantity',
        'damaged_quantity',
        'short_quantity',
        'gps_latitude',
        'gps_longitude',
        'signature_file_path',
        'document_file_path',
        'photo_file_path',
        'status',
        'exception_notes',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'delivery_datetime' => 'datetime',
        'delivered_quantity' => 'integer',
        'damaged_quantity' => 'integer',
        'short_quantity' => 'integer',
        'gps_latitude' => 'decimal:7',
        'gps_longitude' => 'decimal:7',
        'verified_at' => 'datetime',
    ];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function consignment(): BelongsTo
    {
        return $this->belongsTo(Consignment::class);
    }

    public function tripConsignment(): BelongsTo
    {
        return $this->belongsTo(TripConsignment::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}

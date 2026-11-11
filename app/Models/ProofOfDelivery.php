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
        'file_path',
        'signature_file_path',
        'document_file_path',
        'photo_file_path',
        'status',
        'exception_notes',
        'verified_by',
        'verified_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (ProofOfDelivery $pod) {
            if (empty($pod->consignment_id) && !empty($pod->trip_consignment_id)) {
                $alloc = TripConsignment::find($pod->trip_consignment_id);
                if ($alloc) {
                    $pod->consignment_id = $alloc->consignment_id;
                    if (empty($pod->delivered_quantity)) {
                        $pod->delivered_quantity = $alloc->delivered_quantity ?: $alloc->assigned_quantity;
                    }
                }
            }
            if (empty($pod->delivery_datetime)) {
                $pod->delivery_datetime = now();
            }
            if (empty($pod->delivered_quantity)) {
                $pod->delivered_quantity = 1;
            }
        });
    }

    public function setFilePathAttribute($value): void
    {
        $this->attributes['document_file_path'] = $value;
    }

    public function getFilePathAttribute(): ?string
    {
        return $this->attributes['document_file_path'] ?? null;
    }

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


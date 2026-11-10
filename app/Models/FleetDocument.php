<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FleetDocument extends Model
{
    use HasFactory, BelongsToCompany;

    protected $table = 'fleet_documents';

    public const TYPE_REGISTRATION = 'registration';
    public const TYPE_INSURANCE = 'insurance';
    public const TYPE_FITNESS = 'fitness_certificate';
    public const TYPE_ROUTE_PERMIT = 'route_permit';
    public const TYPE_DRIVING_LICENSE = 'driving_license';
    public const TYPE_CNIC = 'cnic';

    protected $fillable = [
        'company_id',
        'documentable_type',
        'documentable_id',
        'document_type',
        'document_number',
        'file_path',
        'file_name',
        'file_size_kb',
        'issue_date',
        'expiry_date',
        'alert_before_days',
        'is_verified',
        'verified_by',
        'notes',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'is_verified' => 'boolean',
        'alert_before_days' => 'integer',
        'file_size_kb' => 'integer',
    ];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        if (!$this->expiry_date) {
            return false;
        }

        $threshold = now()->addDays($days);
        return $this->expiry_date->isFuture() && $this->expiry_date->lessThanOrEqualTo($threshold);
    }

    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', now())
            ->whereDate('expiry_date', '<=', now()->addDays($days));
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', now());
    }
}


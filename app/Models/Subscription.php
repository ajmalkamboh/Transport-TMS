<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'subscriptions';

    protected $fillable = [
        'company_id',
        'plan_id',
        'billing_cycle',
        'status', // trial, active, expired, suspended, cancelled
        'starts_at',
        'ends_at',
        'trial_ends_at',
        'grace_period_ends_at',
        'price',
        'currency',
        'auto_renew',
        'cancelled_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'grace_period_ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'price' => 'decimal:2',
        'auto_renew' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->ends_at->isFuture();
    }

    public function isTrial(): bool
    {
        return $this->status === 'trial' && ($this->trial_ends_at === null || $this->trial_ends_at->isFuture());
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' || ($this->ends_at->isPast() && !$this->isInGracePeriod());
    }

    public function isInGracePeriod(): bool
    {
        return $this->grace_period_ends_at !== null && $this->grace_period_ends_at->isFuture();
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }
}

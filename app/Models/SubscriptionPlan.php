<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubscriptionPlan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'subscription_plans';

    public const ALL_FEATURES = [
        'fleet_management' => 'Fleet Management',
        'driver_management' => 'Driver Management',
        'customer_management' => 'Customer Management',
        'booking_management' => 'Booking Management',
        'trip_management' => 'Trip Management',
        'fuel_management' => 'Fuel Management',
        'maintenance' => 'Fleet Maintenance',
        'accounting' => 'Double-Entry Accounting',
        'inventory' => 'Spare Parts & Inventory',
        'advanced_reporting' => 'Advanced Analytics & Reports',
        'gps_tracking' => 'GPS Live Tracking',
        'customer_portal' => 'Customer Tracking Portal',
        'mobile_app' => 'Mobile Application',
    ];

    protected $fillable = [
        'name',
        'slug',
        'description',
        'monthly_price',
        'quarterly_price',
        'annual_price',
        'trial_duration_days',
        'max_vehicles',
        'max_users',
        'max_branches',
        'max_storage_mb',
        'features',
        'is_active',
    ];

    protected $casts = [
        'monthly_price' => 'decimal:2',
        'quarterly_price' => 'decimal:2',
        'annual_price' => 'decimal:2',
        'trial_duration_days' => 'integer',
        'max_vehicles' => 'integer',
        'max_users' => 'integer',
        'max_branches' => 'integer',
        'max_storage_mb' => 'integer',
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Subscriptions under this plan.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }

    /**
     * Companies assigned to this plan.
     */
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class, 'plan_id');
    }

    /**
     * Check if plan provides a given feature.
     */
    public function hasFeature(string $featureSlug): bool
    {
        if (empty($this->features)) {
            return false;
        }

        return in_array($featureSlug, $this->features, true);
    }

    /**
     * Determine price for a specific billing cycle.
     */
    public function getPriceForCycle(string $cycle): float
    {
        return match ($cycle) {
            'quarterly' => (float) $this->quarterly_price,
            'annual' => (float) $this->annual_price,
            default => (float) $this->monthly_price,
        };
    }
}

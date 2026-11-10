<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'companies';

    protected $fillable = [
        'name',
        'trading_name',
        'code',
        'slug',
        'email',
        'phone',
        'website',
        'address',
        'country',
        'currency',
        'timezone',
        'currency_precision',
        'tax_number',
        'registration_number',
        'logo_path',
        'is_tax_enabled',
        'tax_name',
        'default_tax_rate',
        'status', // active, inactive, suspended, trial
        'plan_id',
        'owner_id',
        'subscription_plan',
        'subscription_expires_at',
        'trial_ends_at',
        'settings',
        'feature_overrides',
        'onboarding_completed_at',
        'onboarding_step',
    ];

    protected $casts = [
        'settings' => 'array',
        'feature_overrides' => 'array',
        'is_tax_enabled' => 'boolean',
        'currency_precision' => 'integer',
        'default_tax_rate' => 'decimal:2',
        'subscription_expires_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'onboarding_completed_at' => 'datetime',
        'onboarding_step' => 'integer',
    ];

    /**
     * Assigned subscription plan.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    /**
     * Assigned tenant business owner.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Company branches.
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * Company employees/users.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Custom roles defined for this company.
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    /**
     * Subscriptions history for this tenant.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Current active subscription.
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->whereIn('status', ['active', 'trial'])
            ->latestOfMany();
    }

    /**
     * Platform subscription invoices.
     */
    public function subscriptionInvoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }

    /**
     * Platform subscription payments.
     */
    public function subscriptionPayments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    /**
     * Document sequences maintained for this company.
     */
    public function documentSequences(): HasMany
    {
        return $this->hasMany(DocumentSequence::class);
    }

    /**
     * Check if company has access to a specific feature (plan + overrides).
     */
    public function hasFeature(string $featureSlug): bool
    {
        // 1. Check tenant-specific feature overrides first
        if ($this->feature_overrides && array_key_exists($featureSlug, $this->feature_overrides)) {
            return (bool) $this->feature_overrides[$featureSlug];
        }

        // 2. Check active subscription plan features
        return $this->plan?->hasFeature($featureSlug) ?? false;
    }

    /**
     * Check if company can register an additional user within plan limits.
     */
    public function canAddUser(): bool
    {
        if (!$this->plan || $this->plan->max_users === -1) {
            return true;
        }

        return $this->users()->count() < $this->plan->max_users;
    }

    /**
     * Check if company can create an additional branch within plan limits.
     */
    public function canAddBranch(): bool
    {
        if (!$this->plan || $this->plan->max_branches === -1) {
            return true;
        }

        return $this->branches()->count() < $this->plan->max_branches;
    }

    /**
     * Check if company can register an additional fleet vehicle within plan limits.
     */
    public function canAddVehicle(): bool
    {
        if (!$this->plan || $this->plan->max_vehicles === -1) {
            return true;
        }

        return $this->vehicles()->count() < $this->plan->max_vehicles;
    }

    /**
     * Company fleet vehicles.
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(\App\Models\Vehicle::class);
    }

    /**
     * Attached third-party vehicle owners.
     */
    public function vehicleOwners(): HasMany
    {
        return $this->hasMany(\App\Models\VehicleOwner::class);
    }

    /**
     * Company employed / contracted drivers.
     */
    public function drivers(): HasMany
    {
        return $this->hasMany(\App\Models\Driver::class);
    }

    /**
     * Transport clients & commercial customers.
     */
    public function customers(): HasMany
    {
        return $this->hasMany(\App\Models\Customer::class);
    }

    /**
     * Customer commercial contracts.
     */
    public function customerContracts(): HasMany
    {
        return $this->hasMany(\App\Models\CustomerContract::class);
    }

    /**
     * Freight quotations.
     */
    public function quotations(): HasMany
    {
        return $this->hasMany(\App\Models\Quotation::class);
    }

    /**
     * Cargo bookings.
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(\App\Models\Booking::class);
    }

    /**
     * Consignments (Bilties / Lorry Receipts).
     */
    public function consignments(): HasMany
    {
        return $this->hasMany(\App\Models\Consignment::class);
    }

    /**
     * Transport trips.
     */
    public function trips(): HasMany
    {
        return $this->hasMany(\App\Models\Trip::class);
    }

    /**
     * Proof of deliveries.
     */
    public function proofOfDeliveries(): HasMany
    {
        return $this->hasMany(\App\Models\ProofOfDelivery::class);
    }

    public function fuelSuppliers(): HasMany
    {
        return $this->hasMany(FuelSupplier::class);
    }

    public function fuelCards(): HasMany
    {
        return $this->hasMany(FuelCard::class);
    }

    public function fuelEntries(): HasMany
    {
        return $this->hasMany(FuelEntry::class);
    }

    public function tripExpenses(): HasMany
    {
        return $this->hasMany(TripExpense::class);
    }

    public function maintenanceSchedules(): HasMany
    {
        return $this->hasMany(MaintenanceSchedule::class);
    }

    public function jobCards(): HasMany
    {
        return $this->hasMany(JobCard::class);
    }

    public function vehicleComponents(): HasMany
    {
        return $this->hasMany(VehicleComponent::class);
    }

    public function maintenanceAlerts(): HasMany
    {
        return $this->hasMany(MaintenanceAlert::class);
    }

    /**
     * Company financial years.
     */
    public function financialYears(): HasMany
    {
        return $this->hasMany(FinancialYear::class)->orderByDesc('start_date');
    }

    /**
     * Active current financial year.
     */
    public function currentFinancialYear(): HasOne
    {
        return $this->hasOne(FinancialYear::class)->where('is_current', true);
    }

    /**
     * Custom master data items defined by this company.
     */
    public function masterData(): HasMany
    {
        return $this->hasMany(MasterData::class);
    }

    /**
     * Check if tenant has completed initial onboarding setup wizard.
     */
    public function isOnboarded(): bool
    {
        return !is_null($this->onboarding_completed_at);
    }

    /**
     * Calculate onboarding wizard completion percentage (1 to 6 steps).
     */
    public function getOnboardingProgressPercentage(): int
    {
        if ($this->isOnboarded()) {
            return 100;
        }

        return min(100, (int) round((($this->onboarding_step - 1) / 6) * 100));
    }
}

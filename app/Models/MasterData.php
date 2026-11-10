<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterData extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'master_data';

    // 12 Standard Transport Master Categories
    public const CATEGORY_VEHICLE_CATEGORY = 'vehicle_category';
    public const CATEGORY_VEHICLE_TYPE = 'vehicle_type';
    public const CATEGORY_FUEL_TYPE = 'fuel_type';
    public const CATEGORY_EXPENSE_CATEGORY = 'expense_category';
    public const CATEGORY_MAINTENANCE_CATEGORY = 'maintenance_category';
    public const CATEGORY_FREIGHT_UNIT = 'freight_unit';
    public const CATEGORY_CARGO_CATEGORY = 'cargo_category';
    public const CATEGORY_PAYMENT_METHOD = 'payment_method';
    public const CATEGORY_TRIP_STATUS = 'trip_status';
    public const CATEGORY_DELIVERY_STATUS = 'delivery_status';
    public const CATEGORY_ROUTE_LOCATION = 'route_location';
    public const CATEGORY_DOCUMENT_TYPE = 'document_type';

    public const ALL_CATEGORIES = [
        self::CATEGORY_VEHICLE_CATEGORY => 'Vehicle Categories',
        self::CATEGORY_VEHICLE_TYPE => 'Vehicle Types',
        self::CATEGORY_FUEL_TYPE => 'Fuel Types',
        self::CATEGORY_EXPENSE_CATEGORY => 'Trip Expense Categories',
        self::CATEGORY_MAINTENANCE_CATEGORY => 'Maintenance Categories',
        self::CATEGORY_FREIGHT_UNIT => 'Freight Units of Measure',
        self::CATEGORY_CARGO_CATEGORY => 'Cargo Categories',
        self::CATEGORY_PAYMENT_METHOD => 'Payment Methods',
        self::CATEGORY_TRIP_STATUS => 'Trip Dispatch Statuses',
        self::CATEGORY_DELIVERY_STATUS => 'Delivery & POD Statuses',
        self::CATEGORY_ROUTE_LOCATION => 'Transit Route Locations / Hubs',
        self::CATEGORY_DOCUMENT_TYPE => 'Document Numbering Types',
    ];

    protected $fillable = [
        'company_id',
        'category',
        'code',
        'name',
        'description',
        'meta',
        'is_system',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'meta' => 'array',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Owning company (null for system-wide defaults).
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Scope to a specific category.
     */
    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to active items only.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope accessible items for a given tenant company.
     * Returns company's custom items plus default system items.
     */
    public function scopeForCompany(Builder $query, ?int $companyId): Builder
    {
        return $query->where(function ($q) use ($companyId) {
            $q->where('company_id', $companyId);
            if ($companyId !== null) {
                $q->orWhere(function ($sub) {
                    $sub->whereNull('company_id')->where('is_system', true);
                });
            } else {
                $q->orWhereNull('company_id');
            }
        })->orderBy('sort_order')->orderBy('name');
    }
}


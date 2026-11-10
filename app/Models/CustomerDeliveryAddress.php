<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerDeliveryAddress extends Model
{
    use HasFactory, BelongsToCompany;

    protected $table = 'customer_delivery_addresses';

    protected $fillable = [
        'company_id',
        'customer_id',
        'location_title',
        'contact_person',
        'contact_phone',
        'address',
        'city',
        'state',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}


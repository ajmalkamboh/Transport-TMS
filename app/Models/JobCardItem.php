<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobCardItem extends Model
{
    use HasFactory, BelongsToCompany;

    protected $table = 'job_card_items';

    protected $fillable = [
        'company_id',
        'job_card_id',
        'part_name',
        'part_number',
        'quantity',
        'unit_cost',
        'total_cost',
        'is_inventory_item',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'is_inventory_item' => 'boolean',
    ];

    public function jobCard(): BelongsTo
    {
        return $this->belongsTo(JobCard::class);
    }
}

<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $table = 'branches';

    protected $fillable = [
        'company_id',
        'manager_id',
        'code',
        'name',
        'city',
        'phone',
        'email',
        'address',
        'operating_hours',
        'dock_capacity',
        'is_head_office',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_head_office' => 'boolean',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'dock_capacity' => 'integer',
    ];

    /**
     * Branch operational manager.
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * Document sequences specific to this branch.
     */
    public function sequences(): HasMany
    {
        return $this->hasMany(DocumentSequence::class);
    }

    /**
     * Users who have this branch as their primary home branch.
     */
    public function primaryUsers(): HasMany
    {
        return $this->hasMany(User::class, 'branch_id');
    }

    /**
     * All users authorized to operate at this branch (many-to-many).
     */
    public function authorizedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_branches', 'branch_id', 'user_id')
            ->withTimestamps();
    }
}


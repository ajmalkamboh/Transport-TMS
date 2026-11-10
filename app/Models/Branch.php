<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $table = 'branches';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'city',
        'phone',
        'email',
        'address',
        'is_head_office',
        'is_active',
    ];

    protected $casts = [
        'is_head_office' => 'boolean',
        'is_active' => 'boolean',
    ];

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


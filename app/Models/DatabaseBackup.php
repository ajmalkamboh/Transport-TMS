<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatabaseBackup extends Model
{
    use HasFactory;

    protected $table = 'database_backups';

    public const TYPE_DATABASE = 'database';
    public const TYPE_STORAGE = 'storage';
    public const TYPE_FULL = 'full';

    public const STATUS_COMPLETED = 'completed';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'company_id',
        'filename',
        'disk',
        'file_path',
        'file_size_bytes',
        'checksum_sha256',
        'backup_type',
        'status',
        'verified_at',
        'notes',
    ];

    protected $casts = [
        'file_size_bytes' => 'integer',
        'verified_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}


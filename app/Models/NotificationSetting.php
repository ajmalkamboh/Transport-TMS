<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationSetting extends Model
{
    use HasFactory, BelongsToCompany;

    protected $table = 'notification_settings';

    protected $fillable = [
        'company_id',
        'event_type',
        'in_app_enabled',
        'email_enabled',
        'sms_enabled',
        'whatsapp_enabled',
        'alert_recipients',
    ];

    protected $casts = [
        'in_app_enabled' => 'boolean',
        'email_enabled' => 'boolean',
        'sms_enabled' => 'boolean',
        'whatsapp_enabled' => 'boolean',
        'alert_recipients' => 'array',
    ];
}


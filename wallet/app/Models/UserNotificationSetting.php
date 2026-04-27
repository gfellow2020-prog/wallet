<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotificationSetting extends Model
{
    protected $table = 'user_notification_settings';

    protected $fillable = [
        'user_id',
        'push_enabled',
        'email_enabled',
        'in_app_enabled',
        'hide_sensitive_push',
        'quiet_hours_start',
        'quiet_hours_end',
    ];

    protected function casts(): array
    {
        return [
            'push_enabled' => 'boolean',
            'email_enabled' => 'boolean',
            'in_app_enabled' => 'boolean',
            'hide_sensitive_push' => 'boolean',
            'quiet_hours_start' => 'datetime:H:i:s',
            'quiet_hours_end' => 'datetime:H:i:s',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}


<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpoPushTicket extends Model
{
    protected $table = 'expo_push_tickets';

    protected $fillable = [
        'user_id',
        'user_push_token_id',
        'expo_token',
        'user_notification_id',
        'ticket_id',
        'status',
        'error',
        'details',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'checked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pushToken(): BelongsTo
    {
        return $this->belongsTo(UserPushToken::class, 'user_push_token_id');
    }
}


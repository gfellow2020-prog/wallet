<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserStreak extends Model
{
    protected $fillable = [
        'user_id',
        'streak_type',
        'current_count',
        'longest_count',
        'last_qualified_on',
        'last_claimed_on',
    ];

    protected function casts(): array
    {
        return [
            'last_qualified_on' => 'date',
            'last_claimed_on' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StreakRewardDefinition extends Model
{
    protected $fillable = [
        'day_number',
        'code',
        'title',
        'description',
        'reward_type',
        'reward_value',
        'reward_meta',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'reward_meta' => 'array',
            'is_active' => 'boolean',
        ];
    }
}

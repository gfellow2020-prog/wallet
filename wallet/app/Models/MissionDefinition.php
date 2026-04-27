<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MissionDefinition extends Model
{
    protected $fillable = [
        'code',
        'title',
        'description',
        'action_type',
        'target_count',
        'reward_type',
        'reward_value',
        'reward_meta',
        'sort_order',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'target_count' => 'integer',
            'reward_meta' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function userMissions(): HasMany
    {
        return $this->hasMany(UserMission::class);
    }

    public function rewardGrants(): HasMany
    {
        return $this->hasMany(RewardGrant::class);
    }
}

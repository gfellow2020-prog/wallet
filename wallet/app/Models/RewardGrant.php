<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardGrant extends Model
{
    protected $fillable = [
        'user_id',
        'mission_definition_id',
        'user_mission_id',
        'reward_type',
        'reward_value',
        'status',
        'source_type',
        'source_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function missionDefinition(): BelongsTo
    {
        return $this->belongsTo(MissionDefinition::class);
    }

    public function userMission(): BelongsTo
    {
        return $this->belongsTo(UserMission::class);
    }
}

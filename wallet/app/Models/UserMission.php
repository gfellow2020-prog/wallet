<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserMission extends Model
{
    protected $fillable = [
        'user_id',
        'mission_definition_id',
        'period_date',
        'progress',
        'is_completed',
        'completed_at',
        'is_claimed',
        'claimed_at',
        'source_meta',
    ];

    protected function casts(): array
    {
        return [
            'period_date' => 'date',
            'progress' => 'integer',
            'is_completed' => 'boolean',
            'completed_at' => 'datetime',
            'is_claimed' => 'boolean',
            'claimed_at' => 'datetime',
            'source_meta' => 'array',
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

    public function rewardGrants(): HasMany
    {
        return $this->hasMany(RewardGrant::class);
    }
}

<?php

namespace App\Services;

use App\Models\MissionDefinition;
use App\Models\User;
use App\Models\UserMission;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MissionService
{
    public function definitionsForDate(?Carbon $date = null): Collection
    {
        $date = ($date ?? now())->copy();

        return MissionDefinition::query()
            ->where('is_active', true)
            ->where(function ($query) use ($date) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $date);
            })
            ->where(function ($query) use ($date) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $date);
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function ensureDailyMissions(User $user, ?Carbon $date = null): Collection
    {
        $date = ($date ?? now())->copy()->startOfDay();
        $dateString = $date->toDateString();
        $definitions = $this->definitionsForDate($date);

        $existingDefinitionIds = UserMission::query()
            ->where('user_id', $user->id)
            ->where('period_date', $dateString)
            ->pluck('mission_definition_id')
            ->all();

        $existingLookup = array_fill_keys(array_map('intval', $existingDefinitionIds), true);

        $now = now();
        $rows = [];
        foreach ($definitions as $definition) {
            $definitionId = (int) $definition->id;
            if (isset($existingLookup[$definitionId])) {
                continue;
            }

            $rows[] = [
                'user_id' => $user->id,
                'mission_definition_id' => $definitionId,
                'period_date' => $dateString,
                'progress' => 0,
                'is_completed' => false,
                'completed_at' => null,
                'is_claimed' => false,
                'claimed_at' => null,
                'source_meta' => json_encode(['source_keys' => []]),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            // Safe with unique constraint (user_id, mission_definition_id, period_date)
            UserMission::query()->insertOrIgnore($rows);
        }

        return UserMission::query()
            ->with('missionDefinition')
            ->where('user_id', $user->id)
            ->where('period_date', $dateString)
            ->get()
            ->sortBy(fn (UserMission $mission) => sprintf(
                '%05d-%010d',
                (int) ($mission->missionDefinition?->sort_order ?? 9999),
                (int) $mission->id
            ))
            ->values();
    }

    public function recordAction(User $user, string $actionType, array $source = [], ?Carbon $date = null): Collection
    {
        $date = ($date ?? now())->copy()->startOfDay();
        $sourceKey = $this->sourceKey($source);
        $missions = $this->ensureDailyMissions($user, $date)
            ->filter(fn (UserMission $mission) => $mission->missionDefinition?->action_type === $actionType)
            ->values();

        if ($missions->isEmpty()) {
            return collect();
        }

        return DB::transaction(function () use ($missions, $sourceKey, $source) {
            return $missions->map(function (UserMission $mission) use ($sourceKey, $source) {
                $mission = UserMission::query()
                    ->with('missionDefinition')
                    ->lockForUpdate()
                    ->findOrFail($mission->id);

                $meta = is_array($mission->source_meta) ? $mission->source_meta : [];
                $sourceKeys = array_values(array_unique(array_filter((array) ($meta['source_keys'] ?? []))));

                if ($sourceKey !== null && in_array($sourceKey, $sourceKeys, true)) {
                    return $mission;
                }

                $target = max(1, (int) ($mission->missionDefinition?->target_count ?? 1));
                $nextProgress = min($target, (int) $mission->progress + 1);

                if ($sourceKey !== null) {
                    $sourceKeys[] = $sourceKey;
                }

                $meta['source_keys'] = array_values(array_unique($sourceKeys));
                if (! empty($source)) {
                    $meta['last_source'] = $source;
                }

                $updates = [
                    'progress' => $nextProgress,
                    'source_meta' => $meta,
                ];

                if (! $mission->is_completed && $nextProgress >= $target) {
                    $updates['is_completed'] = true;
                    $updates['completed_at'] = now();
                }

                $mission->update($updates);

                return $mission->fresh(['missionDefinition']);
            });
        });
    }

    public function summary(User $user, ?Carbon $date = null): array
    {
        $missions = $this->ensureDailyMissions($user, $date);
        $completed = $missions->where('is_completed', true)->count();
        $claimable = $missions->where('is_completed', true)->where('is_claimed', false)->count();

        return [
            'date' => (($date ?? now())->copy()->startOfDay())->toDateString(),
            'total_missions' => $missions->count(),
            'completed_missions' => $completed,
            'claimable_missions' => $claimable,
            'missions' => $missions->map(fn (UserMission $mission) => $this->formatMission($mission))->values()->all(),
        ];
    }

    public function formatMission(UserMission $mission): array
    {
        $definition = $mission->missionDefinition;

        return [
            'id' => $mission->id,
            'code' => $definition?->code,
            'title' => $definition?->title,
            'description' => $definition?->description,
            'action_type' => $definition?->action_type,
            'progress' => (int) $mission->progress,
            'target_count' => (int) ($definition?->target_count ?? 1),
            'is_completed' => (bool) $mission->is_completed,
            'is_claimed' => (bool) $mission->is_claimed,
            'completed_at' => $mission->completed_at?->toIso8601String(),
            'claimed_at' => $mission->claimed_at?->toIso8601String(),
            'reward' => [
                'type' => $definition?->reward_type,
                'value' => $definition?->reward_value,
                'meta' => $definition?->reward_meta ?? [],
            ],
        ];
    }

    protected function sourceKey(array $source): ?string
    {
        $sourceType = $source['source_type'] ?? null;
        $sourceId = $source['source_id'] ?? null;

        if ($sourceType === null || $sourceId === null) {
            return null;
        }

        return sprintf('%s:%s', (string) $sourceType, (string) $sourceId);
    }
}

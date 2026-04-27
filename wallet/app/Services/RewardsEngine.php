<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserMission;

class RewardsEngine
{
    public function __construct(
        protected StreakService $streakService,
        protected MissionService $missionService,
        protected RewardGrantService $rewardGrantService,
        protected StreakRewardService $streakRewardService,
    ) {}

    public function checkIn(User $user): array
    {
        $streakResult = $this->streakService->checkIn($user);
        $milestoneGrants = $this->streakRewardService->grantMilestonesForCount(
            $user,
            $streakResult['checked_in'],
            (int) $streakResult['streak']->current_count
        );
        $missions = $this->missionService->ensureDailyMissions($user);

        return [
            'streak' => $this->streakService->summary($user),
            'missions' => $missions->map(fn (UserMission $mission) => $this->missionService->formatMission($mission))->values()->all(),
            'checked_in' => $streakResult['checked_in'],
            'continued' => $streakResult['continued'],
            'reset' => $streakResult['reset'],
            'streak_milestone_grants' => array_map(
                static fn (array $row) => [
                    'day_number' => (int) $row['definition']->day_number,
                    'code' => $row['definition']->code,
                    'title' => $row['definition']->title,
                    'reward_type' => $row['grant']->reward_type,
                    'reward_value' => $row['grant']->reward_value,
                ],
                $milestoneGrants
            ),
        ];
    }

    public function recordAction(User $user, string $actionType, array $source = []): array
    {
        $updatedMissions = $this->missionService->recordAction($user, $actionType, $source);

        return [
            'updated_missions' => $updatedMissions->map(
                fn (UserMission $mission) => $this->missionService->formatMission($mission)
            )->values()->all(),
            'summary' => $this->missionService->summary($user),
        ];
    }

    public function summary(User $user): array
    {
        return [
            'streak' => $this->streakService->summary($user),
            'missions' => $this->missionService->summary($user),
            'recent_rewards' => $user->rewardGrants()
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(fn ($grant) => [
                    'id' => $grant->id,
                    'reward_type' => $grant->reward_type,
                    'reward_value' => $grant->reward_value,
                    'status' => $grant->status,
                    'meta' => $grant->meta ?? [],
                    'granted_at' => $grant->created_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
    }
}

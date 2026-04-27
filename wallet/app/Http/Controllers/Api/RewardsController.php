<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RewardGrant;
use App\Models\StreakRewardDefinition;
use App\Models\User;
use App\Models\UserMission;
use App\Services\MissionService;
use App\Services\RewardGrantService;
use App\Services\RewardsEngine;
use App\Services\StreakService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class RewardsController extends Controller
{
    public function __construct(
        protected RewardsEngine $rewardsEngine,
        protected StreakService $streakService,
        protected MissionService $missionService,
        protected RewardGrantService $rewardGrantService,
    ) {}

    public function checkIn(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $result = $this->rewardsEngine->checkIn($user);

        $payload = [
            'message' => $result['checked_in']
                ? 'Daily check-in recorded.'
                : 'Daily check-in already recorded today.',
            'checked_in' => $result['checked_in'],
            'continued' => $result['continued'],
            'reset' => $result['reset'],
            'summary' => $this->summaryPayload($user),
        ];

        if (! empty($result['streak_milestone_grants'])) {
            $payload['streak_milestone_grants'] = $result['streak_milestone_grants'];
        }

        return response()->json($payload);
    }

    public function summary(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($this->summaryPayload($user));
    }

    public function missions(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $missions = $this->missionService->ensureDailyMissions($user);

        return response()->json([
            'date' => now()->toDateString(),
            'missions' => $missions->map(fn (UserMission $mission) => $this->missionService->formatMission($mission))->values()->all(),
            'recent_rewards' => $this->recentRewards($user),
            'badges' => $this->badges($user),
            'streak' => $this->streakService->summary($user),
        ]);
    }

    public function claim(Request $request, UserMission $mission): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $grant = $this->rewardGrantService->claim($user, $mission);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Reward claimed successfully.',
            'reward' => $this->formatGrant($grant),
            'summary' => $this->summaryPayload($user),
        ]);
    }

    protected function summaryPayload(User $user): array
    {
        $missionSummary = $this->missionService->summary($user);

        return [
            'streak' => $this->streakService->summary($user),
            'missions' => $missionSummary,
            'recent_rewards' => $this->recentRewards($user),
            'badges' => $this->badges($user),
            'streak_program' => $this->streakProgramMilestones(),
        ];
    }

    /**
     * @return list<array{day_number: int, title: string, code: string, reward_type: string, reward_value: string|null}>
     */
    protected function streakProgramMilestones(): array
    {
        if (! Schema::hasTable('streak_reward_definitions')) {
            return [];
        }

        return StreakRewardDefinition::query()
            ->where('is_active', true)
            ->orderBy('day_number')
            ->get()
            ->map(fn (StreakRewardDefinition $d) => [
                'day_number' => (int) $d->day_number,
                'title' => $d->title,
                'code' => $d->code,
                'reward_type' => $d->reward_type,
                'reward_value' => $d->reward_value,
            ])
            ->values()
            ->all();
    }

    protected function recentRewards(User $user): array
    {
        return RewardGrant::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (RewardGrant $grant) => $this->formatGrant($grant))
            ->values()
            ->all();
    }

    protected function badges(User $user): array
    {
        return RewardGrant::query()
            ->where('user_id', $user->id)
            ->where('reward_type', 'badge_unlock')
            ->latest('id')
            ->get()
            ->map(function (RewardGrant $grant) {
                $meta = $grant->meta ?? [];

                return [
                    'id' => $grant->id,
                    'code' => $meta['badge_code'] ?? $grant->reward_value,
                    'title' => $meta['badge_title'] ?? $meta['label'] ?? $grant->reward_value,
                    'claimed_at' => $grant->created_at?->toIso8601String(),
                ];
            })
            ->unique('code')
            ->values()
            ->all();
    }

    protected function formatGrant(RewardGrant $grant): array
    {
        return [
            'id' => $grant->id,
            'reward_type' => $grant->reward_type,
            'reward_value' => $grant->reward_value,
            'status' => $grant->status,
            'meta' => $grant->meta ?? [],
            'mission_definition_id' => $grant->mission_definition_id,
            'user_mission_id' => $grant->user_mission_id,
            'created_at' => $grant->created_at?->toIso8601String(),
        ];
    }
}

<?php

namespace App\Services;

use App\Models\RewardGrant;
use App\Models\StreakRewardDefinition;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

class StreakRewardService
{
    public function __construct(
        protected LedgerService $ledgerService,
    ) {}

    /**
     * @return list<array{definition: StreakRewardDefinition, grant: RewardGrant}>
     */
    public function grantMilestonesForCount(User $user, bool $checkedInThisRequest, int $streakCount): array
    {
        if (! $checkedInThisRequest || $streakCount < 1) {
            return [];
        }

        $definition = StreakRewardDefinition::query()
            ->where('is_active', true)
            ->where('day_number', $streakCount)
            ->first();

        if (! $definition) {
            return [];
        }

        if ($this->alreadyGranted($user, $definition)) {
            return [];
        }

        $grant = DB::transaction(function () use ($user, $definition) {
            if ($this->alreadyGranted($user, $definition)) {
                return null;
            }

            $meta = $definition->reward_meta ?? [];
            $meta['milestone'] = (int) $definition->day_number;
            $meta['streak_code'] = $definition->code;

            if ($definition->reward_type === 'wallet_bonus') {
                $amount = max(0, round((float) ($definition->reward_value ?? 0), 2));
                if ($amount <= 0) {
                    return null;
                }

                $grant = RewardGrant::query()->create([
                    'user_id' => $user->id,
                    'mission_definition_id' => null,
                    'user_mission_id' => null,
                    'reward_type' => $definition->reward_type,
                    'reward_value' => number_format($amount, 2, '.', ''),
                    'status' => 'granted',
                    'source_type' => StreakRewardDefinition::class,
                    'source_id' => $definition->id,
                    'meta' => array_merge($meta, [
                        'wallet_amount' => number_format($amount, 2, '.', ''),
                    ]),
                ]);

                $wallet = Wallet::query()->firstOrCreate(
                    ['user_id' => $user->id],
                    ['available_balance' => 0, 'currency' => 'ZMW']
                );

                $ledger = $this->ledgerService->credit(
                    $wallet,
                    'streak_reward',
                    $amount,
                    RewardGrant::class,
                    $grant->id,
                    [
                        'streak_code' => $definition->code,
                        'day_number' => $definition->day_number,
                        'funding_source' => 'streak_milestone',
                    ]
                );

                $meta = $grant->meta ?? [];
                $meta['wallet_ledger_id'] = $ledger->id;
                $grant->update(['meta' => $meta]);

                return $grant->fresh();
            }

            if ($definition->reward_type === 'badge_unlock') {
                return RewardGrant::query()->create([
                    'user_id' => $user->id,
                    'mission_definition_id' => null,
                    'user_mission_id' => null,
                    'reward_type' => $definition->reward_type,
                    'reward_value' => $definition->reward_value,
                    'status' => 'granted',
                    'source_type' => StreakRewardDefinition::class,
                    'source_id' => $definition->id,
                    'meta' => $meta,
                ]);
            }

            return null;
        });

        if (! $grant) {
            return [];
        }

        return [['definition' => $definition, 'grant' => $grant]];
    }

    protected function alreadyGranted(User $user, StreakRewardDefinition $definition): bool
    {
        return RewardGrant::query()
            ->where('user_id', $user->id)
            ->where('source_type', StreakRewardDefinition::class)
            ->where('source_id', $definition->id)
            ->exists();
    }
}

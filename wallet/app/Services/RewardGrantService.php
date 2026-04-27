<?php

namespace App\Services;

use App\Models\BuyRequest;
use App\Models\ProductSale;
use App\Models\RewardGrant;
use App\Models\User;
use App\Models\UserMission;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

class RewardGrantService
{
    public function __construct(
        protected LedgerService $ledgerService,
    ) {}

    public function claim(User $user, UserMission $userMission): RewardGrant
    {
        return DB::transaction(function () use ($user, $userMission) {
            $mission = UserMission::query()
                ->with('missionDefinition')
                ->lockForUpdate()
                ->findOrFail($userMission->id);

            if ((int) $mission->user_id !== (int) $user->id) {
                throw new \RuntimeException('This mission does not belong to the authenticated user.');
            }

            if (! $mission->is_completed) {
                throw new \RuntimeException('This mission is not complete yet.');
            }

            if ($mission->is_claimed) {
                throw new \RuntimeException('This mission reward has already been claimed.');
            }

            $definition = $mission->missionDefinition;
            if (! $definition) {
                throw new \RuntimeException('Mission definition could not be loaded.');
            }

            $rewardAmount = null;
            $meta = $definition->reward_meta ?? [];

            if ($definition->reward_type === 'wallet_bonus') {
                $rewardAmount = $this->fundedWalletBonusAmount($mission);

                if ($rewardAmount <= 0) {
                    throw new \RuntimeException('This wallet reward is not funded by platform revenue.');
                }

                $meta['wallet_amount'] = number_format($rewardAmount, 2, '.', '');
                $meta['funding_policy'] = 'capped_by_admin_fee';
            }

            $rewardGrant = RewardGrant::query()->create([
                'user_id' => $user->id,
                'mission_definition_id' => $definition->id,
                'user_mission_id' => $mission->id,
                'reward_type' => $definition->reward_type,
                'reward_value' => $rewardAmount !== null
                    ? number_format($rewardAmount, 2, '.', '')
                    : $definition->reward_value,
                'status' => 'granted',
                'source_type' => UserMission::class,
                'source_id' => $mission->id,
                'meta' => $meta,
            ]);

            $meta = $rewardGrant->meta ?? [];
            if ($definition->reward_type === 'wallet_bonus') {
                $wallet = Wallet::query()->firstOrCreate(
                    ['user_id' => $user->id],
                    ['available_balance' => 0, 'currency' => 'ZMW']
                );

                $ledger = $this->ledgerService->credit(
                    $wallet,
                    'mission_reward',
                    $rewardAmount,
                    RewardGrant::class,
                    $rewardGrant->id,
                    [
                        'mission_code' => $definition->code,
                        'user_mission_id' => $mission->id,
                        'funding_policy' => 'capped_by_admin_fee',
                    ]
                );

                $meta['wallet_ledger_id'] = $ledger->id;
            }

            $rewardGrant->update(['meta' => $meta]);

            $mission->update([
                'is_claimed' => true,
                'claimed_at' => now(),
            ]);

            return $rewardGrant->fresh(['missionDefinition', 'userMission']);
        });
    }

    protected function fundedWalletBonusAmount(UserMission $mission): float
    {
        $definition = $mission->missionDefinition;
        $configuredCap = max(0, round((float) ($definition?->reward_value ?? 0), 2));
        $adminFee = $this->adminFeeFundingFor($mission);

        return round(min($configuredCap, $adminFee), 2);
    }

    protected function adminFeeFundingFor(UserMission $mission): float
    {
        $meta = is_array($mission->source_meta) ? $mission->source_meta : [];
        $source = is_array($meta['last_source'] ?? null) ? $meta['last_source'] : [];

        if (($source['source_type'] ?? null) === ProductSale::class && ! empty($source['source_id'])) {
            return (float) ProductSale::query()
                ->whereKey($source['source_id'])
                ->where('buyer_id', $mission->user_id)
                ->where('status', 'completed')
                ->value('admin_fee');
        }

        if (($source['source_type'] ?? null) === BuyRequest::class && ! empty($source['sale_id'])) {
            return (float) ProductSale::query()
                ->whereKey($source['sale_id'])
                ->where('buyer_id', $mission->user_id)
                ->where('status', 'completed')
                ->value('admin_fee');
        }

        if (($source['source_type'] ?? null) === 'cart_checkout') {
            $saleIds = array_filter((array) ($source['sale_ids'] ?? []));

            if (empty($saleIds)) {
                return 0.0;
            }

            return (float) ProductSale::query()
                ->whereIn('id', $saleIds)
                ->where('buyer_id', $mission->user_id)
                ->where('status', 'completed')
                ->sum('admin_fee');
        }

        return 0.0;
    }
}

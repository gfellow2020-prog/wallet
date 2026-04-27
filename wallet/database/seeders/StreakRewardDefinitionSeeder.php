<?php

namespace Database\Seeders;

use App\Models\StreakRewardDefinition;
use Illuminate\Database\Seeder;

class StreakRewardDefinitionSeeder extends Seeder
{
    /**
     * Day streak milestones: rewards granted automatically when a user's
     * daily check-in count first reaches each day (see StreakRewardService).
     */
    public function run(): void
    {
        $rewards = [
            [
                'day_number' => 3,
                'code' => 'streak_day_3',
                'title' => '3-day streak',
                'description' => 'You checked in 3 days in a row. Small bonus for showing up.',
                'reward_type' => 'wallet_bonus',
                'reward_value' => '1.00',
                'reward_meta' => [
                    'currency' => 'ZMW',
                    'funding_source' => 'streak_milestone',
                    'milestone' => 3,
                    'label' => 'ZMW 1 streak bonus',
                ],
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'day_number' => 7,
                'code' => 'streak_day_7',
                'title' => '1-week streak',
                'description' => 'A full week of check-ins. Keep the momentum going.',
                'reward_type' => 'wallet_bonus',
                'reward_value' => '2.00',
                'reward_meta' => [
                    'currency' => 'ZMW',
                    'funding_source' => 'streak_milestone',
                    'milestone' => 7,
                    'label' => 'ZMW 2 streak bonus',
                ],
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'day_number' => 14,
                'code' => 'streak_day_14',
                'title' => '2-week streak',
                'description' => 'Two solid weeks. Here is a bigger thank-you from ExtraCash.',
                'reward_type' => 'wallet_bonus',
                'reward_value' => '5.00',
                'reward_meta' => [
                    'currency' => 'ZMW',
                    'funding_source' => 'streak_milestone',
                    'milestone' => 14,
                    'label' => 'ZMW 5 streak bonus',
                ],
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'day_number' => 30,
                'code' => 'streak_day_30',
                'title' => '30-day champion',
                'description' => 'A month of consistency. This is our top day-streak reward.',
                'reward_type' => 'wallet_bonus',
                'reward_value' => '10.00',
                'reward_meta' => [
                    'currency' => 'ZMW',
                    'funding_source' => 'streak_milestone',
                    'milestone' => 30,
                    'label' => 'ZMW 10 streak bonus',
                ],
                'sort_order' => 4,
                'is_active' => true,
            ],
        ];

        foreach ($rewards as $row) {
            StreakRewardDefinition::query()->updateOrCreate(
                ['code' => $row['code']],
                $row
            );
        }
    }
}

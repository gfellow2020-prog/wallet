<?php

namespace Database\Seeders;

use App\Models\MissionDefinition;
use Illuminate\Database\Seeder;

class RewardsMissionDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $missions = [
            [
                'code' => 'scan_qr_once',
                'title' => 'Scan and Pay',
                'description' => 'Complete one QR payment today.',
                'action_type' => 'qr_pay',
                'target_count' => 1,
                'reward_type' => 'badge_unlock',
                'reward_value' => 'qr_starter',
                'reward_meta' => [
                    'badge_code' => 'qr_starter',
                    'badge_title' => 'QR Starter',
                    'label' => 'Unlock QR Starter badge',
                ],
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'code' => 'send_money_once',
                'title' => 'Share ExtraCash',
                'description' => 'Send money to one person today.',
                'action_type' => 'send_money',
                'target_count' => 1,
                'reward_type' => 'badge_unlock',
                'reward_value' => 'connector',
                'reward_meta' => [
                    'badge_code' => 'connector',
                    'badge_title' => 'Connector',
                    'label' => 'Unlock Connector badge',
                ],
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'code' => 'buy_once',
                'title' => 'Buy Something',
                'description' => 'Complete one marketplace purchase today.',
                'action_type' => 'buy_product',
                'target_count' => 1,
                'reward_type' => 'wallet_bonus',
                'reward_value' => '5.00',
                'reward_meta' => [
                    'currency' => 'ZMW',
                    'funding_source' => 'admin_fee',
                    'label' => 'Up to ZMW 5 bonus, funded from admin fee',
                ],
                'sort_order' => 3,
                'is_active' => true,
            ],
        ];

        foreach ($missions as $mission) {
            MissionDefinition::query()->updateOrCreate(
                ['code' => $mission['code']],
                $mission
            );
        }
    }
}

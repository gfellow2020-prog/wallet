<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SystemSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $hasDescription = Schema::hasColumn('system_settings', 'description');

        $settings = [
            // Cashback
            ['key' => 'cashback_rate',            'value' => '0.02',  'description' => 'Cashback percentage (2%)'],
            ['key' => 'cashback_hold_days',        'value' => '7',     'description' => 'Days before cashback is released to available balance'],
            ['key' => 'cashback_daily_cap',        'value' => '500',   'description' => 'Max cashback a user can earn per day (ZMW)'],
            ['key' => 'cashback_monthly_cap',      'value' => '5000',  'description' => 'Max cashback a user can earn per month (ZMW)'],
            ['key' => 'cashback_enabled',          'value' => '1',     'description' => 'Master switch for cashback engine'],
            // Withdrawals
            ['key' => 'withdrawal_min_amount',     'value' => '20',    'description' => 'Minimum withdrawal amount (ZMW)'],
            ['key' => 'withdrawal_daily_max',      'value' => '10000', 'description' => 'Max daily withdrawal per user (ZMW)'],
            ['key' => 'withdrawal_require_kyc',    'value' => '1',     'description' => 'Require KYC before allowing withdrawals'],
            // SMS (OTP)
            ['key' => 'sms_enabled',              'value' => '0',     'description' => 'Enable SMS sending (OTP)'],
            ['key' => 'sms_short_code',           'value' => '388',   'description' => 'SMS short code (CloudServiceZM)'],
        ];

        foreach ($settings as $setting) {
            if (! $hasDescription) {
                unset($setting['description']);
            }
            DB::table('system_settings')->updateOrInsert(
                ['key' => $setting['key']],
                array_merge($setting, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }

        // Insert secrets once (never overwrite) so admins can set values in UI.
        $insertIfMissing = [
            'sms_username' => '',
            'sms_password' => '',
            'sms_sender_id' => 'Extracash',
        ];

        foreach ($insertIfMissing as $key => $defaultValue) {
            if (DB::table('system_settings')->where('key', $key)->exists()) {
                continue;
            }
            $row = [
                'key' => $key,
                'value' => $defaultValue,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if ($hasDescription) {
                $row['description'] = match ($key) {
                    'sms_username' => 'SMS API username (CloudServiceZM)',
                    'sms_password' => 'SMS API password (CloudServiceZM)',
                    'sms_sender_id' => 'SMS sender id (CloudServiceZM)',
                    default => $key,
                };
            }
            DB::table('system_settings')->insert($row);
        }

        $this->call(LencoSystemSettingsSeeder::class);
    }
}

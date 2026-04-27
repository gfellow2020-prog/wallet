<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds Lenco admin (system_settings) keys.
 *
 * - Refreshes non-secret defaults: base URL, country, environment.
 * - API key, secret, webhook secret, and account id are only inserted when missing;
 *   they are never overwritten so you can add them yourself and re-run safely.
 */
class LencoSystemSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $nonSensitive = [
            'lenco_base_url' => 'https://api.lenco.co/access/v2',
            'lenco_country' => 'zm',
            'lenco_environment' => 'sandbox',
        ];

        $hasDescription = Schema::hasColumn('system_settings', 'description');

        foreach ($nonSensitive as $key => $value) {
            $row = [
                'key' => $key,
                'value' => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if ($hasDescription) {
                $row['description'] = match ($key) {
                    'lenco_base_url' => 'Lenco API base URL (v2: .../access/v2)',
                    'lenco_country' => 'ISO country for Lenco (e.g. zm, mw)',
                    'lenco_environment' => 'Lenco environment: sandbox or live',
                    default => $key,
                };
            }
            DB::table('system_settings')->updateOrInsert(
                ['key' => $key],
                $row
            );
        }

        $insertIfMissing = [
            'lenco_api_key' => '',
            'lenco_secret_key' => '',
            'lenco_webhook_secret' => '',
            'lenco_account_id' => '',
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
                    'lenco_api_key' => 'Lenco API Key (add in admin)',
                    'lenco_secret_key' => 'Lenco Secret Key (add in admin)',
                    'lenco_webhook_secret' => 'Lenco Webhook Signing Secret (add in admin)',
                    'lenco_account_id' => 'Lenco account UUID to debit for payouts (add in admin)',
                    default => $key,
                };
            }
            DB::table('system_settings')->insert($row);
        }
    }
}

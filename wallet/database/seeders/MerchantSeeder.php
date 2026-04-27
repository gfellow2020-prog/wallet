<?php

namespace Database\Seeders;

use App\Models\Merchant;
use Illuminate\Database\Seeder;

class MerchantSeeder extends Seeder
{
    public function run(): void
    {
        $merchants = [
            // Groceries
            ['name' => 'Shoprite Zambia',     'code' => 'SHOPRITE',    'category' => 'groceries',    'cashback_eligible' => true],
            ['name' => 'Pick n Pay',           'code' => 'PNP',         'category' => 'groceries',    'cashback_eligible' => true],
            ['name' => 'Spar Zambia',          'code' => 'SPAR',        'category' => 'groceries',    'cashback_eligible' => true],
            ['name' => 'Game Stores',          'code' => 'GAME',        'category' => 'groceries',    'cashback_eligible' => true],

            // Food & Beverage
            ['name' => 'KFC Zambia',           'code' => 'KFC_ZM',      'category' => 'food',         'cashback_eligible' => true],
            ['name' => 'Hungry Lion',          'code' => 'HLION',       'category' => 'food',         'cashback_eligible' => true],
            ['name' => 'Steers',               'code' => 'STEERS',      'category' => 'food',         'cashback_eligible' => true],
            ['name' => 'Debonairs Pizza',      'code' => 'DEBONAIRS',   'category' => 'food',         'cashback_eligible' => true],

            // Fuel
            ['name' => 'Total Energies',       'code' => 'TOTAL',       'category' => 'fuel',         'cashback_eligible' => true],
            ['name' => 'Shell Zambia',         'code' => 'SHELL',       'category' => 'fuel',         'cashback_eligible' => true],
            ['name' => 'Puma Energy',          'code' => 'PUMA',        'category' => 'fuel',         'cashback_eligible' => true],

            // Retail
            ['name' => 'Jet Stores',           'code' => 'JET',         'category' => 'retail',       'cashback_eligible' => true],
            ['name' => 'Mr Price',             'code' => 'MRP',         'category' => 'retail',       'cashback_eligible' => true],
            ['name' => 'Truworths',            'code' => 'TRUWORTHS',   'category' => 'retail',       'cashback_eligible' => true],
            ['name' => 'Ackermans',            'code' => 'ACKERMANS',   'category' => 'retail',       'cashback_eligible' => true],

            // Pharmacy
            ['name' => 'Clicks Pharmacy',      'code' => 'CLICKS',      'category' => 'pharmacy',     'cashback_eligible' => true],
            ['name' => 'Dis-Chem',             'code' => 'DISCHEM',     'category' => 'pharmacy',     'cashback_eligible' => true],

            // Telecoms (no cashback — airtime/data excluded)
            ['name' => 'Airtel Zambia',        'code' => 'AIRTEL',      'category' => 'telecoms',     'cashback_eligible' => false],
            ['name' => 'MTN Zambia',           'code' => 'MTN_ZM',      'category' => 'telecoms',     'cashback_eligible' => false],
            ['name' => 'Zamtel',               'code' => 'ZAMTEL',      'category' => 'telecoms',     'cashback_eligible' => false],

            // Utilities (no cashback)
            ['name' => 'ZESCO',                'code' => 'ZESCO',       'category' => 'utilities',    'cashback_eligible' => false],
            ['name' => 'Lusaka Water',         'code' => 'LWSC',        'category' => 'utilities',    'cashback_eligible' => false],
        ];

        foreach ($merchants as $data) {
            Merchant::updateOrCreate(
                ['code' => $data['code']],
                array_merge($data, ['is_active' => true])
            );
        }

        $this->command->info('Seeded '.count($merchants).' merchants.');
    }
}

<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;

/**
 * Extra demo users and marketplace listings for local/staging.
 * Idempotent: safe to run multiple times (updateOrCreate per user / listing).
 *
 * Login: email below, password always `password`.
 */
class DemoCommunitySeeder extends Seeder
{
    public const SELLER_EMAIL_PREFIX = 'demo.seller';

    public const BUYER_EMAIL_PREFIX = 'demo.buyer';

    public function run(): void
    {
        $sellerCount = 18;
        $buyerCount = 12;
        $listingsPerSeller = 5;

        $locations = [
            ['lat' => -15.4166, 'lng' => 28.2833, 'label' => 'Lusaka CBD'],
            ['lat' => -15.3875, 'lng' => 28.3228, 'label' => 'Woodlands, Lusaka'],
            ['lat' => -15.4275, 'lng' => 28.3172, 'label' => 'Kabulonga, Lusaka'],
            ['lat' => -15.4050, 'lng' => 28.2680, 'label' => 'Matero, Lusaka'],
            ['lat' => -15.4400, 'lng' => 28.3500, 'label' => 'Chelston, Lusaka'],
            ['lat' => -15.3700, 'lng' => 28.3100, 'label' => 'Rhodes Park, Lusaka'],
            ['lat' => -15.4550, 'lng' => 28.2950, 'label' => 'Chilenje, Lusaka'],
            ['lat' => -15.4200, 'lng' => 28.2450, 'label' => 'Bauleni, Lusaka'],
        ];

        $templates = [
            ['t' => 'Bluetooth speaker – portable', 'c' => 'Electronics', 'cond' => 'new'],
            ['t' => 'USB-C fast charger 65W', 'c' => 'Electronics', 'cond' => 'new'],
            ['t' => 'LED desk lamp – dimmable', 'c' => 'Home', 'cond' => 'new'],
            ['t' => 'Non-stick cookware set', 'c' => 'Home', 'cond' => 'new'],
            ['t' => 'Rice cooker 1.8L', 'c' => 'Appliances', 'cond' => 'used'],
            ['t' => 'Standing fan 16"', 'c' => 'Appliances', 'cond' => 'refurbished'],
            ['t' => 'Chitenge fabric – 6 yards', 'c' => 'Clothing', 'cond' => 'new'],
            ['t' => 'Running shoes – size 43', 'c' => 'Clothing', 'cond' => 'used'],
            ['t' => 'Groundnuts – roasted 2kg', 'c' => 'Food', 'cond' => 'new'],
            ['t' => 'Cooking oil – 5L', 'c' => 'Food', 'cond' => 'new'],
            ['t' => 'Plastic chairs set of 4', 'c' => 'Furniture', 'cond' => 'used'],
            ['t' => 'Office chair – mesh back', 'c' => 'Furniture', 'cond' => 'new'],
            ['t' => 'Football boots – size 41', 'c' => 'Sports', 'cond' => 'used'],
            ['t' => 'Yoga mat + blocks', 'c' => 'Sports', 'cond' => 'new'],
            ['t' => 'Grade 10 textbook bundle', 'c' => 'Books', 'cond' => 'used'],
            ['t' => 'Novels – fiction lot (5)', 'c' => 'Books', 'cond' => 'used'],
            ['t' => 'Power bank 20,000mAh', 'c' => 'Electronics', 'cond' => 'new'],
            ['t' => 'Wi‑Fi router dual-band', 'c' => 'Electronics', 'cond' => 'refurbished'],
            ['t' => 'Iron steam 2400W', 'c' => 'Appliances', 'cond' => 'new'],
            ['t' => 'Vacuum cleaner bagless', 'c' => 'Appliances', 'cond' => 'used'],
            ['t' => 'Wall clock silent', 'c' => 'Home', 'cond' => 'new'],
            ['t' => 'Bedding set – queen', 'c' => 'Home', 'cond' => 'new'],
            ['t' => 'Garden hose 30m', 'c' => 'Home', 'cond' => 'new'],
            ['t' => 'Toolbox 120-piece', 'c' => 'Home', 'cond' => 'new'],
            ['t' => 'Kids bicycle 20"', 'c' => 'Sports', 'cond' => 'used'],
        ];

        $cashbackRate = 0.02;
        $sellers = [];

        for ($i = 1; $i <= $sellerCount; $i++) {
            $email = self::SELLER_EMAIL_PREFIX.$i.'@extracash.zm';
            $seller = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => 'Demo Seller '.$i,
                    'password' => 'password',
                ]
            );
            $sellers[] = $seller;

            Wallet::query()->updateOrCreate(
                ['user_id' => $seller->id],
                [
                    'available_balance' => fake()->randomFloat(2, 3_500, 95_000),
                    'pending_balance' => 0,
                    'currency' => 'ZMW',
                ]
            );
        }

        for ($i = 1; $i <= $buyerCount; $i++) {
            $email = self::BUYER_EMAIL_PREFIX.$i.'@extracash.zm';
            $buyer = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => 'Demo Buyer '.$i,
                    'password' => 'password',
                ]
            );

            Wallet::query()->updateOrCreate(
                ['user_id' => $buyer->id],
                [
                    'available_balance' => fake()->randomFloat(2, 800, 25_000),
                    'pending_balance' => 0,
                    'currency' => 'ZMW',
                ]
            );
        }

        $templateIndex = 0;
        foreach ($sellers as $sellerIndex => $seller) {
            for ($j = 0; $j < $listingsPerSeller; $j++) {
                $tpl = $templates[$templateIndex % count($templates)];
                $templateIndex++;

                $title = $tpl['t'].' · '.$seller->name;
                $loc = $locations[($sellerIndex + $j) % count($locations)];
                $latJitter = (random_int(-60, 60) / 10000);
                $lngJitter = (random_int(-60, 60) / 10000);

                $price = match ($tpl['c']) {
                    'Electronics' => fake()->randomFloat(2, 180, 9_500),
                    'Furniture' => fake()->randomFloat(2, 400, 6_000),
                    'Appliances' => fake()->randomFloat(2, 150, 4_000),
                    'Food' => fake()->randomFloat(2, 35, 450),
                    'Clothing' => fake()->randomFloat(2, 60, 900),
                    'Sports' => fake()->randomFloat(2, 120, 2_800),
                    'Books' => fake()->randomFloat(2, 40, 600),
                    default => fake()->randomFloat(2, 50, 2_000),
                };

                $seed = 'ec-'.$seller->id.'-'.$j.'-'.substr(sha1($title), 0, 10);
                $imageUrl = 'https://picsum.photos/seed/'.rawurlencode($seed).'/800/600';

                Product::query()->updateOrCreate(
                    [
                        'user_id' => $seller->id,
                        'title' => $title,
                    ],
                    [
                        'description' => 'Demo listing for testing the marketplace. '.$tpl['t'].'.',
                        'category' => $tpl['c'],
                        'price' => $price,
                        'cashback_rate' => $cashbackRate,
                        'cashback_amount' => round($price * $cashbackRate, 2),
                        'condition' => $tpl['cond'],
                        'stock' => random_int(1, 12),
                        'is_active' => true,
                        'latitude' => $loc['lat'] + $latJitter,
                        'longitude' => $loc['lng'] + $lngJitter,
                        'location_label' => $loc['label'],
                        'image_url' => $imageUrl,
                    ]
                );
            }
        }

        $totalProducts = $sellerCount * $listingsPerSeller;
        $this->command->info("Demo community: {$sellerCount} sellers, {$buyerCount} buyers, {$totalProducts} listings (password: password).");
    }
}

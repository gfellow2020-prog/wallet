<?php

namespace Database\Seeders;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Do not use WithoutModelEvents: User must run model events so `extracash_number` is
        // minted in the creating hook. Password is plain text; the model's `hashed` cast hashes it.
        $user = User::query()->updateOrCreate(
            ['email' => 'user@extracash.app'],
            [
                'name' => 'Major Mulenga',
                'password' => 'password',
            ]
        );

        $this->call(AdminUserSeeder::class);
        $this->call(RolesAndPermissionsSeeder::class);

        $wallet = Wallet::query()->updateOrCreate([
            'user_id' => $user->id,
        ], [
            'available_balance' => 152300.50,
            'currency' => 'ZMW',
        ]);

        Transaction::query()->where('wallet_id', $wallet->id)->delete();

        $wallet->transactions()->createMany([
            [
                'type' => 'credit',
                'amount' => 70000,
                'narration' => 'Salary top-up',
                'transacted_at' => Carbon::now()->subDays(1),
            ],
            [
                'type' => 'debit',
                'amount' => 8500,
                'narration' => 'Groceries',
                'transacted_at' => Carbon::now()->subDays(2),
            ],
            [
                'type' => 'debit',
                'amount' => 2500,
                'narration' => 'Airtime',
                'transacted_at' => Carbon::now()->subDays(3),
            ],
            [
                'type' => 'credit',
                'amount' => 12000,
                'narration' => 'Refund',
                'transacted_at' => Carbon::now()->subDays(4),
            ],
        ]);

        $this->call([
            ProductSeeder::class,
            DemoCommunitySeeder::class,
            LusakaSeeder::class,
            RewardsMissionDefinitionSeeder::class,
            StreakRewardDefinitionSeeder::class,
        ]);
    }
}

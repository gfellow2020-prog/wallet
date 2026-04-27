<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed a user that matches `ADMIN_EMAILS` in `.env` (see config/admin.php).
     * Default password (local only): `password`
     */
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@extracash.app'],
            [
                'name' => 'Platform Admin',
                'password' => 'password',
            ]
        );

        Wallet::query()->updateOrCreate(
            ['user_id' => $admin->id],
            [
                'available_balance' => 0,
                'pending_balance' => 0,
                'currency' => 'ZMW',
            ]
        );
    }
}

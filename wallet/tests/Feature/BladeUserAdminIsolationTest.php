<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BladeUserAdminIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_redirects_to_admin_and_cannot_access_wallet_pages(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create([
            'email' => 'admin@isolation.test',
            'password' => bcrypt('password'),
            'phone_verified_at' => now(),
        ]);
        $admin->assignRole('super_admin');

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect('/admin');

        $this->actingAs($admin)
            ->get('/wallet')
            ->assertRedirect('/admin');
    }

    public function test_user_login_redirects_to_wallet_and_cannot_access_admin(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create([
            'email' => 'user@isolation.test',
            'password' => bcrypt('password'),
            'phone_verified_at' => now(),
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/wallet');

        $this->actingAs($user)
            ->get('/admin')
            ->assertForbidden();
    }
}


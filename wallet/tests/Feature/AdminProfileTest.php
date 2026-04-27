<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_profile_page_and_update_name_and_password(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create([
            'email' => 'admin@profile.test',
            'password' => bcrypt('oldpassword123'),
            'phone_verified_at' => now(),
        ]);
        $admin->assignRole('super_admin');

        $this->actingAs($admin)
            ->get('/admin/profile')
            ->assertOk()
            ->assertSee('My Profile');

        $this->actingAs($admin)
            ->post('/admin/profile', [
                'name' => 'New Admin Name',
            ])
            ->assertRedirect('/admin/profile');

        $this->assertSame('New Admin Name', $admin->fresh()->name);

        $this->actingAs($admin)
            ->post('/admin/profile/password', [
                'current_password' => 'oldpassword123',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ])
            ->assertRedirect('/admin/profile');

        $this->assertTrue(Hash::check('newpassword123', (string) $admin->fresh()->password));
    }
}


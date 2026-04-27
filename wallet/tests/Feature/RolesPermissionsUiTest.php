<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesPermissionsUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_index_requires_roles_view_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create(['email' => 'admin@roles.test', 'password' => 'password', 'phone_verified_at' => now()]);
        $admin->assignRole('ops'); // does not include roles.view

        $this->actingAs($admin)->get('/admin/roles')->assertForbidden();

        $admin->givePermissionTo('roles.view');
        $this->actingAs($admin)->get('/admin/roles')->assertOk()->assertSee('Roles');
    }

    public function test_roles_manage_can_create_update_and_delete_role(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create(['email' => 'admin@roles2.test', 'password' => 'password', 'phone_verified_at' => now()]);
        $admin->assignRole('super_admin');

        $this->actingAs($admin)
            ->post('/admin/roles', ['name' => 'auditor'])
            ->assertRedirect();

        $this->assertDatabaseHas('roles', ['name' => 'auditor']);

        $role = Role::query()->where('name', 'auditor')->firstOrFail();

        $this->actingAs($admin)
            ->put("/admin/roles/{$role->id}", ['name' => 'auditor_readonly'])
            ->assertRedirect();

        $this->assertDatabaseHas('roles', ['name' => 'auditor_readonly']);

        $this->actingAs($admin)
            ->delete("/admin/roles/{$role->id}")
            ->assertRedirect('/admin/roles');

        $this->assertDatabaseMissing('roles', ['name' => 'auditor_readonly']);
    }

    public function test_permissions_manage_can_create_update_and_delete_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create(['email' => 'admin@perms.test', 'password' => 'password', 'phone_verified_at' => now()]);
        $admin->assignRole('super_admin');

        $this->actingAs($admin)
            ->post('/admin/permissions', ['name' => 'reports.view'])
            ->assertRedirect('/admin/permissions');

        $perm = Permission::query()->where('name', 'reports.view')->firstOrFail();

        $this->actingAs($admin)
            ->put("/admin/permissions/{$perm->id}", ['name' => 'reports.export'])
            ->assertRedirect('/admin/permissions');

        $this->assertDatabaseHas('permissions', ['name' => 'reports.export']);

        $this->actingAs($admin)
            ->delete("/admin/permissions/{$perm->id}")
            ->assertRedirect('/admin/permissions');

        $this->assertDatabaseMissing('permissions', ['name' => 'reports.export']);
    }

    public function test_user_role_assignment_requires_users_assign_roles_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $actor = User::factory()->create(['email' => 'actor@roles.test', 'password' => 'password', 'phone_verified_at' => now()]);
        $actor->assignRole('ops');

        $target = User::factory()->create(['email' => 'target@roles.test', 'password' => 'password', 'phone_verified_at' => now()]);

        $this->actingAs($actor)
            ->post("/admin/users/{$target->id}/roles", ['roles' => ['support']])
            ->assertForbidden();

        $actor->givePermissionTo('users.assign_roles');

        $this->actingAs($actor)
            ->post("/admin/users/{$target->id}/roles", ['roles' => ['support']])
            ->assertRedirect("/admin/users/{$target->id}");

        $this->assertTrue($target->fresh()->hasRole('support'));
    }
}


<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            // KYC
            'kyc.view',
            'kyc.review',

            // Fraud / risk
            'fraud.view',
            'fraud.resolve',
            'adjustments.create',

            // Settings
            'settings.view',
            'settings.update',
            'settings.update_secrets',

            // Merchants / marketplace
            'merchants.create',
            'merchants.update',
            'orders.view',

            // Payments / withdrawals
            'payments.view',
            'withdrawals.view',
            'withdrawals.action',

            // Roles & permissions management (admin UI)
            'roles.view',
            'roles.manage',
            'permissions.view',
            'permissions.manage',
            'users.assign_roles',
        ];

        foreach ($permissions as $name) {
            Permission::findOrCreate($name);
        }

        $roles = [
            'super_admin' => $permissions,
            'compliance' => ['kyc.view', 'kyc.review'],
            'risk' => ['fraud.view', 'fraud.resolve'],
            'support' => ['kyc.view', 'fraud.view', 'payments.view', 'withdrawals.view', 'orders.view'],
            'finance' => ['payments.view', 'withdrawals.view', 'withdrawals.action'],
            'ops' => ['settings.view', 'settings.update'],
            'merchant_admin' => ['merchants.create', 'merchants.update', 'orders.view'],
        ];

        foreach ($roles as $roleName => $rolePerms) {
            /** @var Role $role */
            $role = Role::findOrCreate($roleName);
            $role->syncPermissions($rolePerms);
        }

        // Migrate existing allowlisted admins to super_admin.
        $emails = config('admin.emails', []);
        if (is_array($emails) && $emails !== []) {
            $users = User::query()->whereIn('email', $emails)->get();
            foreach ($users as $user) {
                /** @var User $user */
                $user->assignRole('super_admin');
            }
        }
    }
}


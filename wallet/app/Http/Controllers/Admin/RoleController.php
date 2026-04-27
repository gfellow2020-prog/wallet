<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $roles = Role::query()
            ->withCount('users')
            ->when($q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.roles.index', compact('roles', 'q'));
    }

    public function create(): View
    {
        return view('admin.roles.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_\\-\\.]+$/i', 'unique:roles,name'],
        ]);

        $role = Role::query()->create([
            'name' => $data['name'],
            'guard_name' => config('auth.defaults.guard', 'web'),
        ]);

        return redirect()
            ->route('admin.roles.show', $role)
            ->with('success', 'Role created.');
    }

    public function show(Role $role, Request $request): View
    {
        $role->load('permissions');

        $permissions = Permission::query()
            ->orderBy('name')
            ->get();

        $groupedPermissions = $permissions->groupBy(function (Permission $p) {
            $parts = explode('.', $p->name);
            return $parts[0] ?? 'other';
        });

        $selected = $role->permissions->pluck('name')->all();

        return view('admin.roles.show', compact('role', 'groupedPermissions', 'selected'));
    }

    public function update(Role $role, Request $request): RedirectResponse
    {
        if ($role->name === 'super_admin') {
            return redirect()
                ->route('admin.roles.show', $role)
                ->with('error', 'The super_admin role cannot be modified.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_\\-\\.]+$/i', 'unique:roles,name,'.$role->id],
        ]);

        $role->update([
            'name' => $data['name'],
        ]);

        return redirect()
            ->route('admin.roles.show', $role)
            ->with('success', 'Role updated.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->name === 'super_admin') {
            return redirect()
                ->route('admin.roles.show', $role)
                ->with('error', 'The super_admin role cannot be deleted.');
        }

        if ($role->users()->exists()) {
            return redirect()
                ->route('admin.roles.show', $role)
                ->with('error', 'This role is assigned to users and cannot be deleted.');
        }

        $role->delete();

        return redirect()
            ->route('admin.roles.index')
            ->with('success', 'Role deleted.');
    }

    public function syncPermissions(Role $role, Request $request): RedirectResponse
    {
        if ($role->name === 'super_admin') {
            return redirect()
                ->route('admin.roles.show', $role)
                ->with('error', 'The super_admin role permissions cannot be modified.');
        }

        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $names = $data['permissions'] ?? [];
        $role->syncPermissions($names);

        return redirect()
            ->route('admin.roles.show', $role)
            ->with('success', 'Role permissions updated.');
    }
}


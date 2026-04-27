<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $permissions = Permission::query()
            ->withCount('roles')
            ->when($q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        return view('admin.permissions.index', compact('permissions', 'q'));
    }

    public function create(): View
    {
        return view('admin.permissions.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_\\-\\.]+$/i', 'unique:permissions,name'],
        ]);

        Permission::query()->create([
            'name' => $data['name'],
            'guard_name' => config('auth.defaults.guard', 'web'),
        ]);

        return redirect()
            ->route('admin.permissions.index')
            ->with('success', 'Permission created.');
    }

    public function update(Permission $permission, Request $request): RedirectResponse
    {
        if ($permission->name === 'settings.update_secrets') {
            return redirect()
                ->route('admin.permissions.index')
                ->with('error', 'This permission cannot be modified.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_\\-\\.]+$/i', 'unique:permissions,name,'.$permission->id],
        ]);

        $permission->update([
            'name' => $data['name'],
        ]);

        return redirect()
            ->route('admin.permissions.index')
            ->with('success', 'Permission updated.');
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        if ($permission->roles()->exists()) {
            return redirect()
                ->route('admin.permissions.index')
                ->with('error', 'This permission is attached to roles and cannot be deleted.');
        }

        $permission->delete();

        return redirect()
            ->route('admin.permissions.index')
            ->with('success', 'Permission deleted.');
    }
}


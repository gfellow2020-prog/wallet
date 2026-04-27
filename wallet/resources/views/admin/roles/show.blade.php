@extends('admin.layouts.app')
@section('title', 'Role — ExtraCash Admin')
@section('page-title', 'Role')
@section('breadcrumb', $role->name)

@section('content')

<div class="mb-5 flex items-center justify-between gap-3">
    <a href="{{ route('admin.roles.index') }}" class="btn-secondary text-xs">← Back to roles</a>
    <div class="text-xs text-neutral-500">Role ID: {{ $role->id }}</div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="space-y-6">
        <div class="card p-6">
            <h3 class="font-semibold text-sm text-neutral-900 dark:text-white mb-4">Role details</h3>

            <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="space-y-3">
                @csrf
                @method('PUT')

                <div>
                    <label class="block text-xs font-semibold text-neutral-600 mb-1">Name</label>
                    <input type="text" name="name" value="{{ old('name', $role->name) }}"
                           class="w-full px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white"
                           @disabled($role->name === 'super_admin')
                           required>
                    @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <button type="submit" class="btn-primary w-full justify-center" @disabled($role->name === 'super_admin')>
                    Save role
                </button>
            </form>

            @can('roles.manage')
                <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" class="mt-4"
                      onsubmit="return confirm('Delete this role? This cannot be undone.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-danger w-full justify-center" @disabled($role->name === 'super_admin')>
                        Delete role
                    </button>
                </form>
            @endcan
        </div>
    </div>

    <div class="xl:col-span-2">
        <div class="card overflow-hidden">
            <div class="px-5 py-3 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between gap-3">
                <h3 class="font-semibold text-sm text-neutral-900 dark:text-white">Permissions</h3>
                <div class="text-xs text-neutral-500">{{ count($selected) }} selected</div>
            </div>

            <form method="POST" action="{{ route('admin.roles.permissions.sync', $role) }}">
                @csrf

                <div class="p-5 space-y-5">
                    @foreach($groupedPermissions as $group => $perms)
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500 mb-2">{{ $group }}</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                @foreach($perms as $p)
                                    <label class="flex items-center gap-2 text-sm text-neutral-700 dark:text-neutral-200">
                                        <input type="checkbox" name="permissions[]" value="{{ $p->name }}"
                                               class="rounded"
                                               @checked(in_array($p->name, $selected, true))
                                               @disabled($role->name === 'super_admin')>
                                        <span class="font-mono text-xs">{{ $p->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    @error('permissions')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                    @error('permissions.*')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="px-5 py-4 border-t border-neutral-200 dark:border-neutral-800">
                    <button type="submit" class="btn-primary w-full justify-center" @disabled($role->name === 'super_admin')>
                        Save permissions
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection


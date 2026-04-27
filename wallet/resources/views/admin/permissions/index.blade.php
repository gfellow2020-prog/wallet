@extends('admin.layouts.app')
@section('title', 'Permissions — ExtraCash Admin')
@section('page-title', 'Permissions')
@section('breadcrumb', 'Access control permissions')

@section('content')

<div class="card overflow-hidden">
    <div class="px-5 py-4 border-b border-neutral-200 dark:border-neutral-800 flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between">
        <form method="GET" class="flex gap-2 flex-1">
            <input type="text" name="q" value="{{ $q }}" placeholder="Search permission key…"
                   class="flex-1 px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white max-w-md">
            <button type="submit" class="btn-secondary text-xs py-2">Search</button>
        </form>
        <div class="flex items-center gap-2">
            <div class="text-xs text-neutral-500">{{ $permissions->total() }} permissions total</div>
            @can('permissions.manage')
                <a href="{{ route('admin.permissions.create') }}" class="btn-primary text-xs py-2">New permission</a>
            @endcan
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
            <tr class="border-b border-neutral-100 dark:border-neutral-800">
                <th class="px-5 py-3 text-left">Permission</th>
                <th class="px-5 py-3 text-right">Roles</th>
                @can('permissions.manage')
                    <th class="px-5 py-3 text-right">Actions</th>
                @endcan
            </tr>
            </thead>
            <tbody>
            @forelse($permissions as $perm)
                <tr class="border-b border-neutral-50 dark:border-neutral-800/50">
                    <td class="px-5 py-3">
                        <span class="font-mono text-xs text-neutral-900 dark:text-white">{{ $perm->name }}</span>
                    </td>
                    <td class="px-5 py-3 text-right text-neutral-600 dark:text-neutral-300">
                        {{ number_format($perm->roles_count) }}
                    </td>
                    @can('permissions.manage')
                        <td class="px-5 py-3 text-right">
                            <form method="POST" action="{{ route('admin.permissions.update', $perm) }}" class="inline-flex items-center gap-2">
                                @csrf
                                @method('PUT')
                                <input type="text" name="name" value="{{ old('name', $perm->name) }}"
                                       class="px-2 py-1 text-xs border rounded bg-white dark:bg-neutral-900 dark:text-white w-64"
                                       @disabled($perm->name === 'settings.update_secrets')>
                                <button type="submit" class="btn-secondary text-xs py-1.5" @disabled($perm->name === 'settings.update_secrets')>Rename</button>
                            </form>

                            <form method="POST" action="{{ route('admin.permissions.destroy', $perm) }}" class="inline"
                                  onsubmit="return confirm('Delete this permission?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-danger text-xs py-1.5">Delete</button>
                            </form>
                        </td>
                    @endcan
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="px-5 py-6 text-center text-neutral-400">No permissions</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="px-5 py-4">
        {{ $permissions->links() }}
    </div>
</div>

@endsection


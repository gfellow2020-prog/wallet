@extends('admin.layouts.app')
@section('title', 'Roles — ExtraCash Admin')
@section('page-title', 'Roles')
@section('breadcrumb', 'Access control roles')

@section('content')

<div class="card overflow-hidden">
    <div class="px-5 py-4 border-b border-neutral-200 dark:border-neutral-800 flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between">
        <form method="GET" class="flex gap-2 flex-1">
            <input type="text" name="q" value="{{ $q }}" placeholder="Search role name…"
                   class="flex-1 px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white max-w-xs">
            <button type="submit" class="btn-secondary text-xs py-2">Search</button>
        </form>
        <div class="flex items-center gap-2">
            <div class="text-xs text-neutral-500">{{ $roles->total() }} roles total</div>
            @can('roles.manage')
                <a href="{{ route('admin.roles.create') }}" class="btn-primary text-xs py-2">New role</a>
            @endcan
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
            <tr class="border-b border-neutral-100 dark:border-neutral-800">
                <th class="px-5 py-3 text-left">Role</th>
                <th class="px-5 py-3 text-right">Users</th>
                <th class="px-5 py-3 text-right">Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($roles as $role)
                <tr class="border-b border-neutral-50 dark:border-neutral-800/50">
                    <td class="px-5 py-3 font-medium text-neutral-900 dark:text-white">
                        {{ $role->name }}
                    </td>
                    <td class="px-5 py-3 text-right text-neutral-600 dark:text-neutral-300">
                        {{ number_format($role->users_count) }}
                    </td>
                    <td class="px-5 py-3 text-right">
                        <a href="{{ route('admin.roles.show', $role) }}" class="btn-secondary text-xs py-2">Manage</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="px-5 py-6 text-center text-neutral-400">No roles</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="px-5 py-4">
        {{ $roles->links() }}
    </div>
</div>

@endsection


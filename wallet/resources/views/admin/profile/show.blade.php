@extends('admin.layouts.app')
@section('title', 'My Profile — ExtraCash Admin')
@section('page-title', 'My Profile')
@section('breadcrumb', 'Account settings')

@section('content')

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="space-y-6">
        <div class="card p-6">
            <div class="flex items-center gap-4 mb-4">
                <div class="w-12 h-12 rounded bg-neutral-200 dark:bg-neutral-700 flex items-center justify-center text-lg font-bold text-neutral-700 dark:text-neutral-200">
                    {{ strtoupper(substr($user->name, 0, 2)) }}
                </div>
                <div class="min-w-0">
                    <p class="font-bold text-neutral-900 dark:text-white truncate">{{ $user->name }}</p>
                    <p class="text-sm text-neutral-500 truncate">{{ $user->email }}</p>
                </div>
            </div>

            <dl class="space-y-3 text-sm">
                <div class="flex justify-between gap-3">
                    <dt class="text-neutral-500">User ID</dt>
                    <dd class="font-medium text-neutral-900 dark:text-white">{{ $user->id }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-neutral-500">Joined</dt>
                    <dd class="font-medium text-neutral-900 dark:text-white">{{ $user->created_at->format('M d, Y H:i') }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-neutral-500">Last login</dt>
                    <dd class="font-medium text-neutral-900 dark:text-white">
                        {{ $user->last_login_at ? $user->last_login_at->format('M d, Y H:i') : '—' }}
                    </dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-neutral-500">Last login IP</dt>
                    <dd class="font-medium text-neutral-900 dark:text-white">{{ $user->last_login_ip ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        <div class="card p-6">
            <h3 class="font-semibold text-sm text-neutral-900 dark:text-white mb-4">Update profile</h3>
            <form method="POST" action="{{ route('admin.profile.update') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-neutral-600 mb-1">Name</label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}"
                           class="w-full px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white"
                           required>
                    @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="btn-primary w-full justify-center">Save</button>
            </form>
        </div>

        <div class="card p-6">
            <h3 class="font-semibold text-sm text-neutral-900 dark:text-white mb-4">Change password</h3>
            <form method="POST" action="{{ route('admin.profile.password') }}" class="space-y-3">
                @csrf

                <div>
                    <label class="block text-xs font-semibold text-neutral-600 mb-1">Current password</label>
                    <input type="password" name="current_password"
                           class="w-full px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white"
                           required>
                    @error('current_password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-neutral-600 mb-1">New password</label>
                    <input type="password" name="password"
                           class="w-full px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white"
                           required>
                    @error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-neutral-600 mb-1">Confirm new password</label>
                    <input type="password" name="password_confirmation"
                           class="w-full px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white"
                           required>
                </div>

                <button type="submit" class="btn-primary w-full justify-center">Update password</button>
            </form>
        </div>
    </div>

    <div class="xl:col-span-2 space-y-6">
        <div class="card p-6">
            <h3 class="font-semibold text-sm text-neutral-900 dark:text-white mb-3">Roles</h3>
            @if($user->roles->isEmpty())
                <p class="text-sm text-neutral-500">No roles assigned.</p>
            @else
                <div class="flex flex-wrap gap-2">
                    @foreach($user->roles as $r)
                        <span class="badge badge-approved">{{ $r->name }}</span>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="card p-6">
            <h3 class="font-semibold text-sm text-neutral-900 dark:text-white mb-3">Direct permissions</h3>
            @php $direct = $user->permissions; @endphp
            @if($direct->isEmpty())
                <p class="text-sm text-neutral-500">No direct permissions.</p>
            @else
                <div class="flex flex-wrap gap-2">
                    @foreach($direct as $p)
                        <span class="badge badge-pending"><span class="font-mono text-[11px]">{{ $p->name }}</span></span>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="card p-6">
            <h3 class="font-semibold text-sm text-neutral-900 dark:text-white mb-3">Effective permissions (via roles + direct)</h3>
            @php $effective = $user->getAllPermissions()->pluck('name')->sort()->values(); @endphp
            @if($effective->isEmpty())
                <p class="text-sm text-neutral-500">No permissions.</p>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    @foreach($effective as $name)
                        <div class="text-sm text-neutral-700 dark:text-neutral-200">
                            <span class="font-mono text-xs">{{ $name }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>

@endsection


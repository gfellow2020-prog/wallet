@extends('admin.layouts.app')
@section('title', 'New Role — ExtraCash Admin')
@section('page-title', 'New Role')
@section('breadcrumb', 'Create access role')

@section('content')

<div class="max-w-2xl">
    <div class="mb-4">
        <a href="{{ route('admin.roles.index') }}" class="btn-secondary text-xs">← Back to roles</a>
    </div>

    <div class="card p-6">
        <form method="POST" action="{{ route('admin.roles.store') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-xs font-semibold text-neutral-600 mb-1">Role name</label>
                <input type="text" name="name" value="{{ old('name') }}"
                       placeholder="e.g. auditor"
                       class="w-full px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white"
                       required>
                @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                <p class="mt-2 text-xs text-neutral-500">Use a stable key like <code>audit_readonly</code> (letters, numbers, dot, dash, underscore).</p>
            </div>

            <button type="submit" class="btn-primary">Create role</button>
        </form>
    </div>
</div>

@endsection


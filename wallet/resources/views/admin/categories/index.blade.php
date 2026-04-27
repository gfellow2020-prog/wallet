@extends('admin.layouts.app')
@section('title', 'Categories — ExtraCash Admin')
@section('page-title', 'Categories')
@section('breadcrumb', 'Manage marketplace categories')

@section('content')

<div class="card overflow-hidden">
    <div class="px-5 py-4 border-b border-neutral-200 dark:border-neutral-800 flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between">
        <form method="GET" class="flex gap-2 flex-wrap items-center">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Search name or slug…"
                   class="px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white w-56">
            <select name="active" class="px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white">
                <option value="">All</option>
                <option value="1" {{ request('active') === '1' ? 'selected' : '' }}>Active</option>
                <option value="0" {{ request('active') === '0' ? 'selected' : '' }}>Inactive</option>
            </select>
            <button type="submit" class="btn-secondary text-xs py-2">Filter</button>
            <a href="{{ route('admin.categories.create') }}" class="btn-primary text-xs py-2">+ New</a>
        </form>
        <div class="text-xs text-neutral-500">{{ $categories->total() }} categories</div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-neutral-100 dark:border-neutral-800">
                    <th class="px-5 py-3 text-left">Name</th>
                    <th class="px-5 py-3 text-left">Slug</th>
                    <th class="px-5 py-3 text-right">Sort</th>
                    <th class="px-5 py-3 text-center">Status</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($categories as $c)
                <tr class="border-b border-neutral-50 dark:border-neutral-800/50 hover:bg-neutral-50 dark:hover:bg-neutral-900/40 transition">
                    <td class="px-5 py-3 font-medium text-neutral-900 dark:text-white">{{ $c->name }}</td>
                    <td class="px-5 py-3 font-mono text-xs text-neutral-600 dark:text-neutral-400">{{ $c->slug }}</td>
                    <td class="px-5 py-3 text-right text-neutral-900 dark:text-white tabular-nums">{{ (int) $c->sort_order }}</td>
                    <td class="px-5 py-3 text-center">
                        <span class="badge badge-{{ $c->is_active ? 'approved' : 'rejected' }}">
                            {{ $c->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('admin.categories.edit', $c) }}" class="btn-secondary text-xs py-2 px-3">Edit</a>
                            <form method="POST" action="{{ route('admin.categories.toggle', $c) }}">
                                @csrf
                                <button type="submit" class="btn-secondary text-xs py-2 px-3">
                                    {{ $c->is_active ? 'Disable' : 'Enable' }}
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-5 py-8 text-center text-neutral-400">No categories found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($categories->hasPages())
    <div class="px-5 py-4 border-t border-neutral-100 dark:border-neutral-800">
        {{ $categories->withQueryString()->links() }}
    </div>
    @endif
</div>

@endsection


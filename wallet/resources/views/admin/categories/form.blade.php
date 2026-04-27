@extends('admin.layouts.app')
@section('title', ($category ? 'Edit category' : 'New category').' — ExtraCash Admin')
@section('page-title', $category ? 'Edit category' : 'New category')
@section('breadcrumb', 'Marketplace categories')

@section('content')

<div class="mb-5">
    <a href="{{ route('admin.categories.index') }}" class="btn-secondary text-xs">← Back to categories</a>
</div>

<div class="card p-6 max-w-2xl">
    <form method="POST" action="{{ $category ? route('admin.categories.update', $category) : route('admin.categories.store') }}" class="space-y-4">
        @csrf

        <div>
            <label class="block text-xs font-semibold text-neutral-600 mb-1">Name</label>
            <input type="text" name="name" value="{{ old('name', $category?->name) }}" maxlength="100" required
                   class="w-full px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white" />
            @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-xs font-semibold text-neutral-600 mb-1">Slug (optional)</label>
            <input type="text" name="slug" value="{{ old('slug', $category?->slug) }}" maxlength="120"
                   placeholder="auto-generated from name if empty"
                   class="w-full px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white" />
            @error('slug')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-neutral-600 mb-1">Sort order</label>
                <input type="number" name="sort_order" value="{{ old('sort_order', $category?->sort_order ?? 0) }}"
                       class="w-full px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white" />
                @error('sort_order')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="flex items-center gap-2 pt-6">
                <input id="is_active" type="checkbox" name="is_active" value="1"
                       class="rounded border-neutral-300"
                       {{ old('is_active', $category ? ($category->is_active ? 1 : 0) : 1) ? 'checked' : '' }} />
                <label for="is_active" class="text-sm text-neutral-700 dark:text-neutral-300">Active</label>
            </div>
        </div>

        <button type="submit" class="btn-primary">Save</button>
    </form>
</div>

@endsection


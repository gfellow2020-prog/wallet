<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $q = Category::query()
            ->when($request->query('q'), function ($qb, $search) {
                $s = (string) $search;
                $qb->where(function ($qq) use ($s) {
                    $qq->where('name', 'like', "%{$s}%")
                        ->orWhere('slug', 'like', "%{$s}%");
                });
            })
            ->when($request->query('active') !== null && $request->query('active') !== '', function ($qb) use ($request) {
                $active = $request->query('active') === '1';
                $qb->where('is_active', $active);
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.categories.index', ['categories' => $q]);
    }

    public function create()
    {
        return view('admin.categories.form', ['category' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:categories,name'],
            'slug' => ['nullable', 'string', 'max:120', 'unique:categories,slug'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $name = trim($data['name']);
        $slug = trim((string) ($data['slug'] ?? ''));
        $slug = $slug !== '' ? Str::slug($slug, '-') : Str::slug($name, '-');

        Category::create([
            'name' => $name,
            'slug' => $slug,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return redirect()->route('admin.categories.index')->with('success', 'Category created.');
    }

    public function edit(Category $category)
    {
        return view('admin.categories.form', ['category' => $category]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:categories,name,'.$category->id],
            'slug' => ['nullable', 'string', 'max:120', 'unique:categories,slug,'.$category->id],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $name = trim($data['name']);
        $slug = trim((string) ($data['slug'] ?? ''));
        $slug = $slug !== '' ? Str::slug($slug, '-') : Str::slug($name, '-');

        $category->update([
            'name' => $name,
            'slug' => $slug,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return redirect()->route('admin.categories.index')->with('success', 'Category updated.');
    }

    public function toggle(Category $category): RedirectResponse
    {
        $category->update(['is_active' => ! (bool) $category->is_active]);
        return back()->with('success', 'Category status updated.');
    }
}


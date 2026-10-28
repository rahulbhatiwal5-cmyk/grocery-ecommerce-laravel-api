<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = Category::query()
            ->withCount('products')
            ->orderBy('name')
            ->paginate(15);

        return view('admin.categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('admin.categories.form', ['category' => new Category]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = $this->uniqueSlug($data['name']);
        $data['status'] = $request->boolean('status');

        Category::create($data);

        return redirect()->route('admin.categories.index')->with('success', 'Category created successfully.');
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.form', compact('category'));
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = $this->uniqueSlug($data['name'], $category);
        $data['status'] = $request->boolean('status');
        $category->update($data);

        return redirect()->route('admin.categories.index')->with('success', 'Category updated successfully.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->products()->exists()) {
            return back()->with('error', 'This category cannot be deleted while it still contains products.');
        }

        $category->delete();

        return redirect()->route('admin.categories.index')->with('success', 'Category deleted successfully.');
    }

    private function uniqueSlug(string $name, ?Category $ignoredCategory = null): string
    {
        $baseSlug = Str::slug($name) ?: 'category';
        $slug = $baseSlug;
        $suffix = 2;

        while ($this->slugExists($slug, $ignoredCategory)) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function slugExists(string $slug, ?Category $ignoredCategory): bool
    {
        $query = Category::query()->where('slug', $slug);

        if ($ignoredCategory) {
            $query->where('id', '!=', $ignoredCategory->id);
        }

        return $query->exists();
    }
}

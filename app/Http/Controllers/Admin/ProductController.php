<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer', Rule::exists('categories', 'id')],
        ]);
        $products = Product::query()
            ->with('category:id,name')
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery->where('name', 'like', '%'.$search.'%')
                        ->orWhere('unit', 'like', '%'.$search.'%')
                        ->orWhere('slug', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['category'] ?? null, fn ($query, int $category): mixed => $query->where('category_id', $category))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();
        $categories = Category::query()->orderBy('name')->get(['id', 'name']);

        return view('admin.products.index', compact('products', 'categories', 'filters'));
    }

    public function create(): View
    {
        return view('admin.products.form', [
            'product' => new Product,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $data = $this->productData($request);
        $data['slug'] = $this->uniqueSlug($data['name']);

        Product::create($data);

        return redirect()->route('admin.products.index')->with('success', 'Product created successfully.');
    }

    public function edit(Product $product): View
    {
        return view('admin.products.form', [
            'product' => $product,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $data = $this->productData($request);
        $data['slug'] = $this->uniqueSlug($data['name'], $product);
        $product->update($data);

        return redirect()->route('admin.products.index')->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        if ($product->cartItems()->exists()) {
            return back()->with('error', 'This product cannot be deleted while it is present in a customer cart.');
        }

        $product->delete();

        return redirect()->route('admin.products.index')->with('success', 'Product deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function productData(StoreProductRequest|UpdateProductRequest $request): array
    {
        return [
            ...$request->validated(),
            'status' => $request->boolean('status'),
        ];
    }

    private function uniqueSlug(string $name, ?Product $ignoredProduct = null): string
    {
        $baseSlug = Str::slug($name) ?: 'product';
        $slug = $baseSlug;
        $suffix = 2;

        while ($this->slugExists($slug, $ignoredProduct)) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function slugExists(string $slug, ?Product $ignoredProduct): bool
    {
        $query = Product::query()->where('slug', $slug);

        if ($ignoredProduct) {
            $query->where('id', '!=', $ignoredProduct->id);
        }

        return $query->exists();
    }
}

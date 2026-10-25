<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'category' => ['nullable', 'string', 'max:100'], 'sort' => ['nullable', Rule::in(['recommended', 'price_asc', 'price_desc'])]]);
        $query = Product::query()->with('category')->where('status', true)->whereHas('category', fn ($q) => $q->where('status', true));
        if (! empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$term.'%')->orWhere('unit', 'like', '%'.$term.'%')->orWhereHas('category', fn ($c) => $c->where('name', 'like', '%'.$term.'%')->orWhere('slug', 'like', '%'.$term.'%')));
        }
        if (! empty($filters['category'])) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $filters['category']));
        }
        if (($filters['sort'] ?? '') === 'price_asc') {
            $query->orderBy('price');
        }
        if (($filters['sort'] ?? '') === 'price_desc') {
            $query->orderByDesc('price');
        }

        return ProductResource::collection($query->orderBy('id')->get());
    }

    public function show(string $product): ProductResource
    {
        $query = Product::query()->with('category')->where('status', true)->whereHas('category', fn ($q) => $q->where('status', true));
        $item = ctype_digit($product) ? $query->whereKey((int) $product)->firstOrFail() : $query->where('slug', $product)->firstOrFail();

        return new ProductResource($item);
    }
}

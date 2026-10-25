<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection(Category::where('status', true)->orderBy('id')->get());
    }

    public function show(string $category): CategoryResource
    {
        $query = Category::query()->where('status', true)->with(['products' => fn ($q) => $q->where('status', true)->with('category')->orderBy('id')]);
        $item = ctype_digit($category) ? $query->whereKey((int) $category)->firstOrFail() : $query->where('slug', $category)->firstOrFail();

        return new CategoryResource($item);
    }
}

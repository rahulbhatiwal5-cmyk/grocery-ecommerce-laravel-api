<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WishlistResource;
use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WishlistController extends Controller
{
    private function withWishlist(Closure $operation): WishlistResource
    {
        $wishlist = DB::transaction(function () use ($operation) {
            $user = User::query()->whereKey(auth('api')->id())->lockForUpdate()->firstOrFail();
            $wishlist = $user->wishlist()->firstOrCreate();
            $operation($wishlist);

            return $wishlist->load(['items.product.category']);
        });
        $wishlist->wasRecentlyCreated = false;

        return new WishlistResource($wishlist);
    }

    private function addProducts(Wishlist $wishlist, array $ids): void
    {
        $ids = collect($ids)->map(fn ($id) => (int) $id)->unique()->sort()->values();
        $existing = $wishlist->items()->whereIn('product_id', $ids)->pluck('product_id');
        $newIds = $ids->diff($existing)->values();
        if ($newIds->isEmpty()) {
            return;
        }
        $available = Product::query()->whereIn('id', $newIds)->where('status', true)->where('stock', '>', 0)
            ->whereHas('category', fn ($query) => $query->where('status', true))->orderBy('id')->lockForUpdate()->pluck('id');
        if ($available->count() !== $newIds->count()) {
            throw ValidationException::withMessages(['product_id' => 'One or more products are unavailable.']);
        }
        foreach ($newIds as $id) {
            $wishlist->items()->create(['product_id' => $id]);
        }
    }

    private function onlyKeys(Request $request, array $allowed): void
    {
        if (array_diff(array_keys($request->all()), $allowed)) {
            throw ValidationException::withMessages(['items' => 'Only product identifiers are accepted.']);
        }
    }

    public function index(): WishlistResource
    {
        return $this->withWishlist(fn (Wishlist $wishlist) => null);
    }

    public function store(Request $request): WishlistResource
    {
        $this->onlyKeys($request, ['product_id']);
        $data = $request->validate(['product_id' => ['required', 'integer', 'min:1']]);

        return $this->withWishlist(fn (Wishlist $wishlist) => $this->addProducts($wishlist, [$data['product_id']]));
    }

    public function destroy(string $product): WishlistResource
    {
        return $this->withWishlist(fn (Wishlist $wishlist) => $wishlist->items()->where('product_id', $product)->firstOrFail()->delete());
    }

    public function clear(): WishlistResource
    {
        return $this->withWishlist(fn (Wishlist $wishlist) => $wishlist->items()->delete());
    }

    public function merge(Request $request): WishlistResource
    {
        $this->onlyKeys($request, ['items']);
        $data = $request->validate(['items' => ['required', 'array', 'min:1', 'max:100'], 'items.*' => ['required', 'array:product_id'], 'items.*.product_id' => ['required', 'integer', 'min:1']]);

        return $this->withWishlist(fn (Wishlist $wishlist) => $this->addProducts($wishlist, array_column($data['items'], 'product_id')));
    }
}

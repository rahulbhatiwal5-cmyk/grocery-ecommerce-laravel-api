<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WishlistResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['items' => $this->items->map(fn ($item) => ['product' => (new ProductResource($item->product))->resolve($request)]), 'wishlist_count' => $this->items->count()];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $items = $this->items->map(function ($item) use ($request) {
            $priceInPaise = (int) round((float) $item->product->price * 100);

            return ['product' => (new ProductResource($item->product))->resolve($request), 'quantity' => $item->quantity, 'line_total' => $priceInPaise * $item->quantity / 100];
        });
        $subtotalInPaise = $this->items->sum(fn ($item) => (int) round((float) $item->product->price * 100) * $item->quantity);
        $deliveryInPaise = $items->isNotEmpty() ? 2000 : 0;

        return ['items' => $items, 'cart_count' => $this->items->sum('quantity'), 'subtotal' => $subtotalInPaise / 100, 'delivery_charge' => $deliveryInPaise / 100, 'grand_total' => ($subtotalInPaise + $deliveryInPaise) / 100];
    }
}

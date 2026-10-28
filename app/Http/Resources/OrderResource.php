<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $itemCount = $this->items_sum_quantity ?? ($this->resource->relationLoaded('items') ? $this->items->sum('quantity') : 0);

        return [
            'id' => $this->id,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'payment_method' => $this->payment_method,
            'subtotal' => (float) $this->subtotal,
            'delivery_charge' => (float) $this->delivery_charge,
            'grand_total' => (float) $this->grand_total,
            'item_count' => $itemCount,
            'placed_at' => $this->placed_at?->toISOString(),
            'shipping_address' => [
                'full_name' => $this->shipping_full_name,
                'phone' => $this->shipping_phone,
                'address_line_1' => $this->shipping_address_line_1,
                'address_line_2' => $this->shipping_address_line_2,
                'city' => $this->shipping_city,
                'state' => $this->shipping_state,
                'postal_code' => $this->shipping_postal_code,
                'country' => $this->shipping_country,
            ],
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $old = $this->old_price === null ? null : (float) $this->old_price;
        $price = (float) $this->price;

        return ['id' => $this->id, 'name' => $this->name, 'slug' => $this->slug, 'description' => $this->description, 'unit' => $this->unit, 'price' => $price, 'oldPrice' => $old, 'category' => $this->category->slug, 'discount' => $old > $price && $old > 0 ? round(($old - $price) / $old * 100).'% OFF' : '', 'stock' => $this->stock, 'imageUrl' => $this->image_url];
    }
}

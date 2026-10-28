<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id === null ? null : (int) $this->product_id,
            'product_name' => $this->product_name,
            'unit' => $this->unit,
            'unit_price' => (float) $this->unit_price,
            'quantity' => $this->quantity,
            'line_total' => (float) $this->line_total,
        ];
    }
}

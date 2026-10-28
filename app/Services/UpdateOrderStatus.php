<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateOrderStatus
{
    public function handle(Order $order, string $status): void
    {
        if (! in_array($status, Order::STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => 'The selected order status is invalid.',
            ]);
        }

        DB::transaction(function () use ($order, $status): void {
            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->with('items')
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->status === 'cancelled' && $status !== 'cancelled') {
                throw ValidationException::withMessages([
                    'status' => 'Cancelled orders cannot be reopened.',
                ]);
            }

            if ($lockedOrder->status === $status) {
                return;
            }

            if ($status === 'cancelled') {
                $productIds = $lockedOrder->items
                    ->pluck('product_id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->sort()
                    ->values();
                $products = Product::query()
                    ->whereIn('id', $productIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($lockedOrder->items as $item) {
                    $product = $products->get($item->product_id);

                    if ($product) {
                        $product->increment('stock', $item->quantity);
                    }
                }
            }

            $lockedOrder->update(['status' => $status]);
        });
    }
}

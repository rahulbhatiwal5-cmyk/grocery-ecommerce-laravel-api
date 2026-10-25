<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    private const MAX_QUANTITY = 10000;

    private function withCart(Closure $operation): CartResource
    {
        $cart = DB::transaction(function () use ($operation) {
            $user = User::query()->whereKey(auth('api')->id())->lockForUpdate()->firstOrFail();
            $cart = $user->cart()->firstOrCreate();
            $operation($cart);

            return $cart->load(['items.product.category']);
        });
        $cart->wasRecentlyCreated = false;

        return new CartResource($cart);
    }

    private function availableProduct(int $id, int $quantity): Product
    {
        $product = Product::query()->whereKey($id)->where('status', true)
            ->whereHas('category', fn ($query) => $query->where('status', true))->lockForUpdate()->first();
        if (! $product || $quantity > $product->stock || $quantity > self::MAX_QUANTITY) {
            throw ValidationException::withMessages(['product_id' => 'The product or requested quantity is unavailable.']);
        }

        return $product;
    }

    public function index(): CartResource
    {
        return $this->withCart(fn (Cart $cart) => null);
    }

    public function store(Request $request): CartResource
    {
        $data = $request->validate(['product_id' => ['required', 'integer', 'min:1'], 'quantity' => ['required', 'integer', 'min:1', 'max:10000']]);

        return $this->withCart(function (Cart $cart) use ($data) {
            $item = $cart->items()->where('product_id', $data['product_id'])->first();
            $quantity = ($item?->quantity ?? 0) + (int) $data['quantity'];
            $this->availableProduct((int) $data['product_id'], $quantity);
            if ($item) {
                $item->update(['quantity' => $quantity]);
            } else {
                $cart->items()->create(['product_id' => $data['product_id'], 'quantity' => $quantity]);
            }
        });
    }

    public function update(Request $request, string $product): CartResource
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:10000']]);

        return $this->withCart(function (Cart $cart) use ($data, $product) {
            $item = $cart->items()->where('product_id', $product)->firstOrFail();
            $this->availableProduct((int) $product, (int) $data['quantity']);
            $item->update(['quantity' => $data['quantity']]);
        });
    }

    public function destroy(string $product): CartResource
    {
        return $this->withCart(function (Cart $cart) use ($product) {
            $cart->items()->where('product_id', $product)->firstOrFail()->delete();
        });
    }

    public function clear(): CartResource
    {
        return $this->withCart(fn (Cart $cart) => $cart->items()->delete());
    }

    public function merge(Request $request): CartResource
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['required', 'array:product_id,quantity'],
            'items.*.product_id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);
        if (array_diff(array_keys($request->all()), ['items'])) {
            throw ValidationException::withMessages(['items' => 'Only cart items are accepted.']);
        }
        $key = $request->header('Idempotency-Key');
        Validator::make(['key' => $key], ['key' => ['required', 'uuid']])->validate();
        $items = collect($data['items'])->map(fn ($item) => ['product_id' => (int) $item['product_id'], 'quantity' => (int) $item['quantity']])->sortBy('product_id')->values()->all();
        $hash = hash('sha256', json_encode($items, JSON_THROW_ON_ERROR));

        return $this->withCart(function (Cart $cart) use ($key, $hash, $items) {
            $previous = DB::table('cart_merge_requests')->where('cart_id', $cart->id)->where('request_key', $key)->first();
            if ($previous) {
                if (! hash_equals($previous->payload_hash, $hash)) {
                    throw ValidationException::withMessages(['items' => 'This merge key was already used for a different cart.']);
                }

                return;
            }
            foreach ($items as $entry) {
                $item = $cart->items()->where('product_id', $entry['product_id'])->first();
                $quantity = ($item?->quantity ?? 0) + $entry['quantity'];
                $this->availableProduct($entry['product_id'], $quantity);
                if ($item) {
                    $item->update(['quantity' => $quantity]);
                } else {
                    $cart->items()->create(['product_id' => $entry['product_id'], 'quantity' => $quantity]);
                }
            }
            DB::table('cart_merge_requests')->insert(['cart_id' => $cart->id, 'request_key' => $key, 'payload_hash' => $hash, 'created_at' => now(), 'updated_at' => now()]);
        });
    }
}

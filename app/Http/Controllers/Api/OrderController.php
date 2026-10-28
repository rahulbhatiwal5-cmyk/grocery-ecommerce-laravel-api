<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    private const DELIVERY_CHARGE_IN_PAISE = 2000;

    public function index(): AnonymousResourceCollection
    {
        $orders = Order::query()
            ->where('user_id', auth('api')->id())
            ->withSum('items', 'quantity')
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->get();

        return OrderResource::collection($orders);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $data = $request->validated();
        $idempotencyKey = $request->header('Idempotency-Key');

        if ($idempotencyKey !== null) {
            Validator::make(['key' => $idempotencyKey], ['key' => ['required', 'uuid']])->validate();
        }

        $payloadHash = hash('sha256', json_encode([
            'address_id' => (int) $data['address_id'],
            'payment_method' => $data['payment_method'],
        ], JSON_THROW_ON_ERROR));

        $result = DB::transaction(function () use ($data, $idempotencyKey, $payloadHash): array {
            $user = User::query()->whereKey(auth('api')->id())->lockForUpdate()->firstOrFail();

            if ($idempotencyKey !== null) {
                $existingOrder = $user->orders()
                    ->where('idempotency_key', $idempotencyKey)
                    ->with('items')
                    ->withSum('items', 'quantity')
                    ->first();

                if ($existingOrder) {
                    if (! hash_equals((string) $existingOrder->request_hash, $payloadHash)) {
                        throw ValidationException::withMessages([
                            'address_id' => 'This idempotency key was already used for a different order request.',
                        ]);
                    }

                    return ['order' => $existingOrder, 'created' => false];
                }
            }

            $address = $user->addresses()->whereKey($data['address_id'])->lockForUpdate()->first();

            if (! $address) {
                throw ValidationException::withMessages([
                    'address_id' => 'The selected delivery address is invalid.',
                ]);
            }

            $cart = $user->cart()->lockForUpdate()->first();

            if (! $cart) {
                $this->emptyCart();
            }

            $cartItems = $cart->items()->orderBy('product_id')->get();

            if ($cartItems->isEmpty()) {
                $this->emptyCart();
            }

            $productIds = $cartItems->pluck('product_id')->map(fn ($id) => (int) $id)->unique()->sort()->values();
            $products = Product::query()
                ->whereIn('id', $productIds)
                ->where('status', true)
                ->whereHas('category', fn ($query) => $query->where('status', true))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($products->count() !== $productIds->count()) {
                throw ValidationException::withMessages([
                    'cart' => 'One or more cart products are unavailable.',
                ]);
            }

            $subtotalInPaise = 0;
            $orderItems = [];

            foreach ($cartItems as $cartItem) {
                $product = $products->get($cartItem->product_id);
                $quantity = (int) $cartItem->quantity;

                if ($quantity < 1 || $quantity > (int) $product->stock) {
                    throw ValidationException::withMessages([
                        'cart' => 'One or more cart items no longer have enough stock.',
                    ]);
                }

                $unitPriceInPaise = (int) round((float) $product->price * 100);
                $lineTotalInPaise = $unitPriceInPaise * $quantity;
                $subtotalInPaise += $lineTotalInPaise;
                $orderItems[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'unit' => $product->unit,
                    'unit_price' => $unitPriceInPaise / 100,
                    'quantity' => $quantity,
                    'line_total' => $lineTotalInPaise / 100,
                ];
            }

            $deliveryChargeInPaise = self::DELIVERY_CHARGE_IN_PAISE;
            $order = $user->orders()->create([
                'shipping_full_name' => $address->full_name,
                'shipping_phone' => $address->phone,
                'shipping_address_line_1' => $address->address_line_1,
                'shipping_address_line_2' => $address->address_line_2,
                'shipping_city' => $address->city,
                'shipping_state' => $address->state,
                'shipping_postal_code' => $address->postal_code,
                'shipping_country' => $address->country,
                'subtotal' => $subtotalInPaise / 100,
                'delivery_charge' => $deliveryChargeInPaise / 100,
                'grand_total' => ($subtotalInPaise + $deliveryChargeInPaise) / 100,
                'status' => 'pending',
                'payment_status' => 'pending',
                'payment_method' => $data['payment_method'],
                'placed_at' => now(),
                'idempotency_key' => $idempotencyKey,
                'request_hash' => $idempotencyKey === null ? null : $payloadHash,
            ]);

            $order->items()->createMany($orderItems);

            foreach ($cartItems as $cartItem) {
                $product = $products->get($cartItem->product_id);
                $product->update(['stock' => (int) $product->stock - (int) $cartItem->quantity]);
            }

            $cart->items()->delete();

            return ['order' => $order->load('items')->loadSum('items', 'quantity'), 'created' => true];
        });

        return (new OrderResource($result['order']))->response()->setStatusCode($result['created'] ? 201 : 200);
    }

    public function show(string $order): OrderResource
    {
        $order = Order::query()
            ->whereKey($order)
            ->where('user_id', auth('api')->id())
            ->with('items')
            ->withSum('items', 'quantity')
            ->firstOrFail();

        return new OrderResource($order);
    }

    private function emptyCart(): never
    {
        throw ValidationException::withMessages([
            'cart' => 'Your cart is empty.',
        ]);
    }
}

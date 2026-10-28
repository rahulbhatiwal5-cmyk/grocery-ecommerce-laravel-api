<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class OrderCheckoutService
{
    private const DELIVERY_CHARGE_IN_PAISE = 2000;

    /**
     * @return array{order: Order, created: bool}
     */
    public function createCodOrder(User $authenticatedUser, int $addressId, ?string $idempotencyKey): array
    {
        return $this->createOrder($authenticatedUser, $addressId, 'cod', $idempotencyKey, true);
    }

    /**
     * Creates a stock-reserved order while preserving the cart until payment succeeds.
     *
     * @return array{order: Order, created: bool}
     */
    public function reserveRazorpayOrder(User $authenticatedUser, int $addressId, ?string $idempotencyKey): array
    {
        return $this->createOrder($authenticatedUser, $addressId, 'razorpay', $idempotencyKey, false);
    }

    /**
     * @return array{order: Order, created: bool}
     */
    private function createOrder(User $authenticatedUser, int $addressId, string $paymentMethod, ?string $idempotencyKey, bool $clearCart): array
    {
        if ($idempotencyKey !== null) {
            Validator::make(['key' => $idempotencyKey], ['key' => ['required', 'uuid']])->validate();
        }

        $payloadHash = hash('sha256', json_encode([
            'address_id' => $addressId,
            'payment_method' => $paymentMethod,
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($authenticatedUser, $addressId, $paymentMethod, $idempotencyKey, $payloadHash, $clearCart): array {
            $user = User::query()->whereKey($authenticatedUser->getKey())->lockForUpdate()->firstOrFail();

            if ($idempotencyKey !== null) {
                $existingOrder = $user->orders()
                    ->where('idempotency_key', $idempotencyKey)
                    ->with(['items', 'paymentTransaction'])
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

            $address = $user->addresses()->whereKey($addressId)->lockForUpdate()->first();

            if (! $address) {
                throw ValidationException::withMessages([
                    'address_id' => 'The selected delivery address is invalid.',
                ]);
            }

            $cart = $user->cart()->lockForUpdate()->first();

            if (! $cart || $cart->items()->doesntExist()) {
                $this->emptyCart();
            }

            $cartItems = $cart->items()->orderBy('product_id')->get();
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

                $unitPriceInPaise = $this->toPaise((string) $product->price);
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
                'delivery_charge' => self::DELIVERY_CHARGE_IN_PAISE / 100,
                'grand_total' => ($subtotalInPaise + self::DELIVERY_CHARGE_IN_PAISE) / 100,
                'status' => 'pending',
                'payment_status' => 'pending',
                'payment_method' => $paymentMethod,
                'placed_at' => now(),
                'idempotency_key' => $idempotencyKey,
                'request_hash' => $idempotencyKey === null ? null : $payloadHash,
            ]);

            $order->items()->createMany($orderItems);

            foreach ($cartItems as $cartItem) {
                $product = $products->get($cartItem->product_id);
                $product->update(['stock' => (int) $product->stock - (int) $cartItem->quantity]);
            }

            if ($clearCart) {
                $cart->items()->delete();
            }

            return [
                'order' => $order->load(['items', 'paymentTransaction'])->loadSum('items', 'quantity'),
                'created' => true,
            ];
        });
    }

    private function toPaise(string $amount): int
    {
        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $amount, $matches)) {
            throw ValidationException::withMessages(['cart' => 'A product has an invalid price.']);
        }

        return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
    }

    private function emptyCart(): never
    {
        throw ValidationException::withMessages([
            'cart' => 'Your cart is empty.',
        ]);
    }
}

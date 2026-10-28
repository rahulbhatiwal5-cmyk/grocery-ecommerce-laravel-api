<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RazorpayPaymentService
{
    public function __construct(
        private readonly OrderCheckoutService $checkout,
        private readonly RazorpayGateway $gateway,
    ) {}

    /**
     * @return array{order: Order, transaction: PaymentTransaction, key_id: string, created: bool}
     */
    public function createOrder(User $user, int $addressId, ?string $idempotencyKey): array
    {
        $reservation = $this->checkout->reserveRazorpayOrder($user, $addressId, $idempotencyKey);
        $order = $reservation['order'];

        if (! $reservation['created']) {
            $transaction = $order->paymentTransaction;

            if (! $transaction || $transaction->provider_order_id === null) {
                throw ValidationException::withMessages([
                    'payment' => 'This payment attempt is not ready. Start a new payment attempt.',
                ]);
            }

            return ['order' => $order, 'transaction' => $transaction, 'key_id' => $this->gateway->publicKey(), 'created' => false];
        }

        $transaction = $order->paymentTransaction()->create([
            'provider' => 'razorpay',
            'amount' => $this->toPaise((string) $order->grand_total),
            'currency' => 'INR',
            'status' => 'pending',
        ]);

        try {
            $providerOrder = $this->gateway->createOrder(
                $transaction->amount,
                'greenbasket_'.$order->id,
                ['local_order_id' => (string) $order->id],
            );
        } catch (\Throwable $exception) {
            $this->markFailed($order, null, 'gateway_unavailable', 'Unable to start the Razorpay payment.');

            throw $exception;
        }

        $providerOrderId = $providerOrder['id'] ?? null;
        $providerAmount = $providerOrder['amount'] ?? null;
        $providerCurrency = $providerOrder['currency'] ?? null;

        if (! is_string($providerOrderId) || ! is_int($providerAmount) || $providerAmount !== $transaction->amount || $providerCurrency !== 'INR') {
            $this->markFailed($order, null, 'invalid_gateway_response', 'The payment provider returned an invalid order.');

            throw ValidationException::withMessages(['payment' => 'Unable to start the Razorpay payment.']);
        }

        $transaction->update(['provider_order_id' => $providerOrderId]);

        return [
            'order' => $order->fresh(['items', 'paymentTransaction'])->loadSum('items', 'quantity'),
            'transaction' => $transaction->fresh(),
            'key_id' => $this->gateway->publicKey(),
            'created' => true,
        ];
    }

    public function verifyClientPayment(User $user, int $orderId, string $providerOrderId, string $paymentId, string $signature): Order
    {
        $transaction = PaymentTransaction::query()
            ->where('provider', 'razorpay')
            ->where('order_id', $orderId)
            ->where('provider_order_id', $providerOrderId)
            ->whereHas('order', fn ($query) => $query->where('user_id', $user->id))
            ->first();

        if (! $transaction) {
            throw ValidationException::withMessages(['payment' => 'The payment reference is invalid.']);
        }

        if ($transaction->status === 'paid') {
            return $transaction->order()->with('items')->withSum('items', 'quantity')->firstOrFail();
        }

        if ($transaction->status !== 'pending') {
            throw ValidationException::withMessages(['payment' => 'This payment attempt is no longer active.']);
        }

        $this->gateway->verifyPaymentSignature($providerOrderId, $paymentId, $signature);

        return $this->finalizePayment($providerOrderId, $paymentId, $transaction->amount, $transaction->currency, $user->id);
    }

    public function markClientPaymentFailed(User $user, int $orderId, string $providerOrderId, ?string $failureCode, ?string $failureMessage): Order
    {
        $order = Order::query()->whereKey($orderId)->where('user_id', $user->id)->firstOrFail();
        $transaction = $order->paymentTransaction;

        if (! $transaction || $transaction->provider !== 'razorpay' || ! hash_equals((string) $transaction->provider_order_id, $providerOrderId)) {
            throw ValidationException::withMessages(['payment' => 'The payment reference is invalid.']);
        }

        return $this->markFailed($order, $transaction, $failureCode, $failureMessage);
    }

    public function processWebhook(string $event, array $payload): void
    {
        $payment = $payload['payment']['entity'] ?? null;

        if (! is_array($payment) || ! is_string($payment['order_id'] ?? null)) {
            return;
        }

        $providerOrderId = $payment['order_id'];

        if ($event === 'payment.captured') {
            $paymentId = $payment['id'] ?? null;
            $amount = $payment['amount'] ?? null;
            $currency = $payment['currency'] ?? null;

            if (is_string($paymentId) && is_int($amount) && is_string($currency)) {
                $this->finalizePayment($providerOrderId, $paymentId, $amount, $currency);
            }

            return;
        }

        if ($event === 'payment.failed') {
            $transaction = PaymentTransaction::query()
                ->where('provider', 'razorpay')
                ->where('provider_order_id', $providerOrderId)
                ->with('order')
                ->first();

            if ($transaction) {
                $this->markFailed(
                    $transaction->order,
                    $transaction,
                    is_string($payment['error_code'] ?? null) ? $payment['error_code'] : 'payment_failed',
                    is_string($payment['error_description'] ?? null) ? $payment['error_description'] : 'Razorpay reported that the payment failed.',
                );
            }
        }
    }

    private function finalizePayment(string $providerOrderId, string $paymentId, int $amount, string $currency, ?int $expectedUserId = null): Order
    {
        return DB::transaction(function () use ($providerOrderId, $paymentId, $amount, $currency, $expectedUserId): Order {
            $transaction = PaymentTransaction::query()
                ->where('provider', 'razorpay')
                ->where('provider_order_id', $providerOrderId)
                ->with(['order.items'])
                ->lockForUpdate()
                ->firstOrFail();
            $order = $transaction->order;

            if ($expectedUserId !== null && $order->user_id !== $expectedUserId) {
                throw ValidationException::withMessages(['payment' => 'The payment does not belong to this account.']);
            }

            if ($transaction->status === 'paid' && $order->payment_status === 'paid') {
                return $order->loadSum('items', 'quantity');
            }

            if ($transaction->status !== 'pending' || $order->payment_status !== 'pending') {
                throw ValidationException::withMessages(['payment' => 'This payment attempt is no longer active.']);
            }

            if ($transaction->amount !== $amount || $transaction->currency !== $currency) {
                throw ValidationException::withMessages(['payment' => 'The payment amount does not match this order.']);
            }

            $transaction->update([
                'provider_payment_id' => $paymentId,
                'status' => 'paid',
                'finalized_at' => now(),
                'failure_code' => null,
                'failure_message' => null,
            ]);
            $order->update(['payment_status' => 'paid']);

            $this->removePurchasedItemsFromCart($order);

            return $order->fresh(['items', 'paymentTransaction'])->loadSum('items', 'quantity');
        });
    }

    private function markFailed(Order $order, ?PaymentTransaction $transaction, ?string $failureCode, ?string $failureMessage): Order
    {
        return DB::transaction(function () use ($order, $failureCode, $failureMessage): Order {
            $lockedOrder = Order::query()->whereKey($order->id)->with('items')->lockForUpdate()->firstOrFail();
            $lockedTransaction = PaymentTransaction::query()->where('order_id', $lockedOrder->id)->lockForUpdate()->first();

            if (! $lockedTransaction || $lockedTransaction->status === 'paid' || $lockedOrder->payment_status === 'paid') {
                return $lockedOrder->load(['items', 'paymentTransaction'])->loadSum('items', 'quantity');
            }

            if ($lockedTransaction->status === 'failed') {
                return $lockedOrder->load(['items', 'paymentTransaction'])->loadSum('items', 'quantity');
            }

            $this->restoreReservedStock($lockedOrder);

            $lockedTransaction->update([
                'status' => 'failed',
                'failure_code' => $failureCode,
                'failure_message' => $failureMessage,
            ]);
            $lockedOrder->update(['status' => 'cancelled', 'payment_status' => 'failed']);

            return $lockedOrder->fresh(['items', 'paymentTransaction'])->loadSum('items', 'quantity');
        });
    }

    private function restoreReservedStock(Order $order): void
    {
        $productIds = $order->items->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->unique()->sort()->values();
        $products = Product::query()->whereIn('id', $productIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        foreach ($order->items as $item) {
            $product = $products->get($item->product_id);

            if ($product) {
                $product->increment('stock', $item->quantity);
            }
        }
    }

    private function removePurchasedItemsFromCart(Order $order): void
    {
        $cart = Cart::query()->where('user_id', $order->user_id)->lockForUpdate()->first();

        if (! $cart) {
            return;
        }

        $cartItems = $cart->items()->whereIn('product_id', $order->items->pluck('product_id')->filter())->lockForUpdate()->get()->keyBy('product_id');

        foreach ($order->items as $orderItem) {
            $cartItem = $cartItems->get($orderItem->product_id);

            if (! $cartItem) {
                continue;
            }

            if ($cartItem->quantity <= $orderItem->quantity) {
                $cartItem->delete();
            } else {
                $cartItem->decrement('quantity', $orderItem->quantity);
            }
        }
    }

    private function toPaise(string $amount): int
    {
        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $amount, $matches)) {
            throw ValidationException::withMessages(['payment' => 'The order amount is invalid.']);
        }

        return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
    }
}

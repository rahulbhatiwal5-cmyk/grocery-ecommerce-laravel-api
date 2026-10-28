<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\RazorpayGateway;
use App\Services\UpdateOrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Razorpay\Api\Errors\SignatureVerificationError;
use Tests\TestCase;

class RazorpayPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_payment_routes_require_authentication(): void
    {
        $this->postJson('/api/payments/razorpay/create-order', ['address_id' => 1])->assertUnauthorized();
        $this->postJson('/api/payments/razorpay/verify', [])->assertUnauthorized();
        $this->postJson('/api/payments/razorpay/fail', [])->assertUnauthorized();
    }

    public function test_empty_cart_invalid_address_and_foreign_address_are_rejected_before_a_gateway_order_is_created(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create();
        $gateway = $this->gateway();
        $gateway->shouldNotReceive('createOrder');

        $this->actingAs($user, 'api')->postJson('/api/payments/razorpay/create-order', ['address_id' => $address->id])
            ->assertUnprocessable()->assertJsonValidationErrors('cart');

        $product = Product::factory()->create(['stock' => 4]);
        $otherUser = User::factory()->create();
        $foreignAddress = Address::factory()->for($otherUser)->create();
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();
        $this->postJson('/api/payments/razorpay/create-order', ['address_id' => 999999])->assertUnprocessable()->assertJsonValidationErrors('address_id');
        $this->postJson('/api/payments/razorpay/create-order', ['address_id' => $foreignAddress->id])->assertUnprocessable()->assertJsonValidationErrors('address_id');
    }

    public function test_razorpay_order_is_server_priced_stored_and_reserves_stock_while_preserving_cart(): void
    {
        [$user, $address, $product] = $this->checkoutData(['price' => 499.50, 'stock' => 3]);
        $gateway = $this->gateway();
        $gateway->shouldReceive('createOrder')->once()->with(51950, 'greenbasket_1', Mockery::type('array'))
            ->andReturn(['id' => 'order_test_123', 'amount' => 51950, 'currency' => 'INR']);
        $gateway->shouldReceive('publicKey')->once()->andReturn('rzp_test_public');

        $this->actingAs($user, 'api')->withHeader('Idempotency-Key', '0c7b62e5-1cf3-42ed-832f-1f22acb20180')
            ->postJson('/api/payments/razorpay/create-order', ['address_id' => $address->id, 'grand_total' => 1, 'product_price' => 1, 'user_id' => $user->id])
            ->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 1]);

        $response = $this->withHeader('Idempotency-Key', '0c7b62e5-1cf3-42ed-832f-1f22acb20180')
            ->postJson('/api/payments/razorpay/create-order', ['address_id' => $address->id])
            ->assertCreated()->assertJsonPath('data.amount', 51950)->assertJsonPath('data.currency', 'INR')
            ->assertJsonPath('data.key_id', 'rzp_test_public')->assertJsonPath('data.razorpay_order_id', 'order_test_123');

        $orderId = $response->json('data.order_id');
        $this->assertDatabaseHas('payment_transactions', ['order_id' => $orderId, 'provider' => 'razorpay', 'provider_order_id' => 'order_test_123', 'amount' => 51950, 'status' => 'pending']);
        $this->assertSame(2, $product->fresh()->stock);
        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 1]);
    }

    public function test_valid_signature_finalizes_once_clears_the_cart_and_never_decrements_stock_twice(): void
    {
        [$user, $address, $product] = $this->checkoutData(['price' => 100, 'stock' => 5]);
        $gateway = $this->gateway();
        $gateway->shouldReceive('createOrder')->once()->andReturn(['id' => 'order_test_paid', 'amount' => 12000, 'currency' => 'INR']);
        $gateway->shouldReceive('publicKey')->once()->andReturn('rzp_test_public');
        $gateway->shouldReceive('verifyPaymentSignature')->once()->with('order_test_paid', 'pay_test_paid', 'signature')->andReturnNull();
        $attempt = $this->createAttempt($user, $address);
        $laterProduct = Product::factory()->create(['stock' => 3]);
        $this->postJson('/api/cart/items', ['product_id' => $laterProduct->id, 'quantity' => 1])->assertOk();
        $payload = ['order_id' => $attempt['order_id'], 'razorpay_order_id' => 'order_test_paid', 'razorpay_payment_id' => 'pay_test_paid', 'razorpay_signature' => 'signature'];

        $this->postJson('/api/payments/razorpay/verify', $payload)->assertOk()->assertJsonPath('data.payment_status', 'paid')->assertJsonPath('data.payment_method', 'razorpay');
        $this->postJson('/api/payments/razorpay/verify', $payload)->assertOk()->assertJsonPath('data.payment_status', 'paid');

        $this->assertDatabaseHas('payment_transactions', ['provider_order_id' => 'order_test_paid', 'provider_payment_id' => 'pay_test_paid', 'status' => 'paid']);
        $this->assertDatabaseMissing('cart_items', ['product_id' => $product->id]);
        $this->assertDatabaseHas('cart_items', ['product_id' => $laterProduct->id, 'quantity' => 1]);
        $this->assertSame(4, $product->fresh()->stock);
    }

    public function test_invalid_signature_wrong_provider_order_and_foreign_order_are_rejected_without_clearing_cart(): void
    {
        [$user, $address, $product] = $this->checkoutData();
        $gateway = $this->gateway();
        $gateway->shouldReceive('createOrder')->once()->andReturn(['id' => 'order_test_invalid', 'amount' => 12000, 'currency' => 'INR']);
        $gateway->shouldReceive('publicKey')->once()->andReturn('rzp_test_public');
        $gateway->shouldReceive('verifyPaymentSignature')->once()->andThrow(new SignatureVerificationError('Invalid signature'));
        $attempt = $this->createAttempt($user, $address);

        $this->postJson('/api/payments/razorpay/verify', ['order_id' => $attempt['order_id'], 'razorpay_order_id' => 'wrong_order', 'razorpay_payment_id' => 'pay_test_invalid', 'razorpay_signature' => 'bad'])->assertUnprocessable();
        $this->postJson('/api/payments/razorpay/verify', ['order_id' => $attempt['order_id'], 'razorpay_order_id' => 'order_test_invalid', 'razorpay_payment_id' => 'pay_test_invalid', 'razorpay_signature' => 'bad'])->assertUnprocessable();

        $otherUser = User::factory()->create();
        $this->actingAs($otherUser, 'api')->postJson('/api/payments/razorpay/verify', ['order_id' => $attempt['order_id'], 'razorpay_order_id' => 'order_test_invalid', 'razorpay_payment_id' => 'pay_test_invalid', 'razorpay_signature' => 'bad'])->assertUnprocessable();
        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 1]);
        $this->assertSame('pending', Order::findOrFail($attempt['order_id'])->payment_status);
    }

    public function test_client_payment_failure_restores_stock_once_and_keeps_the_cart(): void
    {
        [$user, $address, $product] = $this->checkoutData(['stock' => 2]);
        $gateway = $this->gateway();
        $gateway->shouldReceive('createOrder')->once()->andReturn(['id' => 'order_test_failed', 'amount' => 12000, 'currency' => 'INR']);
        $gateway->shouldReceive('publicKey')->once()->andReturn('rzp_test_public');
        $attempt = $this->createAttempt($user, $address);
        $payload = ['order_id' => $attempt['order_id'], 'razorpay_order_id' => 'order_test_failed', 'error_code' => 'payment_failed', 'error_description' => 'Declined'];

        $this->postJson('/api/payments/razorpay/fail', $payload)->assertOk()->assertJsonPath('data.payment_status', 'failed');
        $this->postJson('/api/payments/razorpay/fail', $payload)->assertOk()->assertJsonPath('data.payment_status', 'failed');
        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 1]);
        $this->assertSame(2, $product->fresh()->stock);
        $this->assertDatabaseHas('payment_transactions', ['provider_order_id' => 'order_test_failed', 'status' => 'failed']);
    }

    public function test_cancelling_a_pending_razorpay_order_releases_stock_and_prevents_late_finalization(): void
    {
        [$user, $address, $product] = $this->checkoutData(['stock' => 2]);
        $gateway = $this->gateway();
        $gateway->shouldReceive('createOrder')->once()->andReturn(['id' => 'order_test_cancelled', 'amount' => 12000, 'currency' => 'INR']);
        $gateway->shouldReceive('publicKey')->once()->andReturn('rzp_test_public');
        $gateway->shouldNotReceive('verifyPaymentSignature');
        $attempt = $this->createAttempt($user, $address);

        $this->app->make(UpdateOrderStatus::class)->handle(Order::findOrFail($attempt['order_id']), 'cancelled');

        $this->postJson('/api/payments/razorpay/verify', [
            'order_id' => $attempt['order_id'],
            'razorpay_order_id' => 'order_test_cancelled',
            'razorpay_payment_id' => 'pay_test_late',
            'razorpay_signature' => 'signature',
        ])->assertUnprocessable();
        $this->assertDatabaseHas('orders', ['id' => $attempt['order_id'], 'status' => 'cancelled', 'payment_status' => 'failed']);
        $this->assertSame(2, $product->fresh()->stock);
        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 1]);
    }

    public function test_verified_webhook_finalizes_idempotently_and_rejects_invalid_webhook_signatures(): void
    {
        [$user, $address, $product] = $this->checkoutData();
        $gateway = $this->gateway();
        $gateway->shouldReceive('createOrder')->once()->andReturn(['id' => 'order_test_webhook', 'amount' => 12000, 'currency' => 'INR']);
        $gateway->shouldReceive('publicKey')->once()->andReturn('rzp_test_public');
        $gateway->shouldReceive('verifyWebhookSignature')->twice()->andReturnNull();
        $attempt = $this->createAttempt($user, $address);
        $payload = ['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => ['id' => 'pay_test_webhook', 'order_id' => 'order_test_webhook', 'amount' => 12000, 'currency' => 'INR']]]];

        $this->postJson('/api/webhooks/razorpay', $payload, ['X-Razorpay-Signature' => 'valid'])->assertOk();
        $this->postJson('/api/webhooks/razorpay', $payload, ['X-Razorpay-Signature' => 'valid'])->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $attempt['order_id'], 'payment_status' => 'paid']);
        $this->assertSame(4, $product->fresh()->stock);
        $this->assertDatabaseMissing('cart_items', ['product_id' => $product->id]);

        $invalidGateway = Mockery::mock(RazorpayGateway::class);
        $invalidGateway->shouldReceive('verifyWebhookSignature')->once()->andThrow(new SignatureVerificationError('Invalid signature'));
        $this->app->instance(RazorpayGateway::class, $invalidGateway);
        $this->postJson('/api/webhooks/razorpay', $payload, ['X-Razorpay-Signature' => 'bad'])->assertUnauthorized();
    }

    public function test_signed_failed_webhook_restores_reserved_stock_and_keeps_the_cart(): void
    {
        [$user, $address, $product] = $this->checkoutData(['stock' => 2]);
        $gateway = $this->gateway();
        $gateway->shouldReceive('createOrder')->once()->andReturn(['id' => 'order_test_webhook_failed', 'amount' => 12000, 'currency' => 'INR']);
        $gateway->shouldReceive('publicKey')->once()->andReturn('rzp_test_public');
        $gateway->shouldReceive('verifyWebhookSignature')->once()->andReturnNull();
        $attempt = $this->createAttempt($user, $address);
        $payload = ['event' => 'payment.failed', 'payload' => ['payment' => ['entity' => [
            'order_id' => 'order_test_webhook_failed',
            'error_code' => 'BAD_REQUEST_ERROR',
            'error_description' => 'Payment declined',
        ]]]];

        $this->postJson('/api/webhooks/razorpay', $payload, ['X-Razorpay-Signature' => 'valid'])->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $attempt['order_id'], 'status' => 'cancelled', 'payment_status' => 'failed']);
        $this->assertSame(2, $product->fresh()->stock);
        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 1]);
    }

    /** @return array{0: User, 1: Address, 2: Product} */
    private function checkoutData(array $productAttributes = []): array
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create();
        $product = Product::factory()->create(array_merge(['price' => 100, 'stock' => 5], $productAttributes));
        $this->actingAs($user, 'api')->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();

        return [$user, $address, $product];
    }

    private function createAttempt(User $user, Address $address): array
    {
        return $this->actingAs($user, 'api')->withHeader('Idempotency-Key', fake()->uuid())
            ->postJson('/api/payments/razorpay/create-order', ['address_id' => $address->id])
            ->assertCreated()->json('data');
    }

    private function gateway(): MockInterface
    {
        $gateway = Mockery::mock(RazorpayGateway::class);
        $this->app->instance(RazorpayGateway::class, $gateway);

        return $gateway;
    }
}

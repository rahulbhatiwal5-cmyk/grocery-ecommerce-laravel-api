<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_routes_require_authentication(): void
    {
        $this->getJson('/api/orders')->assertUnauthorized();
        $this->postJson('/api/orders', ['address_id' => 1, 'payment_method' => 'cod'])->assertUnauthorized();
        $this->getJson('/api/orders/1')->assertUnauthorized();
    }

    public function test_empty_cart_is_rejected_without_creating_an_order(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create();

        $this->actingAs($user, 'api')
            ->postJson('/api/orders', ['address_id' => $address->id, 'payment_method' => 'cod'])
            ->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_valid_order_uses_current_prices_stores_snapshots_decrements_stock_and_clears_cart(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create([
            'full_name' => 'Priya Kapoor',
            'phone' => '+91 9123456789',
            'address_line_1' => '42 Market Road',
            'address_line_2' => 'Apartment 8B',
            'city' => 'Gurugram',
            'state' => 'Haryana',
            'postal_code' => '122001',
            'country' => 'India',
        ]);
        $product = Product::factory()->create([
            'name' => 'Farm Fresh Tomatoes',
            'unit' => '500 g',
            'price' => 49.50,
            'stock' => 8,
        ]);

        $this->actingAs($user, 'api')
            ->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2])
            ->assertOk();
        $product->update(['price' => 55.25]);

        $response = $this->withHeader('Idempotency-Key', '762f0d1a-3f56-489c-94fe-d4ba2e60a77a')
            ->postJson('/api/orders', ['address_id' => $address->id, 'payment_method' => 'cod'])
            ->assertCreated()
            ->assertJsonPath('data.subtotal', 110.5)
            ->assertJsonPath('data.delivery_charge', 20)
            ->assertJsonPath('data.grand_total', 130.5)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.payment_status', 'pending')
            ->assertJsonPath('data.payment_method', 'cod')
            ->assertJsonPath('data.item_count', 2)
            ->assertJsonPath('data.shipping_address.full_name', 'Priya Kapoor')
            ->assertJsonPath('data.items.0.product_name', 'Farm Fresh Tomatoes')
            ->assertJsonPath('data.items.0.unit_price', 55.25)
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.items.0.line_total', 110.5);

        $order = Order::query()->with('items')->findOrFail($response->json('data.id'));

        $this->assertSame(110.5, (float) $order->subtotal);
        $this->assertSame(20.0, (float) $order->delivery_charge);
        $this->assertSame(130.5, (float) $order->grand_total);
        $this->assertSame('Priya Kapoor', $order->shipping_full_name);
        $this->assertCount(1, $order->items);
        $this->assertSame('Farm Fresh Tomatoes', $order->items->first()->product_name);
        $this->assertSame(55.25, (float) $order->items->first()->unit_price);
        $this->assertSame(110.5, (float) $order->items->first()->line_total);
        $this->assertSame(6, $product->fresh()->stock);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_order_rejects_client_prices_and_uses_only_the_selected_address_and_payment_method(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create();
        $product = Product::factory()->create(['price' => 75, 'stock' => 5]);

        $this->actingAs($user, 'api')
            ->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])
            ->assertOk();

        $this->postJson('/api/orders', [
            'address_id' => $address->id,
            'payment_method' => 'cod',
            'subtotal' => 0.01,
            'delivery_charge' => 0,
            'grand_total' => 0.01,
            'user_id' => $user->id,
        ])->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 1]);
    }

    public function test_order_rejects_another_users_address_and_preserves_the_cart(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $bobsAddress = Address::factory()->for($bob)->create();
        $product = Product::factory()->create(['stock' => 5]);

        $this->actingAs($alice, 'api')
            ->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])
            ->assertOk();

        $this->postJson('/api/orders', ['address_id' => $bobsAddress->id, 'payment_method' => 'cod'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('address_id');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 1]);
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_insufficient_stock_rolls_back_order_and_preserves_cart(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create();
        $product = Product::factory()->create(['stock' => 2]);

        $this->actingAs($user, 'api')
            ->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2])
            ->assertOk();
        $product->update(['stock' => 1]);

        $this->postJson('/api/orders', ['address_id' => $address->id, 'payment_method' => 'cod'])
            ->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1, $product->fresh()->stock);
        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 2]);
    }

    public function test_inactive_product_is_rejected_and_order_item_snapshots_survive_product_changes(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create();
        $inactive = Product::factory()->create(['stock' => 3]);

        $this->actingAs($user, 'api')
            ->postJson('/api/cart/items', ['product_id' => $inactive->id, 'quantity' => 1])
            ->assertOk();
        $inactive->update(['status' => false]);

        $this->postJson('/api/orders', ['address_id' => $address->id, 'payment_method' => 'cod'])
            ->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('cart_items', ['product_id' => $inactive->id, 'quantity' => 1]);

        $active = Product::factory()->create([
            'name' => 'Original Rice',
            'unit' => '1 kg',
            'price' => 99,
            'stock' => 3,
        ]);
        $this->postJson('/api/cart/items', ['product_id' => $active->id, 'quantity' => 1])->assertOk();
        $this->deleteJson('/api/cart/items/'.$inactive->id)->assertOk();

        $orderResponse = $this->postJson('/api/orders', ['address_id' => $address->id, 'payment_method' => 'cod'])
            ->assertCreated();
        $orderId = $orderResponse->json('data.id');

        $active->update(['name' => 'Renamed Rice', 'unit' => '2 kg', 'price' => 125]);
        $this->getJson('/api/orders/'.$orderId)
            ->assertOk()
            ->assertJsonPath('data.items.0.product_name', 'Original Rice')
            ->assertJsonPath('data.items.0.unit', '1 kg')
            ->assertJsonPath('data.items.0.unit_price', 99)
            ->assertJsonPath('data.items.0.line_total', 99);

    }

    public function test_user_can_list_and_view_only_their_own_orders(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $alicesAddress = Address::factory()->for($alice)->create();
        $bobsAddress = Address::factory()->for($bob)->create();
        $alicesProduct = Product::factory()->create(['stock' => 4]);
        $bobsProduct = Product::factory()->create(['stock' => 4]);

        $aliceOrder = $this->placeOrder($alice, $alicesAddress, $alicesProduct, 2);
        $bobOrder = $this->placeOrder($bob, $bobsAddress, $bobsProduct, 1);

        $this->actingAs($alice, 'api')
            ->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $aliceOrder)
            ->assertJsonPath('data.0.item_count', 2);

        $this->getJson('/api/orders/'.$aliceOrder)->assertOk()->assertJsonPath('data.id', $aliceOrder);
        $this->getJson('/api/orders/'.$bobOrder)->assertNotFound();
    }

    public function test_repeated_idempotency_key_returns_the_existing_order_without_creating_a_second_order(): void
    {
        $user = User::factory()->create();
        $firstAddress = Address::factory()->for($user)->create();
        $secondAddress = Address::factory()->for($user)->create();
        $product = Product::factory()->create(['stock' => 5]);
        $key = 'd8b25495-9463-4fa0-8f45-c3257b7ec239';

        $this->actingAs($user, 'api')
            ->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])
            ->assertOk();

        $payload = ['address_id' => $firstAddress->id, 'payment_method' => 'cod'];
        $first = $this->withHeader('Idempotency-Key', $key)->postJson('/api/orders', $payload)->assertCreated();
        $orderId = $first->json('data.id');

        $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/orders', $payload)
            ->assertSuccessful()
            ->assertJsonPath('data.id', $orderId);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertSame(4, $product->fresh()->stock);

        $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/orders', ['address_id' => $secondAddress->id, 'payment_method' => 'cod'])
            ->assertUnprocessable();

        $this->assertDatabaseCount('orders', 1);
    }

    private function placeOrder(User $user, Address $address, Product $product, int $quantity): int
    {
        $this->actingAs($user, 'api')
            ->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => $quantity])
            ->assertOk();

        return $this->postJson('/api/orders', ['address_id' => $address->id, 'payment_method' => 'cod'])
            ->assertCreated()
            ->json('data.id');
    }
}

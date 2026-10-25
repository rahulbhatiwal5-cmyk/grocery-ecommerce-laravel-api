<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_routes_require_authentication(): void
    {
        $this->getJson('/api/cart')->assertUnauthorized();
        $this->postJson('/api/cart/items', ['product_id' => 1, 'quantity' => 1])->assertUnauthorized();
        $this->patchJson('/api/cart/items/1', ['quantity' => 1])->assertUnauthorized();
        $this->deleteJson('/api/cart/items/1')->assertUnauthorized();
        $this->deleteJson('/api/cart')->assertUnauthorized();
        $this->postJson('/api/cart/merge', ['items' => []])->assertUnauthorized();
    }

    public function test_cart_add_update_remove_and_clear_use_current_server_prices(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 56.25, 'stock' => 20]);
        $this->actingAs($user, 'api')->getJson('/api/cart')->assertOk()->assertJsonPath('data.cart_count', 0)->assertJsonPath('data.grand_total', 0);
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2, 'price' => 0])->assertOk()->assertJsonPath('data.cart_count', 2)->assertJsonPath('data.subtotal', 112.5)->assertJsonPath('data.delivery_charge', 20)->assertJsonPath('data.grand_total', 132.5);
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.quantity', 3);
        $this->assertDatabaseCount('cart_items', 1);
        $product->update(['price' => 60]);
        $this->patchJson('/api/cart/items/'.$product->id, ['quantity' => 4])->assertOk()->assertJsonPath('data.items.0.line_total', 240)->assertJsonPath('data.grand_total', 260);
        $this->deleteJson('/api/cart/items/'.$product->id)->assertOk()->assertJsonCount(0, 'data.items')->assertJsonPath('data.delivery_charge', 0);
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();
        $this->deleteJson('/api/cart')->assertOk()->assertJsonPath('data.cart_count', 0);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_invalid_quantities_missing_inactive_and_out_of_stock_products_are_rejected(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 2]);
        $inactive = Product::factory()->create(['status' => false]);
        $category = Category::factory()->create(['status' => false]);
        $hidden = Product::factory()->for($category)->create();
        $this->actingAs($user, 'api');
        foreach ([0, -1, 1.5, 'abc'] as $quantity) {
            $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => $quantity])->assertUnprocessable();
        }
        foreach ([999999, $inactive->id, $hidden->id] as $id) {
            $this->postJson('/api/cart/items', ['product_id' => $id, 'quantity' => 1])->assertUnprocessable();
        }
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 3])->assertUnprocessable();
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2])->assertOk();
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertUnprocessable();
        $this->patchJson('/api/cart/items/'.$product->id, ['quantity' => 0])->assertUnprocessable();
        $this->deleteJson('/api/cart/items/'.$product->id.'abc')->assertNotFound();
        $this->getJson('/api/cart')->assertJsonPath('data.items.0.quantity', 2);
    }

    public function test_cart_is_scoped_to_authenticated_user(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $product = Product::factory()->create();
        $this->actingAs($alice, 'api')->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1, 'user_id' => $bob->id])->assertOk();
        $this->actingAs($bob, 'api')->getJson('/api/cart')->assertJsonCount(0, 'data.items');
        $this->patchJson('/api/cart/items/'.$product->id, ['quantity' => 2])->assertNotFound();
        $this->deleteJson('/api/cart/items/'.$product->id)->assertNotFound();
        $this->deleteJson('/api/cart')->assertOk();
        $this->actingAs($alice, 'api')->getJson('/api/cart')->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.quantity', 1);
    }

    public function test_guest_merge_is_atomic_and_idempotent(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 20]);
        $other = Product::factory()->create(['stock' => 20]);
        $this->actingAs($user, 'api')->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2])->assertOk();
        $key = '357dd5c9-630b-4cd3-9160-8685cb0db6aa';
        $payload = ['items' => [['product_id' => $product->id, 'quantity' => 3], ['product_id' => $other->id, 'quantity' => 1]]];
        $this->withHeader('Idempotency-Key', $key)->postJson('/api/cart/merge', $payload)->assertOk()->assertJsonPath('data.cart_count', 6)->assertJsonCount(2, 'data.items');
        $this->postJson('/api/cart/merge', $payload)->assertOk()->assertJsonPath('data.cart_count', 6);
        $this->assertDatabaseCount('cart_items', 2);
        $this->postJson('/api/cart/merge', ['items' => [['product_id' => $product->id, 'quantity' => 1]]])->assertUnprocessable();
        $this->withHeader('Idempotency-Key', 'fd057bd8-4fa6-4498-9a89-4f2ada56f734')->postJson('/api/cart/merge', ['items' => [['product_id' => $other->id, 'quantity' => 1], ['product_id' => 99999, 'quantity' => 1]]])->assertUnprocessable();
        $this->getJson('/api/cart')->assertJsonPath('data.cart_count', 6);
        $this->postJson('/api/cart/merge', ['items' => [['product_id' => $other->id, 'quantity' => 1, 'price' => 1]]])->assertUnprocessable();
        $this->postJson('/api/cart/merge', ['items' => [['product_id' => $other->id, 'quantity' => 1]], 'user_id' => $user->id])->assertUnprocessable();
    }
}

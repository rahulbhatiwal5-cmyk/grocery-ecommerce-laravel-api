<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WishlistTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_wishlist_routes_require_authentication(): void
    {
        $this->getJson('/api/wishlist')->assertUnauthorized();
        $this->postJson('/api/wishlist/items', ['product_id' => 1])->assertUnauthorized();
        $this->deleteJson('/api/wishlist/items/1')->assertUnauthorized();
        $this->deleteJson('/api/wishlist')->assertUnauthorized();
        $this->postJson('/api/wishlist/merge', ['items' => [['product_id' => 1]]])->assertUnauthorized();
    }

    public function test_add_is_idempotent_and_remove_and_clear_only_wishlist_rows(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 10]);
        $other = Product::factory()->create(['stock' => 10]);
        $this->actingAs($user, 'api')->getJson('/api/wishlist')->assertOk()->assertJsonPath('data.wishlist_count', 0)->assertJsonCount(0, 'data.items');
        $this->postJson('/api/wishlist/items', ['product_id' => $product->id])->assertOk()->assertJsonPath('data.items.0.product.id', $product->id)->assertJsonPath('data.wishlist_count', 1);
        $this->postJson('/api/wishlist/items', ['product_id' => $product->id])->assertOk()->assertJsonPath('data.wishlist_count', 1);
        $this->assertDatabaseCount('wishlist_items', 1);
        $product->update(['price' => 125]);
        $this->getJson('/api/wishlist')->assertOk()->assertJsonPath('data.items.0.product.price', 125);
        $this->postJson('/api/wishlist/items', ['product_id' => $other->id])->assertOk()->assertJsonPath('data.wishlist_count', 2);
        $this->deleteJson('/api/wishlist/items/'.$product->id)->assertOk()->assertJsonPath('data.wishlist_count', 1);
        $this->deleteJson('/api/wishlist')->assertOk()->assertJsonCount(0, 'data.items');
        $this->assertDatabaseCount('wishlist_items', 0);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
        $this->assertDatabaseHas('products', ['id' => $other->id]);
    }

    public function test_unavailable_products_and_untrusted_fields_are_rejected(): void
    {
        $user = User::factory()->create();
        $active = Product::factory()->create(['stock' => 1]);
        $inactive = Product::factory()->create(['status' => false]);
        $out = Product::factory()->create(['stock' => 0]);
        $category = Category::factory()->create(['status' => false]);
        $hidden = Product::factory()->for($category)->create();
        $this->actingAs($user, 'api');
        foreach ([999999, $inactive->id, $out->id, $hidden->id] as $id) {
            $this->postJson('/api/wishlist/items', ['product_id' => $id])->assertUnprocessable();
        }
        $this->postJson('/api/wishlist/items', ['product_id' => $active->id, 'user_id' => $user->id])->assertUnprocessable();
        $this->postJson('/api/wishlist/items', ['product_id' => $active->id, 'price' => 0])->assertUnprocessable();
        $this->postJson('/api/wishlist/merge', ['items' => [['product_id' => $active->id, 'name' => 'fake']]])->assertUnprocessable();
        $this->postJson('/api/wishlist/merge', ['items' => [['product_id' => $active->id]], 'user_id' => $user->id])->assertUnprocessable();
        $this->getJson('/api/wishlist')->assertJsonPath('data.wishlist_count', 0);
    }

    public function test_wishlist_is_scoped_to_authenticated_user(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $product = Product::factory()->create();
        $this->actingAs($alice, 'api')->postJson('/api/wishlist/items', ['product_id' => $product->id])->assertOk();
        $this->actingAs($bob, 'api')->getJson('/api/wishlist')->assertJsonCount(0, 'data.items');
        $this->deleteJson('/api/wishlist/items/'.$product->id)->assertNotFound();
        $this->deleteJson('/api/wishlist')->assertOk();
        $this->actingAs($alice, 'api')->getJson('/api/wishlist')->assertJsonCount(1, 'data.items');
    }

    public function test_merge_is_atomic_and_repeated_requests_do_not_duplicate_products(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $other = Product::factory()->create();
        $this->actingAs($user, 'api')->postJson('/api/wishlist/items', ['product_id' => $product->id])->assertOk();
        $payload = ['items' => [['product_id' => $product->id], ['product_id' => $other->id], ['product_id' => $other->id]]];
        $this->postJson('/api/wishlist/merge', $payload)->assertOk()->assertJsonPath('data.wishlist_count', 2);
        $this->postJson('/api/wishlist/merge', $payload)->assertOk()->assertJsonPath('data.wishlist_count', 2);
        $this->assertDatabaseCount('wishlist_items', 2);
        $fresh = Product::factory()->create();
        $this->postJson('/api/wishlist/merge', ['items' => [['product_id' => $fresh->id], ['product_id' => 999999]]])->assertUnprocessable();
        $this->getJson('/api/wishlist')->assertJsonPath('data.wishlist_count', 2);
        $this->assertDatabaseMissing('wishlist_items', ['product_id' => $fresh->id]);
    }

    public function test_deleting_a_product_or_user_removes_related_wishlist_rows(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $this->actingAs($user, 'api')->postJson('/api/wishlist/items', ['product_id' => $product->id])->assertOk();
        $wishlistId = $user->wishlist()->firstOrFail()->id;
        $product->delete();
        $this->assertDatabaseCount('wishlist_items', 0);
        $another = Product::factory()->create();
        $this->postJson('/api/wishlist/items', ['product_id' => $another->id])->assertOk();
        $user->delete();
        $this->assertDatabaseMissing('wishlists', ['id' => $wishlistId]);
        $this->assertDatabaseCount('wishlist_items', 0);
        $this->assertDatabaseHas('products', ['id' => $another->id]);
    }
}

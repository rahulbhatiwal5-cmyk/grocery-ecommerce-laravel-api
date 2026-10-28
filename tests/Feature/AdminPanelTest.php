<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_the_admin_login(): void
    {
        $this->get('/admin/dashboard')->assertRedirect(route('admin.login'));
    }

    public function test_normal_customer_cannot_access_admin_dashboard_or_orders(): void
    {
        $customer = User::factory()->create();
        $order = Order::factory()->for($customer)->create();

        $this->actingAs($customer)
            ->get('/admin/dashboard')
            ->assertForbidden();
        $this->get('/admin/orders/'.$order->id)->assertForbidden();
    }

    public function test_admin_can_sign_in_and_access_the_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin, 'web');
        $this->get('/admin/dashboard')->assertOk()->assertSee('Recent Orders');
    }

    public function test_admin_can_list_create_update_and_protect_categories(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create(['name' => 'Old Category', 'slug' => 'old-category']);

        $this->actingAs($admin)
            ->get('/admin/categories')
            ->assertOk()
            ->assertSee('Old Category');
        $this->post('/admin/categories', $this->categoryPayload(['name' => 'Fresh Produce']))
            ->assertRedirect(route('admin.categories.index'));
        $this->assertDatabaseHas('categories', ['name' => 'Fresh Produce', 'slug' => 'fresh-produce', 'status' => 1]);
        $this->put('/admin/categories/'.$category->id, $this->categoryPayload(['name' => 'Renamed Category', 'status' => 0]))
            ->assertRedirect(route('admin.categories.index'));
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Renamed Category', 'slug' => 'renamed-category', 'status' => 0]);

        $category->products()->create(Product::factory()->make()->toArray());
        $this->delete('/admin/categories/'.$category->id)->assertRedirect();
        $this->assertModelExists($category);
    }

    public function test_admin_can_list_create_and_update_product_stock(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();
        $existing = Product::factory()->for($category)->create(['name' => 'Existing Product']);

        $this->actingAs($admin)
            ->get('/admin/products')
            ->assertOk()
            ->assertSee('Existing Product');
        $this->post('/admin/products', $this->productPayload($category, ['name' => 'New Product', 'stock' => 5]))
            ->assertRedirect(route('admin.products.index'));
        $product = Product::query()->where('name', 'New Product')->firstOrFail();
        $this->assertSame(5, $product->stock);
        $this->put('/admin/products/'.$product->id, $this->productPayload($category, ['name' => 'New Product', 'stock' => 24]))
            ->assertRedirect(route('admin.products.index'));
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 24]);
    }

    public function test_admin_product_validation_rejects_negative_price_and_stock(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin)
            ->from('/admin/products/create')
            ->post('/admin/products', $this->productPayload($category, ['price' => -1, 'stock' => -1]))
            ->assertRedirect('/admin/products/create')
            ->assertSessionHasErrors(['price', 'stock']);

        $this->assertDatabaseMissing('products', ['name' => 'Admin Product']);
    }

    public function test_admin_can_list_view_and_update_order_status(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['name' => 'Order Customer']);
        $order = Order::factory()->for($customer)->create(['status' => 'pending']);
        $product = Product::factory()->create(['stock' => 7]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit' => $product->unit,
            'unit_price' => 100,
            'quantity' => 2,
            'line_total' => 200,
        ]);

        $this->actingAs($admin)
            ->get('/admin/orders')
            ->assertOk()
            ->assertSee('Order Customer');
        $this->get('/admin/orders/'.$order->id)->assertOk()->assertSee($product->name);
        $this->patch('/admin/orders/'.$order->id.'/status', ['status' => 'confirmed'])
            ->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'confirmed']);
        $this->patch('/admin/orders/'.$order->id.'/status', ['status' => 'unknown'])
            ->assertSessionHasErrors('status');
    }

    public function test_cancelling_an_order_restores_stock_only_once_and_cannot_be_reopened(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $order = Order::factory()->for($customer)->create(['status' => 'processing']);
        $product = Product::factory()->create(['stock' => 3]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit' => $product->unit,
            'unit_price' => 100,
            'quantity' => 2,
            'line_total' => 200,
        ]);

        $this->actingAs($admin)
            ->patch('/admin/orders/'.$order->id.'/status', ['status' => 'cancelled'])
            ->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled']);
        $this->assertSame(5, $product->fresh()->stock);
        $this->patch('/admin/orders/'.$order->id.'/status', ['status' => 'cancelled'])->assertRedirect();
        $this->assertSame(5, $product->fresh()->stock);
        $this->patch('/admin/orders/'.$order->id.'/status', ['status' => 'confirmed'])->assertSessionHasErrors('status');
    }

    public function test_admin_can_list_customers_without_exposing_administrator_accounts(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Hidden Admin']);
        $customer = User::factory()->create(['name' => 'Visible Customer']);

        $this->actingAs($admin)
            ->get('/admin/customers')
            ->assertOk()
            ->assertSee('Visible Customer')
            ->assertDontSee($admin->email);
        $this->get('/admin/customers/'.$customer->id)->assertOk()->assertSee($customer->email);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function categoryPayload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Admin Category',
            'description' => 'Managed by an administrator.',
            'status' => 1,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function productPayload(Category $category, array $overrides = []): array
    {
        return array_replace([
            'category_id' => $category->id,
            'name' => 'Admin Product',
            'description' => 'Managed product description.',
            'unit' => '1 kg',
            'price' => 99.5,
            'old_price' => 120,
            'stock' => 10,
            'status' => 1,
            'image_url' => 'https://example.test/product.jpg',
        ], $overrides);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_catalog_supports_search_sort_and_detail(): void
    {
        $this->seed(CatalogSeeder::class);
        $this->getJson('/api/products?search=milk')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Amul Gold Milk');
        $this->getJson('/api/products?category=dairy&sort=price_desc')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.name', 'Farm Fresh Eggs');
        $this->getJson('/api/products?sort=price_asc')->assertOk()->assertJsonPath('data.0.name', 'Classic Snacks');
        $this->getJson('/api/products/amul-gold-milk')->assertOk()->assertJsonPath('data.name', 'Amul Gold Milk');
        $milk = Product::where('slug', 'amul-gold-milk')->firstOrFail();
        $this->getJson('/api/products/'.$milk->id)->assertOk()->assertJsonPath('data.name', 'Amul Gold Milk');
        $this->getJson('/api/categories')->assertOk()->assertJsonCount(6, 'data');
        $this->getJson('/api/categories/dairy')->assertOk()->assertJsonCount(2, 'data.products')->assertJsonPath('data.products.0.category', 'dairy');
        $this->getJson('/api/products/999999')->assertNotFound();
        $this->getJson('/api/categories/missing')->assertNotFound();
        $this->getJson('/api/products?sort=price;drop')->assertUnprocessable();
    }

    public function test_inactive_products_and_categories_are_hidden(): void
    {
        $active = Category::factory()->create();
        $inactive = Category::factory()->create(['status' => false]);
        $hidden = Product::factory()->for($active)->create(['status' => false]);
        $inHiddenCategory = Product::factory()->for($inactive)->create();
        $this->getJson('/api/products')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/products/'.$hidden->id)->assertNotFound();
        $this->getJson('/api/products/'.$inHiddenCategory->id)->assertNotFound();
        $this->getJson('/api/categories/'.$inactive->slug)->assertNotFound();
    }

    public function test_jwt_registration_login_refresh_me_and_logout(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
        $registration = $this->postJson('/api/register', ['name' => 'Jane', 'email' => 'jane@example.com', 'password' => 'password123', 'password_confirmation' => 'password123'])->assertCreated();
        $token = $registration->json('access_token');
        $this->withToken($token)->getJson('/api/me')->assertOk()->assertJsonPath('user.email', 'jane@example.com');
        $refreshed = $this->withToken($token)->postJson('/api/refresh')->assertOk()->json('access_token');
        app('tymon.jwt')->unsetToken();
        app('tymon.jwt.manager')->setRefreshFlow(false);
        auth()->forgetGuards();
        $this->withToken($refreshed)->getJson('/api/me')->assertOk();
        app('tymon.jwt')->unsetToken();
        app('tymon.jwt.manager')->setRefreshFlow(false);
        auth()->forgetGuards();
        $this->withToken($refreshed)->postJson('/api/logout')->assertOk();
        app('tymon.jwt')->unsetToken();
        app('tymon.jwt.manager')->setRefreshFlow(false);
        auth()->forgetGuards();
        $this->withToken($refreshed)->getJson('/api/me')->assertUnauthorized();
        $login = $this->postJson('/api/login', ['email' => 'jane@example.com', 'password' => 'password123'])->assertOk();
        $this->assertNotEmpty($login->json('access_token'));
        $this->postJson('/api/login', ['email' => 'jane@example.com', 'password' => 'wrong'])->assertUnauthorized();
        app('tymon.jwt')->unsetToken();
        app('tymon.jwt.manager')->setRefreshFlow(false);
        auth()->forgetGuards();
        $this->withToken('invalid')->postJson('/api/refresh')->assertUnauthorized();
    }

    public function test_expired_jwt_can_be_refreshed_within_the_refresh_window(): void
    {
        config(['jwt.ttl' => 1, 'jwt.refresh_ttl' => 60]);
        $user = User::factory()->create();
        $token = auth('api')->login($user);
        $this->travel(2)->minutes();
        app('tymon.jwt')->unsetToken();
        app('tymon.jwt.manager')->setRefreshFlow(false);
        auth()->forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
        $this->withToken($token)->postJson('/api/refresh')->assertOk()->assertJsonStructure(['access_token']);
    }

    public function test_cors_preflight_allows_local_frontend_authorization_header(): void
    {
        $this->call('OPTIONS', '/api/products', [], [], [], [
            'HTTP_ORIGIN' => 'http://localhost:5173',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization',
        ])->assertNoContent()->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertHeader('Access-Control-Allow-Headers', 'authorization');
    }

    public function test_refresh_rejects_token_outside_the_refresh_window(): void
    {
        config(['jwt.ttl' => 1, 'jwt.refresh_ttl' => 2]);
        $token = auth('api')->login(User::factory()->create());
        $this->travel(3)->minutes();
        app('tymon.jwt')->unsetToken();
        auth()->forgetGuards();
        $this->withToken($token)->postJson('/api/refresh')->assertUnauthorized();
    }
}

<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WishlistItem> */
class WishlistItemFactory extends Factory
{
    public function definition(): array
    {
        return ['wishlist_id' => Wishlist::factory(), 'product_id' => Product::factory()];
    }
}

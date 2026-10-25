<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return ['category_id' => Category::factory(), 'name' => $name, 'slug' => Str::slug($name), 'description' => fake()->sentence(), 'unit' => '1 kg', 'price' => 100, 'old_price' => 120, 'stock' => 10, 'status' => true];
    }
}

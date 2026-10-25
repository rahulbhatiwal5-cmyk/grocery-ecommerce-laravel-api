<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['produce', 'Fruits & Vegetables', 'Fresh produce'], ['dairy', 'Dairy & Eggs', 'Daily essentials'], ['staples', 'Atta, Rice & Dal', 'Pantry basics'], ['snacks', 'Snacks & Munchies', 'Crunchy favourites'], ['beverages', 'Oil, Tea & Beverages', 'Everyday drinks'], ['care', 'Personal Care', 'Daily care essentials'],
        ];
        foreach ($categories as [$slug, $name, $description]) {
            Category::firstOrCreate(['slug' => $slug], ['name' => $name, 'description' => $description, 'status' => true]);
        }
        $products = [
            ['Amul Gold Milk', '1 L', 56, 62, 'dairy'], ['Aashirvaad Atta', '5 kg', 249, 285, 'staples'], ['Fortune Sunlite Oil', '1 L', 135, 152, 'staples'], ['Fresh Red Apples', '1 kg', 129, 149, 'produce'], ['Classic Snacks', '120 g', 35, 45, 'snacks'], ['Fresh Broccoli', '500 g', 69, 82, 'produce'], ['Farm Fresh Eggs', '12 pcs', 99, 112, 'dairy'], ['Bru Instant Coffee', '100 g', 109, 125, 'beverages'], ['Premium Rice', '5 kg', 399, 450, 'staples'], ['Fresh Orange Juice', '1 L', 119, 140, 'beverages'], ['Personal Care Pack', '1 pack', 249, 299, 'care'], ['Snack Mix', '200 g', 89, 105, 'snacks'],
        ];
        $ids = Category::pluck('id', 'slug');
        foreach ($products as [$name, $unit, $price, $oldPrice, $category]) {
            Product::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'unit' => $unit, 'price' => $price, 'old_price' => $oldPrice, 'category_id' => $ids[$category], 'stock' => 100, 'status' => true]);
        }
    }
}

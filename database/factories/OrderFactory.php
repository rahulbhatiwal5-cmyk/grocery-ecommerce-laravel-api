<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'shipping_full_name' => fake()->name(),
            'shipping_phone' => fake()->numerify('##########'),
            'shipping_address_line_1' => fake()->streetAddress(),
            'shipping_address_line_2' => fake()->optional()->secondaryAddress(),
            'shipping_city' => fake()->city(),
            'shipping_state' => fake()->state(),
            'shipping_postal_code' => fake()->postcode(),
            'shipping_country' => 'India',
            'subtotal' => 100,
            'delivery_charge' => 20,
            'grand_total' => 120,
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'cod',
            'placed_at' => now(),
        ];
    }
}

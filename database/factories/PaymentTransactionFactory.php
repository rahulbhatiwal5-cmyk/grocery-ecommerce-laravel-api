<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentTransaction>
 */
class PaymentTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'provider' => 'razorpay',
            'provider_order_id' => 'order_test_'.$this->faker->unique()->bothify('############'),
            'amount' => 12000,
            'currency' => 'INR',
            'status' => 'pending',
        ];
    }
}

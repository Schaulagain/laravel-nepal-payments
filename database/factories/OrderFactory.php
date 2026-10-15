<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Product;
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
            'product_id' => Product::factory(),
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'amount' => fn (array $attributes): int => Product::find($attributes['product_id'])->price,
        ];
    }
}

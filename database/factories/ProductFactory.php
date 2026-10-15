<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['VPS Starter', 'Domain Registration', 'Business Email']),
            'category' => fake()->randomElement(['vps', 'domain', 'email']),
            'description' => fake()->sentence(),
            'price' => fake()->numberBetween(500, 5000) * 100,
        ];
    }
}

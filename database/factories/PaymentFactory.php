<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
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
            'gateway' => 'esewa',
            'amount' => fn (array $attributes): int => Order::find($attributes['order_id'])->amount,
            'status' => PaymentStatus::Pending,
        ];
    }

    public function esewa(): static
    {
        return $this->state(fn (array $attributes): array => ['gateway' => 'esewa']);
    }

    public function khalti(): static
    {
        return $this->state(fn (array $attributes): array => [
            'gateway' => 'khalti',
            'external_id' => Str::random(22),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PaymentStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}

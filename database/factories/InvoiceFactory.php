<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory()->completed(),
            'order_id' => fn (array $attributes): int => Payment::find($attributes['payment_id'])->order_id,
            'amount' => fn (array $attributes): int => Payment::find($attributes['payment_id'])->amount,
            'issued_at' => now(),
        ];
    }
}

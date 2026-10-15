<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_store_lists_products(): void
    {
        Product::factory()->create(['name' => 'VPS Starter', 'price' => 150000]);

        $this->get(route('store.index'))
            ->assertOk()
            ->assertSee('VPS Starter')
            ->assertSee('Rs 1,500.00');
    }

    public function test_checkout_requires_customer_details_and_a_known_gateway(): void
    {
        $product = Product::factory()->create();

        $this->post(route('checkout.store', $product), ['gateway' => 'paypal'])
            ->assertSessionHasErrors(['customer_name', 'customer_email', 'gateway']);

        $this->assertSame(0, Payment::count());
    }

    public function test_invoice_is_only_available_for_completed_payments(): void
    {
        $payment = Payment::factory()->create();

        $this->get(route('payments.invoice', $payment))->assertNotFound();
    }
}

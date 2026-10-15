<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KhaltiPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['payments.gateways.khalti.secret_key' => 'test_secret_key']);
    }

    public function test_checkout_initiates_a_payment_and_redirects_to_khalti(): void
    {
        Http::fake([
            'dev.khalti.com/api/v2/epayment/initiate/' => Http::response([
                'pidx' => 'bZQLD9wRVWo4CdESSfuSsB',
                'payment_url' => 'https://test-pay.khalti.com/?pidx=bZQLD9wRVWo4CdESSfuSsB',
                'expires_in' => 1800,
            ]),
        ]);
        $product = Product::factory()->create(['price' => 80000, 'name' => 'Business Email']);

        $this->post(route('checkout.store', $product), [
            'customer_name' => 'Bikash Thapa',
            'customer_email' => 'bikash@example.com',
            'gateway' => 'khalti',
        ])->assertRedirect('https://test-pay.khalti.com/?pidx=bZQLD9wRVWo4CdESSfuSsB');

        $payment = Payment::sole();
        $this->assertSame('bZQLD9wRVWo4CdESSfuSsB', $payment->external_id);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Key test_secret_key')
            && $request['amount'] === 80000
            && $request['purchase_order_id'] === $payment->uuid
            && $request['return_url'] === route('payments.callback', $payment));
    }

    public function test_gateway_errors_during_checkout_fail_the_payment_gracefully(): void
    {
        Http::fake(['dev.khalti.com/*' => Http::response(['detail' => 'Invalid token.'], 401)]);
        $product = Product::factory()->create();

        $this->from(route('checkout.create', $product))
            ->post(route('checkout.store', $product), [
                'customer_name' => 'Bikash Thapa',
                'customer_email' => 'bikash@example.com',
                'gateway' => 'khalti',
            ])
            ->assertRedirect(route('checkout.create', $product))
            ->assertSessionHas('error');

        $this->assertSame(PaymentStatus::Failed, Payment::sole()->status);
    }

    public function test_callback_is_confirmed_with_the_lookup_api(): void
    {
        $payment = Payment::factory()->khalti()->create(['amount' => 80000]);
        Http::fake([
            'dev.khalti.com/api/v2/epayment/lookup/' => Http::response([
                'pidx' => $payment->external_id,
                'total_amount' => 80000,
                'status' => 'Completed',
                'transaction_id' => 'GFq9PFS7b2iYvL8Lir9oXe',
                'fee' => 0,
                'refunded' => false,
            ]),
        ]);

        // Khalti's own callback parameters are ignored apart from pidx; only the lookup counts.
        $this->get(route('payments.callback', ['payment' => $payment, 'pidx' => $payment->external_id, 'status' => 'Completed']))
            ->assertRedirect(route('payments.show', $payment));

        $payment->refresh();
        $this->assertSame(PaymentStatus::Completed, $payment->status);
        $this->assertSame('GFq9PFS7b2iYvL8Lir9oXe', $payment->transaction_reference);
        $this->assertNotNull($payment->invoice);
    }

    public function test_cancelled_payment_is_marked_failed(): void
    {
        $payment = Payment::factory()->khalti()->create(['amount' => 80000]);
        Http::fake([
            'dev.khalti.com/api/v2/epayment/lookup/' => Http::response([
                'pidx' => $payment->external_id,
                'total_amount' => 80000,
                'status' => 'User canceled',
                'transaction_id' => null,
            ], 400),
        ]);

        $this->get(route('payments.callback', ['payment' => $payment, 'pidx' => $payment->external_id]));

        $payment->refresh();
        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertSame('khalti_user_canceled', $payment->failure_reason);
    }

    public function test_callback_with_a_different_pidx_is_rejected_without_calling_khalti(): void
    {
        Http::fake();
        $payment = Payment::factory()->khalti()->create();

        $this->get(route('payments.callback', ['payment' => $payment, 'pidx' => 'someone-elses-pidx']))
            ->assertSessionHas('error');

        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
        Http::assertNothingSent();
    }
}

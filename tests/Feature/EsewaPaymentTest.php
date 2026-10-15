<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Product;
use App\Payments\GatewayManager;
use App\Payments\Gateways\EsewaGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EsewaPaymentTest extends TestCase
{
    use RefreshDatabase;

    private const SIGNED_RESPONSE_FIELDS = 'transaction_code,status,total_amount,transaction_uuid,product_code,signed_field_names';

    public function test_signature_matches_the_example_in_esewas_documentation(): void
    {
        // Success response example from eSewa's ePay v2 docs, as json_decode() returns it.
        $payload = json_decode('{"transaction_code":"000AWEO","status":"COMPLETE","total_amount":1000.0,"transaction_uuid":"250610-162413","product_code":"EPAYTEST","signed_field_names":"'.self::SIGNED_RESPONSE_FIELDS.'"}', true);

        $signature = $this->esewa()->sign($payload, self::SIGNED_RESPONSE_FIELDS);

        $this->assertSame('62GcfZTmVkzhtUeh+QJ1AqiJrjoWWGof3U+eTPTZ7fA=', $signature);
    }

    public function test_checkout_posts_signed_fields_to_the_esewa_form(): void
    {
        $product = Product::factory()->create(['price' => 150000]);

        $response = $this->post(route('checkout.store', $product), [
            'customer_name' => 'Asha Gurung',
            'customer_email' => 'asha@example.com',
            'gateway' => 'esewa',
        ]);

        $payment = Payment::sole();
        $fields = $response->assertOk()->viewData('redirect')->fields;

        $response->assertSee('https://rc-epay.esewa.com.np/api/epay/main/v2/form', false);
        $this->assertSame('1500.00', $fields['total_amount']);
        $this->assertSame($payment->uuid, $fields['transaction_uuid']);
        $this->assertSame('EPAYTEST', $fields['product_code']);
        $this->assertSame($this->esewa()->sign($fields, 'total_amount,transaction_uuid,product_code'), $fields['signature']);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
    }

    public function test_successful_callback_completes_the_payment_and_issues_an_invoice(): void
    {
        $payment = Payment::factory()->esewa()->create(['amount' => 150000]);

        $this->get($this->callbackUrl($payment, ['total_amount' => 1500.0]))
            ->assertRedirect(route('payments.show', $payment));

        $payment->refresh();
        $this->assertSame(PaymentStatus::Completed, $payment->status);
        $this->assertSame('000AWEO', $payment->transaction_reference);
        $this->assertTrue($payment->order->isPaid());
        $this->assertSame(150000, $payment->invoice->amount);
        $this->assertSame(sprintf('INV-%s-%06d', now()->format('Y'), $payment->invoice->id), $payment->invoice->number);
        $this->assertSame(sprintf('ORD-%06d', $payment->order->id), $payment->order->number);
    }

    public function test_amounts_formatted_with_thousands_separators_are_accepted(): void
    {
        $payment = Payment::factory()->esewa()->create(['amount' => 150000]);

        $this->get($this->callbackUrl($payment, ['total_amount' => '1,500.0']));

        $this->assertSame(PaymentStatus::Completed, $payment->refresh()->status);
    }

    public function test_tampered_callback_is_rejected(): void
    {
        $payment = Payment::factory()->esewa()->create(['amount' => 150000]);
        $payload = $this->signedPayload($payment, ['status' => 'PENDING']);
        $payload['status'] = 'COMPLETE';

        $this->get(route('payments.callback', ['payment' => $payment, 'data' => base64_encode(json_encode($payload, JSON_PRESERVE_ZERO_FRACTION))]))
            ->assertRedirect(route('payments.show', $payment))
            ->assertSessionHas('error');

        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
        $this->assertNull($payment->invoice);
    }

    public function test_callback_signed_for_another_payment_is_rejected(): void
    {
        $paid = Payment::factory()->esewa()->create(['amount' => 150000]);
        $victim = Payment::factory()->esewa()->create(['amount' => 150000]);

        $this->get(route('payments.callback', [
            'payment' => $victim,
            'data' => base64_encode(json_encode($this->signedPayload($paid), JSON_PRESERVE_ZERO_FRACTION)),
        ]))->assertSessionHas('error');

        $this->assertSame(PaymentStatus::Pending, $victim->refresh()->status);
    }

    public function test_callback_for_a_different_amount_fails_the_payment(): void
    {
        $payment = Payment::factory()->esewa()->create(['amount' => 150000]);

        $this->get($this->callbackUrl($payment, ['total_amount' => 10.0]));

        $payment->refresh();
        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertSame('amount_mismatch', $payment->failure_reason);
        $this->assertNull($payment->invoice);
    }

    public function test_repeated_callbacks_issue_only_one_invoice(): void
    {
        $payment = Payment::factory()->esewa()->create(['amount' => 150000]);
        $url = $this->callbackUrl($payment);

        $this->get($url);
        $this->get($url);

        $this->assertSame(1, $payment->invoice()->count());
    }

    public function test_return_without_data_is_confirmed_with_the_status_api(): void
    {
        Http::fake([
            'rc.esewa.com.np/*' => Http::response(['status' => 'CANCELED', 'total_amount' => 1500.0, 'ref_id' => null]),
        ]);
        $payment = Payment::factory()->esewa()->create(['amount' => 150000]);

        $this->get(route('payments.callback', $payment));

        $this->assertSame(PaymentStatus::Failed, $payment->refresh()->status);
        Http::assertSent(fn ($request): bool => $request['transaction_uuid'] === $payment->uuid
            && $request['total_amount'] === '1500.00'
            && $request['product_code'] === 'EPAYTEST');
    }

    private function esewa(): EsewaGateway
    {
        return app(GatewayManager::class)->gateway('esewa');
    }

    /**
     * Build a callback payload signed the way eSewa signs it.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function signedPayload(Payment $payment, array $overrides = []): array
    {
        $payload = array_merge([
            'transaction_code' => '000AWEO',
            'status' => 'COMPLETE',
            'total_amount' => $payment->amount / 100,
            'transaction_uuid' => $payment->uuid,
            'product_code' => 'EPAYTEST',
            'signed_field_names' => self::SIGNED_RESPONSE_FIELDS,
        ], $overrides);

        $payload['signature'] = $this->esewa()->sign(json_decode(json_encode($payload, JSON_PRESERVE_ZERO_FRACTION), true), self::SIGNED_RESPONSE_FIELDS);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function callbackUrl(Payment $payment, array $overrides = []): string
    {
        return route('payments.callback', [
            'payment' => $payment,
            'data' => base64_encode(json_encode($this->signedPayload($payment, $overrides), JSON_PRESERVE_ZERO_FRACTION)),
        ]);
    }
}

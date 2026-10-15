<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Product;
use App\Payments\Jose\JoseEnvelope;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * HBL has no public sandbox, so these tests play the bank's side with their
 * own key pairs: requests are opened the way PACO would open them, and
 * responses are sealed the way PACO seals them.
 */
class HblPaymentTest extends TestCase
{
    use RefreshDatabase;

    private const API_KEY = 'test-api-key';

    /** @var array<string, array{private: string, public: string}> */
    private static array $keys = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['merchant_signing', 'merchant_decryption', 'paco_signing', 'paco_encryption'] as $name) {
            self::$keys[$name] ??= $this->keyPair();
        }

        config(['payments.gateways.hbl' => array_merge(config('payments.gateways.hbl'), [
            'office_id' => 'DEMOOFFICE',
            'api_key' => self::API_KEY,
            'encryption_key_id' => 'test-kid',
            // Bare base64, the way banks usually hand keys out.
            'merchant_signing_private_key' => $this->bare(self::$keys['merchant_signing']['private']),
            'merchant_decryption_private_key' => self::$keys['merchant_decryption']['private'],
            'paco_encryption_public_key' => $this->bare(self::$keys['paco_encryption']['public']),
            'paco_signing_public_key' => self::$keys['paco_signing']['public'],
        ])]);
    }

    public function test_checkout_sends_an_encrypted_request_and_redirects_to_the_card_page(): void
    {
        Http::fake(['core.demo-paco.2c2p.com/api/1.0/Payment/prePaymentUi' => fn (Request $request) => $this->bankResponse([
            'apiResponse' => ['responseCode' => 'PC-B050000', 'responseDescription' => 'Success'],
            'data' => ['paymentPage' => ['paymentPageURL' => 'https://demo-paco.2c2p.com/payment/form?id=abc']],
        ])]);
        $product = Product::factory()->create(['price' => 150000]);

        $this->post(route('checkout.store', $product), [
            'customer_name' => 'Asha Gurung',
            'customer_email' => 'asha@example.com',
            'gateway' => 'hbl',
        ])->assertRedirect('https://demo-paco.2c2p.com/payment/form?id=abc');

        $payment = Payment::sole();
        $this->assertSame(str_replace('-', '', $payment->uuid), $payment->external_id);

        Http::assertSent(function (Request $request) use ($payment): bool {
            $claims = $this->bank()->open($request->body());
            $body = $claims['request'];

            return $request->hasHeader('Content-Type', 'application/jose; charset=utf-8')
                && $request->hasHeader('CompanyApiKey', self::API_KEY)
                && $claims['iss'] === self::API_KEY
                && $claims['aud'] === 'PacoAudience'
                && $body['officeId'] === 'DEMOOFFICE'
                && $body['orderNo'] === $payment->external_id
                && $body['transactionAmount']['amountText'] === '000000150000'
                && $body['transactionAmount']['currencyCode'] === 'NPR'
                && $body['notificationURLs']['confirmationURL'] === route('payments.callback', $payment)
                && $body['notificationURLs']['backendURL'] === route('payments.hbl.notify');
        });
    }

    public function test_gateway_error_codes_fail_the_checkout_gracefully(): void
    {
        Http::fake(['core.demo-paco.2c2p.com/*' => fn () => $this->bankResponse([
            'apiResponse' => ['responseCode' => 'PC-B050088', 'responseDescription' => 'Invalid or missing API key'],
        ])]);
        $product = Product::factory()->create();

        $this->from(route('checkout.create', $product))
            ->post(route('checkout.store', $product), [
                'customer_name' => 'Asha Gurung',
                'customer_email' => 'asha@example.com',
                'gateway' => 'hbl',
            ])
            ->assertRedirect(route('checkout.create', $product))
            ->assertSessionHas('error');

        $this->assertSame(PaymentStatus::Failed, Payment::sole()->status);
    }

    public function test_return_from_hbl_completes_the_payment_from_the_inquiry_api(): void
    {
        $payment = $this->hblPayment();
        // Older API versions answer with a capitalised "Data" key.
        Http::fake(['core.demo-paco.2c2p.com/api/1.0/Inquiry/transactionList' => fn () => $this->bankResponse([
            'apiResponse' => ['responseCode' => 'PC-B050000'],
            'Data' => [$this->transaction($payment, 'A')],
        ])]);

        $this->get(route('payments.callback', $payment))->assertRedirect(route('payments.show', $payment));

        $payment->refresh();
        $this->assertSame(PaymentStatus::Completed, $payment->status);
        $this->assertSame('INV0000001', $payment->transaction_reference);
        $this->assertNotNull($payment->invoice);
    }

    public function test_hbl_can_post_the_customer_back(): void
    {
        $payment = $this->hblPayment();
        Http::fake(['core.demo-paco.2c2p.com/*' => fn () => $this->bankResponse([
            'apiResponse' => ['responseCode' => 'PC-B050000'],
            'data' => [$this->transaction($payment, 'S')],
        ])]);

        $this->post(route('payments.callback', $payment))->assertRedirect(route('payments.show', $payment));

        $this->assertSame(PaymentStatus::Completed, $payment->refresh()->status);
    }

    public function test_cancelled_card_payment_is_marked_failed(): void
    {
        $payment = $this->hblPayment();
        Http::fake(['core.demo-paco.2c2p.com/*' => fn () => $this->bankResponse([
            'apiResponse' => ['responseCode' => 'PC-B050000'],
            'data' => [$this->transaction($payment, 'C')],
        ])]);

        $this->get(route('payments.callback', $payment));

        $payment->refresh();
        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertSame('hbl_cancelled', $payment->failure_reason);
    }

    public function test_payment_still_on_the_card_page_stays_pending(): void
    {
        $payment = $this->hblPayment();
        Http::fake(['core.demo-paco.2c2p.com/*' => fn () => $this->bankResponse([
            'apiResponse' => ['responseCode' => 'PC-B050000'],
            'data' => [$this->transaction($payment, 'PCPS')],
        ])]);

        $this->get(route('payments.callback', $payment));

        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
    }

    public function test_amount_different_from_the_order_fails_the_payment(): void
    {
        $payment = $this->hblPayment();
        Http::fake(['core.demo-paco.2c2p.com/*' => fn () => $this->bankResponse([
            'apiResponse' => ['responseCode' => 'PC-B050000'],
            'data' => [$this->transaction($payment, 'A', amountText: '000000000100')],
        ])]);

        $this->get(route('payments.callback', $payment));

        $payment->refresh();
        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertSame('amount_mismatch', $payment->failure_reason);
    }

    public function test_response_signed_with_another_key_is_rejected(): void
    {
        $payment = $this->hblPayment();
        $impostor = new JoseEnvelope(
            signingKey: $this->keyPair()['private'],
            encryptionKey: self::$keys['merchant_decryption']['public'],
            decryptionKey: self::$keys['paco_encryption']['private'],
            verificationKey: self::$keys['merchant_signing']['public'],
        );
        Http::fake(['core.demo-paco.2c2p.com/*' => fn () => Http::response($impostor->seal($this->claims([
            'apiResponse' => ['responseCode' => 'PC-B050000'],
            'data' => [$this->transaction($payment, 'A')],
        ])))]);

        $this->get(route('payments.callback', $payment))->assertSessionHas('error');

        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
    }

    public function test_response_meant_for_another_merchant_is_rejected(): void
    {
        $payment = $this->hblPayment();
        Http::fake(['core.demo-paco.2c2p.com/*' => fn () => $this->bankResponse([
            'apiResponse' => ['responseCode' => 'PC-B050000'],
            'data' => [$this->transaction($payment, 'A')],
        ], audience: 'someone-elses-api-key')]);

        $this->get(route('payments.callback', $payment))->assertSessionHas('error');

        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
    }

    public function test_backend_notification_confirms_the_payment(): void
    {
        $payment = $this->hblPayment();
        Http::fake(['core.demo-paco.2c2p.com/*' => fn () => $this->bankResponse([
            'apiResponse' => ['responseCode' => 'PC-B050000'],
            'data' => [$this->transaction($payment, 'A')],
        ])]);

        $notification = $this->bank()->seal($this->claims([
            'data' => ['paymentResult' => ['orderNo' => $payment->external_id]],
        ]));

        $this->call('POST', route('payments.hbl.notify'), content: $notification, server: ['CONTENT_TYPE' => 'application/jose'])
            ->assertOk();

        $this->assertSame(PaymentStatus::Completed, $payment->refresh()->status);
    }

    public function test_notification_that_is_not_from_hbl_is_rejected(): void
    {
        Http::fake();
        $payment = $this->hblPayment();

        $this->call('POST', route('payments.hbl.notify'), content: 'not-a-token')->assertStatus(400);

        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
        Http::assertNothingSent();
    }

    public function test_hbl_is_not_offered_until_it_is_configured(): void
    {
        config(['payments.gateways.hbl.api_key' => null]);
        $product = Product::factory()->create();

        $this->post(route('checkout.store', $product), [
            'customer_name' => 'Asha Gurung',
            'customer_email' => 'asha@example.com',
            'gateway' => 'hbl',
        ])->assertSessionHasErrors('gateway');
    }

    private function hblPayment(): Payment
    {
        $payment = Payment::factory()->create(['gateway' => 'hbl', 'amount' => 150000]);
        $payment->update(['external_id' => str_replace('-', '', $payment->uuid)]);

        return $payment;
    }

    /**
     * @return array<string, mixed>
     */
    private function transaction(Payment $payment, string $status, string $amountText = '000000150000'): array
    {
        return [
            'orderNo' => $payment->external_id,
            'invoiceNo2C2P' => 'INV0000001',
            'paymentStatusInfo' => ['paymentStatus' => $status, 'paymentStep' => 'PA'],
            'transactionAmount' => ['amountText' => $amountText, 'currencyCode' => 'NPR', 'decimalPlaces' => 2],
        ];
    }

    /**
     * The bank's side of the conversation: it signs with its own key and
     * encrypts for the merchant, and opens what the merchant sends it.
     */
    private function bank(): JoseEnvelope
    {
        return new JoseEnvelope(
            signingKey: self::$keys['paco_signing']['private'],
            encryptionKey: self::$keys['merchant_decryption']['public'],
            decryptionKey: self::$keys['paco_encryption']['private'],
            verificationKey: self::$keys['merchant_signing']['public'],
        );
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function bankResponse(array $response, string $audience = self::API_KEY): PromiseInterface
    {
        return Http::response($this->bank()->seal($this->claims($response, $audience)), 200, ['Content-Type' => 'application/jose']);
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function claims(array $response, string $audience = self::API_KEY): array
    {
        return [
            'response' => $response,
            'iss' => 'PacoIssuer',
            'aud' => $audience,
            'iat' => time(),
            'nbf' => time(),
            'exp' => time() + 3600,
        ];
    }

    /**
     * @return array{private: string, public: string}
     */
    private function keyPair(): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);

        return ['private' => $private, 'public' => openssl_pkey_get_details($key)['key']];
    }

    private function bare(string $pem): string
    {
        return preg_replace('/-----[^-]+-----|\s+/', '', $pem);
    }
}

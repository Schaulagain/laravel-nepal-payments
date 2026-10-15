<?php

namespace App\Payments\Gateways;

use App\Enums\PaymentStatus;
use App\Exceptions\Payments\InvalidSignatureException;
use App\Models\Payment;
use App\Payments\CheckoutRedirect;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\GatewayResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * eSewa ePay v2: the browser posts HMAC-SHA256 signed fields to eSewa, and
 * eSewa redirects back with a base64-encoded, signed JSON payload.
 *
 * @see https://developer.esewa.com.np/pages/Epay
 */
class EsewaGateway implements PaymentGateway
{
    /**
     * Fields eSewa must have signed for a callback to be trusted.
     */
    private const REQUIRED_SIGNED_FIELDS = ['status', 'total_amount', 'transaction_uuid', 'product_code'];

    /**
     * @param  array{sandbox: bool, product_code: string, secret_key: string, urls: array<string, array{form: string, status: string}>}  $config
     */
    public function __construct(private array $config) {}

    public function checkout(Payment $payment): CheckoutRedirect
    {
        $fields = [
            'amount' => $payment->amountInRupees(),
            'tax_amount' => '0',
            'product_service_charge' => '0',
            'product_delivery_charge' => '0',
            'total_amount' => $payment->amountInRupees(),
            'transaction_uuid' => $payment->uuid,
            'product_code' => $this->config['product_code'],
            'success_url' => route('payments.callback', $payment),
            'failure_url' => route('payments.callback', $payment),
            'signed_field_names' => 'total_amount,transaction_uuid,product_code',
        ];

        $fields['signature'] = $this->sign($fields, $fields['signed_field_names']);

        return CheckoutRedirect::post($this->url('form'), $fields);
    }

    /**
     * eSewa appends `?data=<base64 JSON>` on success. A return without data
     * (failure or cancellation) is confirmed with the status API instead.
     */
    public function handleCallback(Payment $payment, Request $request): GatewayResult
    {
        if (! $request->filled('data')) {
            return $this->fetchStatus($payment);
        }

        $payload = $this->decodeCallback((string) $request->query('data'));

        if ($payload['transaction_uuid'] !== $payment->uuid || $payload['product_code'] !== $this->config['product_code']) {
            throw new InvalidSignatureException('eSewa callback does not belong to this payment.');
        }

        return new GatewayResult(
            status: $this->mapStatus((string) $payload['status']),
            amount: $this->toPaisa($payload['total_amount']),
            transactionReference: $payload['transaction_code'] ?? null,
            failureReason: 'esewa_'.strtolower((string) $payload['status']),
            response: $payload,
        );
    }

    public function fetchStatus(Payment $payment): GatewayResult
    {
        $response = Http::acceptJson()
            ->get($this->url('status'), [
                'product_code' => $this->config['product_code'],
                'total_amount' => $payment->amountInRupees(),
                'transaction_uuid' => $payment->uuid,
            ])
            ->throw()
            ->json();

        return new GatewayResult(
            status: $this->mapStatus((string) $response['status']),
            amount: $this->toPaisa($response['total_amount'] ?? null),
            transactionReference: $response['ref_id'] ?? null,
            failureReason: 'esewa_'.strtolower((string) $response['status']),
            response: $response,
        );
    }

    /**
     * Build eSewa's signature: base64(HMAC-SHA256("key=value,key=value", secret))
     * over the fields listed in $signedFieldNames, in that order.
     *
     * @param  array<string, mixed>  $data
     */
    public function sign(array $data, string $signedFieldNames): string
    {
        $message = collect(explode(',', $signedFieldNames))
            ->map(fn (string $field): string => $field.'='.$this->stringify($data[$field] ?? ''))
            ->implode(',');

        return base64_encode(hash_hmac('sha256', $message, $this->config['secret_key'], true));
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidSignatureException
     */
    private function decodeCallback(string $data): array
    {
        $json = base64_decode($data, true);
        $payload = $json === false ? null : json_decode($json, true);

        if (! is_array($payload) || ! isset($payload['signature'], $payload['signed_field_names'])) {
            throw new InvalidSignatureException('eSewa callback payload is malformed.');
        }

        $signedFields = explode(',', (string) $payload['signed_field_names']);

        if (array_diff(self::REQUIRED_SIGNED_FIELDS, $signedFields) !== []) {
            throw new InvalidSignatureException('eSewa callback is missing required signed fields.');
        }

        if (! hash_equals($this->sign($payload, (string) $payload['signed_field_names']), (string) $payload['signature'])) {
            throw new InvalidSignatureException('eSewa callback signature is invalid.');
        }

        return $payload;
    }

    /**
     * eSewa signs values exactly as they appear in its JSON. json_decode() turns
     * 1000.0 into a float that PHP would print as "1000", so floats are
     * re-encoded with JSON_PRESERVE_ZERO_FRACTION to keep the original "1000.0".
     */
    private function stringify(mixed $value): string
    {
        return is_float($value) ? json_encode($value, JSON_PRESERVE_ZERO_FRACTION) : (string) $value;
    }

    /**
     * eSewa amounts arrive as numbers (1000.0) or formatted strings ("1,000.0").
     */
    private function toPaisa(mixed $amount): ?int
    {
        if ($amount === null) {
            return null;
        }

        return (int) round((float) str_replace(',', '', (string) $amount) * 100);
    }

    private function mapStatus(string $status): PaymentStatus
    {
        return match ($status) {
            'COMPLETE', 'PARTIAL_REFUND' => PaymentStatus::Completed,
            'FULL_REFUND' => PaymentStatus::Refunded,
            'NOT_FOUND', 'CANCELED' => PaymentStatus::Failed,
            default => PaymentStatus::Pending, // PENDING, AMBIGUOUS: check again later
        };
    }

    private function url(string $type): string
    {
        return $this->config['urls'][$this->config['sandbox'] ? 'sandbox' : 'production'][$type];
    }
}

<?php

namespace App\Payments\Gateways;

use App\Enums\PaymentStatus;
use App\Exceptions\Payments\InvalidSignatureException;
use App\Models\Payment;
use App\Payments\CheckoutRedirect;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\GatewayResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Khalti ePayment (KPG-2): the server initiates a payment and gets a `pidx`,
 * the customer pays on Khalti, and the result is confirmed with the lookup API.
 * Khalti's callback is not signed, so it is never trusted on its own.
 *
 * @see https://docs.khalti.com/khalti-epayment/
 */
class KhaltiGateway implements PaymentGateway
{
    /**
     * @param  array{sandbox: bool, secret_key: ?string, urls: array{sandbox: string, production: string}}  $config
     */
    public function __construct(private array $config) {}

    public function checkout(Payment $payment): CheckoutRedirect
    {
        $order = $payment->order;

        $response = $this->client()
            ->post('epayment/initiate/', [
                'return_url' => route('payments.callback', $payment),
                'website_url' => config('app.url'),
                'amount' => $payment->amount,
                'purchase_order_id' => $payment->uuid,
                'purchase_order_name' => $order->product->name,
                'customer_info' => [
                    'name' => $order->customer_name,
                    'email' => $order->customer_email,
                ],
            ])
            ->throw()
            ->json();

        $payment->update(['external_id' => $response['pidx']]);

        return CheckoutRedirect::to($response['payment_url']);
    }

    public function handleCallback(Payment $payment, Request $request): GatewayResult
    {
        if ($request->query('pidx') !== $payment->external_id) {
            throw new InvalidSignatureException('Khalti callback does not belong to this payment.');
        }

        return $this->fetchStatus($payment);
    }

    public function fetchStatus(Payment $payment): GatewayResult
    {
        if ($payment->external_id === null) {
            return new GatewayResult(PaymentStatus::Failed, failureReason: 'khalti_not_initiated');
        }

        // Expired and cancelled payments come back as HTTP 400 with a status in the body.
        $response = $this->client()->post('epayment/lookup/', ['pidx' => $payment->external_id]);

        if ($response->serverError() || ! isset($response['status'])) {
            $response->throw();
        }

        return new GatewayResult(
            status: $this->mapStatus($response['status']),
            amount: $response['total_amount'] ?? null,
            transactionReference: $response['transaction_id'] ?? null,
            failureReason: 'khalti_'.str_replace(' ', '_', strtolower($response['status'])),
            response: $response->json(),
        );
    }

    private function mapStatus(string $status): PaymentStatus
    {
        return match ($status) {
            'Completed', 'Partially Refunded' => PaymentStatus::Completed,
            'Refunded' => PaymentStatus::Refunded,
            'Expired', 'User canceled', 'Failed' => PaymentStatus::Failed,
            default => PaymentStatus::Pending, // Pending, Initiated: check again later
        };
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->config['urls'][$this->config['sandbox'] ? 'sandbox' : 'production'])
            ->withHeader('Authorization', 'Key '.$this->config['secret_key'])
            ->acceptJson()
            ->asJson();
    }
}

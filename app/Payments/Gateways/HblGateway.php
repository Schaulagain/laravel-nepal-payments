<?php

namespace App\Payments\Gateways;

use App\Enums\PaymentStatus;
use App\Exceptions\Payments\GatewayException;
use App\Exceptions\Payments\InvalidSignatureException;
use App\Models\Payment;
use App\Payments\CheckoutRedirect;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\GatewayResult;
use App\Payments\Jose\InvalidTokenException;
use App\Payments\Jose\JoseEnvelope;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Himalayan Bank (HBL) card payments. HBL's gateway runs on 2C2P's PACO
 * platform: every request and response is a signed, then encrypted, JOSE token.
 *
 * Flow: prePaymentUi returns a hosted card page, the customer pays there, and
 * HBL redirects the browser back and posts a backend notification. Neither is
 * trusted on its own; the result always comes from the transactionList inquiry,
 * which also gives the amount to check against the order.
 *
 * @see https://devzone.2c2p.com/docs/payment-result-notification-1
 */
class HblGateway implements PaymentGateway
{
    private const SUCCESS = 'PC-B050000';

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private array $config) {}

    public function checkout(Payment $payment): CheckoutRedirect
    {
        $orderNo = str_replace('-', '', $payment->uuid);
        $payment->update(['external_id' => $orderNo]);

        $order = $payment->order;
        $amount = $this->amount($payment->amount);
        $returnUrl = route('payments.callback', $payment);

        $response = $this->send('api/1.0/Payment/prePaymentUi', [
            'apiRequest' => $this->apiRequest(),
            'officeId' => $this->config['office_id'],
            'orderNo' => $orderNo,
            'productDescription' => Str::limit($order->product->name, 50, ''),
            'paymentType' => 'CC',
            'paymentCategory' => 'ECOM',
            'storeCardDetails' => ['storeCardFlag' => 'N', 'storedCardUniqueID' => (string) Str::uuid()],
            'installmentPaymentDetails' => ['ippFlag' => 'N', 'installmentPeriod' => 0, 'interestType' => null],
            'mcpFlag' => 'N',
            'request3dsFlag' => $this->config['three_d_secure'] ? 'Y' : 'N',
            'transactionAmount' => $amount,
            'notificationURLs' => [
                'confirmationURL' => $returnUrl,
                'failedURL' => $returnUrl,
                'cancellationURL' => $returnUrl,
                'backendURL' => route('payments.hbl.notify'),
            ],
            'deviceDetails' => [
                'browserIp' => request()->ip(),
                'browser' => '',
                'browserUserAgent' => (string) request()->userAgent(),
                'mobileDeviceFlag' => 'N',
            ],
            'purchaseItems' => [[
                'purchaseItemType' => $order->product->category,
                'referenceNo' => $order->number ?? $orderNo,
                'purchaseItemDescription' => Str::limit($order->product->name, 50, ''),
                'purchaseItemPrice' => $amount,
                'subMerchantID' => '',
                'passengerSeqNo' => 1,
            ]],
            'customFieldList' => [],
        ]);

        $url = Arr::get($response, 'data.paymentPage.paymentPageURL')
            ?? throw new GatewayException('HBL did not return a payment page.');

        return CheckoutRedirect::to($url);
    }

    /**
     * The browser comes back here after paying, failing or cancelling. HBL
     * uses the same redirect for all three, so the outcome is looked up.
     */
    public function handleCallback(Payment $payment, Request $request): GatewayResult
    {
        return $this->fetchStatus($payment);
    }

    public function fetchStatus(Payment $payment): GatewayResult
    {
        if ($payment->external_id === null) {
            return new GatewayResult(PaymentStatus::Failed, failureReason: 'hbl_not_initiated');
        }

        $response = $this->send('api/1.0/Inquiry/transactionList', [
            'apiRequest' => $this->apiRequest(),
            'advSearchParams' => [
                'controllerInternalID' => null,
                'officeId' => [$this->config['office_id']],
                'orderNo' => [$payment->external_id],
                'invoiceNo2C2P' => null,
                'fromDate' => '0001-01-01T00:00:00',
                'toDate' => '0001-01-01T00:00:00',
                'amountFrom' => null,
                'amountTo' => null,
            ],
        ]);

        $transaction = collect(Arr::get($response, 'data', []))
            ->first(fn (array $item): bool => ($item['orderNo'] ?? null) === $payment->external_id);

        // No transaction yet: the customer hasn't submitted the card page.
        if ($transaction === null) {
            return new GatewayResult(PaymentStatus::Pending, response: $response);
        }

        $status = Arr::get($transaction, 'paymentStatusInfo.paymentStatus');

        return new GatewayResult(
            status: $this->mapStatus($status),
            amount: $this->paisa(Arr::get($transaction, 'transactionAmount')),
            transactionReference: $transaction['invoiceNo2C2P'] ?? $transaction['pspReferenceNo'] ?? null,
            failureReason: 'hbl_'.match ($status) {
                'C' => 'cancelled',
                'E' => 'expired',
                'V' => 'voided',
                default => 'rejected',
            },
            response: $transaction,
        );
    }

    /**
     * Read a backend notification and return the order number it is about.
     * The token must decrypt with our key and carry HBL's signature.
     *
     * @throws InvalidSignatureException
     */
    public function notificationOrderNo(string $token): string
    {
        $data = $this->unwrap($this->envelope()->open($token));

        $orderNo = Arr::get($data, 'data.paymentResult.orderNo') ?? Arr::get($data, 'data.orderNo');

        if (! is_string($orderNo) || $orderNo === '') {
            throw new InvalidSignatureException('HBL notification has no order number.');
        }

        return $orderNo;
    }

    /**
     * PACO status codes: https://devzone.2c2p.com/docs/payment-status
     */
    private function mapStatus(?string $status): PaymentStatus
    {
        return match ($status) {
            'A', 'S' => PaymentStatus::Completed, // approved, settled
            'R' => PaymentStatus::Refunded,
            'F', 'C', 'E', 'V' => PaymentStatus::Failed, // rejected, cancelled, expired, voided
            default => PaymentStatus::Pending, // PCPS (on the card page), I (processing), P
        };
    }

    /**
     * Send an encrypted request and return the verified response body.
     *
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     *
     * @throws RequestException
     * @throws GatewayException
     * @throws InvalidSignatureException
     */
    private function send(string $path, array $request): array
    {
        $now = now();

        $token = $this->envelope()->seal([
            'request' => $request,
            'iss' => $this->config['api_key'],
            'aud' => 'PacoAudience',
            'CompanyApiKey' => $this->config['api_key'],
            'iat' => $now->getTimestamp(),
            'nbf' => $now->getTimestamp(),
            'exp' => $now->copy()->addHour()->getTimestamp(),
        ]);

        $body = Http::baseUrl($this->config['urls'][$this->config['sandbox'] ? 'sandbox' : 'production'])
            ->withHeaders([
                'apiKey' => $this->config['api_key'],
                'CompanyApiKey' => $this->config['api_key'],
            ])
            ->accept('application/jose')
            ->withBody($token, 'application/jose; charset=utf-8')
            ->post($path)
            ->throw()
            ->body();

        // Some errors (a wrong API key, for example) come back as plain JSON.
        if (is_array($error = json_decode($body, true))) {
            throw new GatewayException(sprintf(
                'HBL returned %s: %s',
                Arr::get($error, 'apiResponse.responseCode', 'no code'),
                Arr::get($error, 'apiResponse.responseDescription', 'no description'),
            ));
        }

        $response = $this->unwrap($this->envelope()->open($body));

        if (Arr::get($response, 'apiResponse.responseCode') !== self::SUCCESS) {
            throw new GatewayException(sprintf(
                'HBL returned %s: %s',
                Arr::get($response, 'apiResponse.responseCode', 'no code'),
                Arr::get($response, 'apiResponse.responseDescription', 'no description'),
            ));
        }

        return $response;
    }

    /**
     * Check the claims HBL puts on every token and return the message inside.
     *
     * @param  array<string, mixed>  $claims
     * @return array<string, mixed>
     *
     * @throws InvalidTokenException
     */
    private function unwrap(array $claims): array
    {
        $now = now()->getTimestamp();
        $leeway = 60;

        if (($claims['iss'] ?? null) !== 'PacoIssuer' || ($claims['aud'] ?? null) !== $this->config['api_key']) {
            throw new InvalidTokenException('HBL token has the wrong issuer or audience.');
        }

        if (isset($claims['exp']) && $now > $claims['exp'] + $leeway) {
            throw new InvalidTokenException('HBL token has expired.');
        }

        if (isset($claims['nbf']) && $now < $claims['nbf'] - $leeway) {
            throw new InvalidTokenException('HBL token is not valid yet.');
        }

        // Older API versions capitalise some keys ("Data"), so normalise them.
        return $this->lowerFirstKeys($claims['response'] ?? $claims);
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private function lowerFirstKeys(array $value): array
    {
        $normalised = [];

        foreach ($value as $key => $item) {
            $normalised[is_string($key) ? lcfirst($key) : $key] = is_array($item) ? $this->lowerFirstKeys($item) : $item;
        }

        return $normalised;
    }

    /**
     * @return array{amountText: string, currencyCode: string, decimalPlaces: int, amount: float}
     */
    private function amount(int $paisa): array
    {
        return [
            'amountText' => str_pad((string) $paisa, 12, '0', STR_PAD_LEFT),
            'currencyCode' => $this->config['currency'],
            'decimalPlaces' => 2,
            'amount' => $paisa / 100,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $amount
     */
    private function paisa(?array $amount): ?int
    {
        if (! isset($amount['amountText']) || ! ctype_digit((string) $amount['amountText'])) {
            return null;
        }

        $decimals = (int) ($amount['decimalPlaces'] ?? 2);

        return intdiv((int) $amount['amountText'] * 100, 10 ** $decimals);
    }

    /**
     * @return array{requestMessageID: string, requestDateTime: string, language: string}
     */
    private function apiRequest(): array
    {
        return [
            'requestMessageID' => (string) Str::uuid(),
            'requestDateTime' => now()->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'language' => 'en-US',
        ];
    }

    private function envelope(): JoseEnvelope
    {
        foreach (['office_id', 'api_key', 'merchant_signing_private_key', 'merchant_decryption_private_key', 'paco_encryption_public_key', 'paco_signing_public_key'] as $key) {
            if (blank($this->config[$key] ?? null)) {
                throw new GatewayException("HBL is not configured: set payments.gateways.hbl.{$key}.");
            }
        }

        try {
            return new JoseEnvelope(
                signingKey: $this->config['merchant_signing_private_key'],
                encryptionKey: $this->config['paco_encryption_public_key'],
                decryptionKey: $this->config['merchant_decryption_private_key'],
                verificationKey: $this->config['paco_signing_public_key'],
                keyId: $this->config['encryption_key_id'] ?: null,
            );
        } catch (Throwable $exception) {
            throw new GatewayException('HBL keys could not be loaded: '.$exception->getMessage(), previous: $exception);
        }
    }
}

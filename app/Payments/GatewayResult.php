<?php

namespace App\Payments;

use App\Enums\PaymentStatus;

/**
 * A verified answer from a gateway about one payment.
 */
final readonly class GatewayResult
{
    /**
     * @param  int|null  $amount  Amount the gateway says was paid, in paisa.
     * @param  array<string, mixed>  $response  Raw gateway response, kept for auditing.
     */
    public function __construct(
        public PaymentStatus $status,
        public ?int $amount = null,
        public ?string $transactionReference = null,
        public ?string $failureReason = null,
        public array $response = [],
    ) {}
}

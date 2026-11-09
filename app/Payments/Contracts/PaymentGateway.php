<?php

namespace App\Payments\Contracts;

use App\Models\Payment;
use App\Payments\CheckoutRedirect;
use App\Payments\GatewayResult;
use Illuminate\Http\Request;

interface PaymentGateway
{
    /**
     * Start a payment and describe where to send the customer.
     */
    public function checkout(Payment $payment): CheckoutRedirect;

    /**
     * Turn the customer's return from the gateway into a verified result.
     *
     * Implementations must never trust the callback on its own: they either
     * verify its signature or confirm the payment with the gateway's API.
     */
    public function handleCallback(Payment $payment, Request $request): GatewayResult;

    /**
     * Ask the gateway's API for the real status and amount of a payment (a lookup).
     */
    public function lookup(Payment $payment): GatewayResult;
}

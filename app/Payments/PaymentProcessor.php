<?php

namespace App\Payments;

use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Applies a verified gateway result to a payment, exactly once.
 *
 * Callbacks can arrive twice (a refresh, a retry, reconciliation running at the
 * same moment), so the payment row is locked and final states are never changed.
 */
class PaymentProcessor
{
    public function apply(Payment $payment, GatewayResult $result): Payment
    {
        return DB::transaction(function () use ($payment, $result): Payment {
            /** @var Payment $payment */
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status->isFinal()) {
                return $payment;
            }

            match ($result->status) {
                PaymentStatus::Completed => $this->complete($payment, $result),
                PaymentStatus::Failed => $this->fail($payment, $result),
                PaymentStatus::Refunded => $payment->update([
                    'status' => PaymentStatus::Refunded,
                    'gateway_response' => $result->response,
                ]),
                PaymentStatus::Pending => $payment->update(['gateway_response' => $result->response]),
            };

            return $payment;
        });
    }

    private function complete(Payment $payment, GatewayResult $result): void
    {
        if ($result->amount !== null && $result->amount !== $payment->amount) {
            Log::warning('Payment amount mismatch', [
                'payment' => $payment->uuid,
                'expected' => $payment->amount,
                'received' => $result->amount,
            ]);

            $this->fail($payment, $result, 'amount_mismatch');

            return;
        }

        $payment->update([
            'status' => PaymentStatus::Completed,
            'transaction_reference' => $result->transactionReference,
            'gateway_response' => $result->response,
            'completed_at' => now(),
        ]);

        $payment->order->update(['paid_at' => now()]);

        Invoice::create([
            'order_id' => $payment->order_id,
            'payment_id' => $payment->id,
            'amount' => $payment->amount,
            'issued_at' => now(),
        ]);
    }

    private function fail(Payment $payment, GatewayResult $result, ?string $reason = null): void
    {
        $payment->update([
            'status' => PaymentStatus::Failed,
            'failure_reason' => $reason ?? $result->failureReason,
            'gateway_response' => $result->response,
        ]);
    }
}

<?php

namespace App\Console\Commands;

use App\Exceptions\Payments\GatewayException;
use App\Exceptions\Payments\InvalidSignatureException;
use App\Models\Payment;
use App\Payments\GatewayManager;
use App\Payments\PaymentProcessor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

#[Signature('payments:reconcile {--minutes= : Only check payments pending for at least this many minutes}')]
#[Description('Re-check pending payments with their gateway (covers customers who never returned from the gateway)')]
class ReconcilePayments extends Command
{
    public function handle(GatewayManager $gateways, PaymentProcessor $processor): int
    {
        $minutes = (int) ($this->option('minutes') ?? config('payments.reconcile_after_minutes'));

        $payments = Payment::query()
            ->pending()
            ->where('created_at', '<=', now()->subMinutes($minutes))
            ->get();

        foreach ($payments as $payment) {
            try {
                $result = $gateways->gateway($payment->gateway)->fetchStatus($payment);
                $payment = $processor->apply($payment, $result);
                $this->line("{$payment->uuid} ({$payment->gateway}): {$payment->status->value}");
            } catch (ConnectionException|RequestException|GatewayException|InvalidSignatureException $exception) {
                $this->warn("{$payment->uuid} ({$payment->gateway}): gateway error, will retry. {$exception->getMessage()}");
            }
        }

        $this->info("Checked {$payments->count()} pending payment(s).");

        return self::SUCCESS;
    }
}

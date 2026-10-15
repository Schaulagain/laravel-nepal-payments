<?php

namespace App\Http\Controllers;

use App\Exceptions\Payments\GatewayException;
use App\Exceptions\Payments\InvalidSignatureException;
use App\Models\Payment;
use App\Payments\GatewayManager;
use App\Payments\PaymentProcessor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PaymentController extends Controller
{
    /**
     * Where every gateway sends the customer back to, whether they paid or not.
     */
    public function callback(Request $request, Payment $payment, GatewayManager $gateways, PaymentProcessor $processor): RedirectResponse
    {
        try {
            $result = $gateways->gateway($payment->gateway)->handleCallback($payment, $request);
            $processor->apply($payment, $result);
        } catch (InvalidSignatureException $exception) {
            Log::warning('Rejected payment callback', ['payment' => $payment->uuid, 'reason' => $exception->getMessage()]);

            return to_route('payments.show', $payment)->with('error', 'We could not verify this payment response.');
        } catch (ConnectionException|RequestException|GatewayException) {
            return to_route('payments.show', $payment)->with('error', 'We could not reach the gateway yet. Your payment will be confirmed automatically.');
        }

        return to_route('payments.show', $payment);
    }

    public function show(Payment $payment): View
    {
        return view('payments.show', [
            'payment' => $payment->load('order.product', 'invoice'),
        ]);
    }

    public function invoice(Payment $payment): View
    {
        abort_unless($payment->invoice()->exists(), 404);

        return view('payments.invoice', [
            'payment' => $payment->load('order.product', 'invoice'),
        ]);
    }
}

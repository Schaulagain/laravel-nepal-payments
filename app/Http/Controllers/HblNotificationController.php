<?php

namespace App\Http\Controllers;

use App\Exceptions\Payments\GatewayException;
use App\Exceptions\Payments\InvalidSignatureException;
use App\Models\Payment;
use App\Payments\GatewayManager;
use App\Payments\Gateways\HblGateway;
use App\Payments\PaymentProcessor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * HBL's server-to-server notification after a card payment finishes. It
 * arrives even when the customer closes the browser before being redirected.
 */
class HblNotificationController extends Controller
{
    public function __invoke(Request $request, GatewayManager $gateways, PaymentProcessor $processor): Response
    {
        /** @var HblGateway $hbl */
        $hbl = $gateways->gateway('hbl');

        try {
            $orderNo = $hbl->notificationOrderNo($request->getContent());
        } catch (InvalidSignatureException|GatewayException $exception) {
            Log::warning('Rejected HBL notification', ['reason' => $exception->getMessage()]);

            return response('', 400);
        }

        $payment = Payment::query()->where('gateway', 'hbl')->where('external_id', $orderNo)->first();

        if ($payment === null) {
            Log::warning('HBL notification for an unknown order', ['order_no' => $orderNo]);

            return response('', 404);
        }

        // The notification only tells us which payment changed; the result
        // itself comes from the inquiry API, like every other HBL check.
        try {
            $processor->apply($payment, $hbl->fetchStatus($payment));
        } catch (ConnectionException|RequestException|GatewayException|InvalidSignatureException $exception) {
            Log::error('HBL notification could not be confirmed', ['payment' => $payment->uuid, 'error' => $exception->getMessage()]);

            return response('', 503);
        }

        return response('', 200);
    }
}

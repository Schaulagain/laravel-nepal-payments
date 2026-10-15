<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Exceptions\Payments\GatewayException;
use App\Exceptions\Payments\InvalidSignatureException;
use App\Models\Product;
use App\Payments\GatewayManager;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(private GatewayManager $gateways) {}

    public function create(Product $product): View
    {
        return view('checkout.create', [
            'product' => $product,
            'gateways' => $this->gateways->all(),
            'available' => $this->gateways->available(),
        ]);
    }

    public function store(Request $request, Product $product): View|RedirectResponse
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_email' => ['required', 'email', 'max:255'],
            'gateway' => ['required', Rule::in(array_keys($this->gateways->available()))],
        ]);

        $payment = DB::transaction(function () use ($product, $validated) {
            $order = $product->orders()->create([
                'customer_name' => $validated['customer_name'],
                'customer_email' => $validated['customer_email'],
                'amount' => $product->price,
            ]);

            return $order->payments()->create([
                'gateway' => $validated['gateway'],
                'amount' => $order->amount,
            ]);
        });

        try {
            $redirect = $this->gateways->gateway($payment->gateway)->checkout($payment);
        } catch (ConnectionException|RequestException|GatewayException|InvalidSignatureException $exception) {
            Log::error('Payment checkout failed', ['payment' => $payment->uuid, 'error' => $exception->getMessage()]);
            $payment->update(['status' => PaymentStatus::Failed, 'failure_reason' => 'checkout_error']);

            return back()->withInput()->with('error', 'We could not reach the payment gateway. Please try again.');
        }

        if ($redirect->isPost()) {
            return view('checkout.redirect', ['redirect' => $redirect]);
        }

        return redirect()->away($redirect->url);
    }
}

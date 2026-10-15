@extends('layouts.app', ['title' => 'Payment'])

@section('content')
    @php($status = $payment->status->value)

    <div class="card">
        <h1>
            @switch($status)
                @case('completed') Payment received @break
                @case('pending') Payment pending @break
                @case('refunded') Payment refunded @break
                @default Payment not completed
            @endswitch
        </h1>
        <span class="status status-{{ $status }}">{{ ucfirst($status) }}</span>

        <dl>
            <dt>Order</dt><dd>{{ $payment->order->number }}</dd>
            <dt>Product</dt><dd>{{ $payment->order->product->name }}</dd>
            <dt>Amount</dt><dd>Rs {{ number_format($payment->amount / 100, 2) }}</dd>
            <dt>Gateway</dt><dd>{{ config("payments.gateways.{$payment->gateway}.label") }}</dd>
            @if ($payment->transaction_reference)
                <dt>Reference</dt><dd>{{ $payment->transaction_reference }}</dd>
            @endif
            @if ($payment->failure_reason && $status === 'failed')
                <dt>Reason</dt><dd>{{ str_replace('_', ' ', $payment->failure_reason) }}</dd>
            @endif
        </dl>

        @if ($payment->invoice)
            <a class="btn" href="{{ route('payments.invoice', $payment) }}">View invoice {{ $payment->invoice->number }}</a>
        @elseif ($status === 'pending')
            <p class="muted">We're waiting for the gateway to confirm. This page will show the result once it does.</p>
        @else
            <a class="btn" href="{{ route('checkout.create', $payment->order->product) }}">Try again</a>
        @endif
        <a class="btn secondary" href="{{ route('store.index') }}">Back to store</a>
    </div>
@endsection

@extends('layouts.app', ['title' => 'Payment'])

@php
    $status = $payment->status->value;
    $look = [
        'completed' => ['check', 'bg-emerald-100 text-emerald-700', 'Payment received', 'Thanks! Your order is paid and the invoice is ready.'],
        'pending' => ['clock', 'bg-amber-100 text-amber-700', 'Waiting for confirmation', "We haven't heard back from the gateway yet. This page refreshes by itself."],
        'refunded' => ['undo', 'bg-sky-100 text-sky-700', 'Payment refunded', 'This payment was refunded by the gateway.'],
        'failed' => ['x', 'bg-red-100 text-red-700', 'Payment not completed', 'No money was taken for this order. You can try again.'],
    ][$status];
    $reasons = [
        'amount_mismatch' => "The amount the gateway reported doesn't match the order.",
        'checkout_error' => "We couldn't reach the gateway to start the payment.",
        'khalti_user_canceled' => 'The payment was cancelled on Khalti.',
        'khalti_expired' => 'The Khalti payment link expired.',
        'hbl_cancelled' => 'The card payment was cancelled.',
        'hbl_expired' => 'The card payment page expired.',
        'hbl_rejected' => 'The bank declined the card payment.',
    ];
@endphp

@if ($status === 'pending')
    @push('head')
        <meta http-equiv="refresh" content="5">
    @endpush
@endif

@section('content')
    <div class="mx-auto max-w-lg">
        <div class="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-xs">
            <div class="px-6 pt-8 pb-6 text-center sm:px-8">
                <span class="mx-auto flex size-14 items-center justify-center rounded-full {{ $look[1] }}">
                    <x-icon :name="$look[0]" @class(['size-7', 'animate-pulse' => $status === 'pending']) />
                </span>
                <h1 class="mt-5 text-2xl font-semibold tracking-tight">{{ $look[2] }}</h1>
                <p class="mt-2 text-sm text-stone-500">{{ $look[3] }}</p>
                <x-money :paisa="$payment->amount" class="mt-6 block text-4xl font-semibold tracking-tight" />
            </div>

            <dl class="divide-y divide-stone-100 border-t border-stone-100 text-sm">
                @foreach (array_filter([
                    'Order' => $payment->order->number,
                    'Product' => $payment->order->product->name,
                    'Method' => config("payments.gateways.{$payment->gateway}.label"),
                    'Reference' => $payment->transaction_reference,
                    'Date' => ($payment->completed_at ?? $payment->created_at)->format('j M Y, g:i a'),
                    'Reason' => $status === 'failed' ? ($reasons[$payment->failure_reason] ?? str_replace('_', ' ', (string) $payment->failure_reason)) : null,
                ]) as $label => $value)
                    <div class="flex justify-between gap-6 px-6 py-3 sm:px-8">
                        <dt class="text-stone-500">{{ $label }}</dt>
                        <dd class="flex items-center gap-2 text-right font-medium break-all">
                            @if ($label === 'Method')
                                <x-gateway-mark :gateway="$payment->gateway" size="sm" />
                            @endif
                            {{ $value }}
                        </dd>
                    </div>
                @endforeach
            </dl>

            <div class="flex flex-col gap-3 border-t border-stone-100 bg-stone-50/60 px-6 py-5 sm:flex-row sm:px-8">
                @if ($payment->invoice)
                    <a href="{{ route('payments.invoice', $payment) }}" class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg bg-stone-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-600">
                        <x-icon name="receipt" class="size-4" />
                        View invoice
                    </a>
                @elseif ($status === 'failed')
                    <a href="{{ route('checkout.create', $payment->order->product) }}" class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg bg-stone-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-600">
                        <x-icon name="refresh" class="size-4" />
                        Try again
                    </a>
                @endif
                <a href="{{ route('store.index') }}" class="inline-flex flex-1 items-center justify-center rounded-lg border border-stone-300 bg-white px-4 py-2.5 text-sm font-medium hover:border-stone-400">Back to products</a>
            </div>
        </div>
    </div>
@endsection

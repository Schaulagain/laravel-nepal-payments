@extends('layouts.app', ['title' => 'Invoice '.$payment->invoice->number])

@section('content')
    <div class="card">
        <div class="tag">Invoice</div>
        <h1>{{ $payment->invoice->number }}</h1>

        <dl>
            <dt>Issued</dt><dd>{{ $payment->invoice->issued_at->format('j M Y, H:i') }}</dd>
            <dt>Billed to</dt><dd>{{ $payment->order->customer_name }} ({{ $payment->order->customer_email }})</dd>
            <dt>Order</dt><dd>{{ $payment->order->number }}</dd>
            <dt>Paid via</dt><dd>{{ config("payments.gateways.{$payment->gateway}.label") }} · {{ $payment->transaction_reference }}</dd>
        </dl>

        <table>
            <thead><tr><th>Item</th><th>Amount</th></tr></thead>
            <tbody><tr><td>{{ $payment->order->product->name }}</td><td>Rs {{ number_format($payment->invoice->amount / 100, 2) }}</td></tr></tbody>
            <tfoot><tr><th>Total paid</th><th>Rs {{ number_format($payment->invoice->amount / 100, 2) }}</th></tr></tfoot>
        </table>

        <a class="btn secondary" href="{{ route('store.index') }}">Back to store</a>
    </div>
@endsection

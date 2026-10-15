@extends('layouts.app', ['title' => 'Invoice '.$payment->invoice->number])

@section('content')
    @php($invoice = $payment->invoice)
    @php($order = $payment->order)

    <div class="no-print mx-auto mb-6 flex max-w-3xl items-center justify-between">
        <a href="{{ route('payments.show', $payment) }}" class="inline-flex items-center gap-1.5 text-sm text-stone-500 hover:text-stone-900">
            <x-icon name="arrow-left" class="size-4" />
            Back to payment
        </a>
        <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 rounded-lg border border-stone-300 bg-white px-3.5 py-2 text-sm font-medium hover:border-stone-400">
            <x-icon name="printer" class="size-4" />
            Print or save as PDF
        </button>
    </div>

    <article class="mx-auto max-w-3xl rounded-2xl border border-stone-200 bg-white p-8 shadow-xs sm:p-12 print:rounded-none print:border-0 print:p-0 print:shadow-none">
        <header class="flex flex-col justify-between gap-6 sm:flex-row">
            <div class="flex items-center gap-2.5 font-semibold tracking-tight">
                <span class="inline-flex size-9 items-center justify-center rounded-lg bg-stone-900 text-white">
                    <x-icon name="cloud" class="size-5" />
                </span>
                Cloud Store
            </div>
            <div class="sm:text-right">
                <p class="text-xs font-medium tracking-wider text-stone-500 uppercase">Invoice</p>
                <p class="mt-1 text-xl font-semibold tabular-nums">{{ $invoice->number }}</p>
            </div>
        </header>

        <div class="mt-10 grid gap-6 text-sm sm:grid-cols-3">
            <div>
                <p class="text-stone-500">Billed to</p>
                <p class="mt-1 font-medium">{{ $order->customer_name }}</p>
                <p class="text-stone-600">{{ $order->customer_email }}</p>
            </div>
            <div>
                <p class="text-stone-500">Issued</p>
                <p class="mt-1 font-medium">{{ $invoice->issued_at->format('j F Y') }}</p>
                <p class="text-stone-600">Order {{ $order->number }}</p>
            </div>
            <div>
                <p class="text-stone-500">Paid with</p>
                <p class="mt-1 font-medium">{{ config("payments.gateways.{$payment->gateway}.label") }}</p>
                <p class="break-all text-stone-600">{{ $payment->transaction_reference }}</p>
            </div>
        </div>

        <table class="mt-10 w-full text-sm">
            <thead>
                <tr class="border-b border-stone-200 text-left text-stone-500">
                    <th class="pb-3 font-medium">Item</th>
                    <th class="pb-3 text-right font-medium">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr class="border-b border-stone-100">
                    <td class="py-4">
                        <p class="font-medium">{{ $order->product->name }}</p>
                        <p class="text-stone-500">{{ $order->product->description }}</p>
                    </td>
                    <td class="py-4 text-right align-top"><x-money :paisa="$invoice->amount" /></td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td class="pt-5 font-semibold">Total paid</td>
                    <td class="pt-5 text-right text-lg font-semibold"><x-money :paisa="$invoice->amount" /></td>
                </tr>
            </tfoot>
        </table>

        <footer class="mt-12 flex items-center justify-between border-t border-stone-100 pt-6 text-xs text-stone-500">
            <p>Demo invoice from a test store. No real money changed hands.</p>
            <span class="rotate-[-6deg] rounded-md border-2 border-emerald-600 px-2.5 py-1 text-sm font-bold tracking-widest text-emerald-700 uppercase">Paid</span>
        </footer>
    </article>
@endsection

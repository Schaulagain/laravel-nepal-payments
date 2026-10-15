@extends('layouts.app', ['title' => 'Products'])

@section('content')
    <section class="max-w-2xl">
        <p class="text-sm font-medium text-indigo-600">Cloud products</p>
        <h1 class="mt-2 text-4xl font-semibold tracking-tight text-balance sm:text-5xl">Servers, domains and email, paid for in rupees.</h1>
        <p class="mt-4 text-lg text-stone-600 text-pretty">
            Pick a product and pay with eSewa, Khalti or a card through Himalayan Bank.
            Everything runs against the gateways' test systems.
        </p>
        <div class="mt-6 flex flex-wrap items-center gap-2">
            @foreach (['esewa' => 'eSewa', 'khalti' => 'Khalti', 'hbl' => 'HBL card'] as $gateway => $label)
                <span class="inline-flex items-center gap-2 rounded-full border border-stone-200 bg-white py-1 pr-3 pl-1 text-sm text-stone-700">
                    <x-gateway-mark :gateway="$gateway" size="sm" class="rounded-full" />
                    {{ $label }}
                </span>
            @endforeach
        </div>
    </section>

    <section class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($products as $product)
            <article class="group flex flex-col rounded-2xl border border-stone-200 bg-white p-6 shadow-xs transition hover:-translate-y-0.5 hover:border-stone-300 hover:shadow-md">
                <div class="flex items-center justify-between">
                    <span class="inline-flex size-11 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                        <x-icon :name="$product->category" class="size-5.5" />
                    </span>
                    <span class="rounded-full bg-stone-100 px-2.5 py-1 text-xs font-medium tracking-wide text-stone-600 uppercase">{{ $product->category }}</span>
                </div>
                <h2 class="mt-5 text-lg font-semibold">{{ $product->name }}</h2>
                <p class="mt-1.5 flex-1 text-sm leading-relaxed text-stone-600">{{ $product->description }}</p>
                <div class="mt-6 flex items-end justify-between gap-4">
                    <x-money :paisa="$product->price" class="text-2xl font-semibold tracking-tight" />
                    <a href="{{ route('checkout.create', $product) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-stone-900 px-4 py-2 text-sm font-medium text-white transition group-hover:bg-indigo-600">
                        Buy
                        <x-icon name="arrow-right" class="size-4" />
                    </a>
                </div>
            </article>
        @endforeach
    </section>

    <section class="mt-16 rounded-2xl border border-stone-200 bg-white p-6 sm:p-8">
        <h2 class="text-lg font-semibold">How a payment is checked</h2>
        <ol class="mt-6 grid gap-6 sm:grid-cols-3">
            @foreach ([
                ['wallet', 'You pay on the gateway', 'You leave this site and pay on eSewa, Khalti or HBL\'s card page.'],
                ['shield', 'The app checks with the gateway', 'Coming back isn\'t enough. The app checks the signature or asks the gateway what happened, and compares the amount.'],
                ['receipt', 'Then you get an invoice', 'Only a confirmed payment marks the order as paid. It happens once, even if the gateway calls back twice.'],
            ] as [$icon, $heading, $text])
                <li class="flex gap-4">
                    <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-full bg-stone-100 text-stone-700">
                        <x-icon :name="$icon" class="size-4.5" />
                    </span>
                    <div>
                        <h3 class="font-medium">{{ $heading }}</h3>
                        <p class="mt-1 text-sm leading-relaxed text-stone-600">{{ $text }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </section>
@endsection

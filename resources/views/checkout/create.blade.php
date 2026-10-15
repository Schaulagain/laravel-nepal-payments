@extends('layouts.app', ['title' => 'Checkout'])

@section('content')
    <a href="{{ route('store.index') }}" class="inline-flex items-center gap-1.5 text-sm text-stone-500 hover:text-stone-900">
        <x-icon name="arrow-left" class="size-4" />
        All products
    </a>
    <h1 class="mt-4 text-3xl font-semibold tracking-tight">Checkout</h1>

    <div class="mt-8 grid items-start gap-8 lg:grid-cols-[1fr_380px]">
        <form method="POST" action="{{ route('checkout.store', $product) }}" class="space-y-6">
            @csrf

            <fieldset class="rounded-2xl border border-stone-200 bg-white p-6">
                <legend class="sr-only">Your details</legend>
                <h2 class="font-semibold">Your details</h2>
                <p class="mt-1 text-sm text-stone-500">The invoice goes to this name and email.</p>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    @foreach (['customer_name' => ['Full name', 'text', 'name'], 'customer_email' => ['Email', 'email', 'email']] as $field => [$label, $type, $autocomplete])
                        <div>
                            <label for="{{ $field }}" class="block text-sm font-medium text-stone-700">{{ $label }}</label>
                            <input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" value="{{ old($field) }}" autocomplete="{{ $autocomplete }}" required
                                @class([
                                    'mt-1.5 block w-full rounded-lg border bg-white px-3.5 py-2.5 text-sm shadow-xs outline-none transition focus:ring-4',
                                    'border-stone-300 focus:border-indigo-500 focus:ring-indigo-100' => ! $errors->has($field),
                                    'border-red-400 focus:border-red-500 focus:ring-red-100' => $errors->has($field),
                                ])>
                            @error($field) <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>
            </fieldset>

            <fieldset class="rounded-2xl border border-stone-200 bg-white p-6">
                <legend class="sr-only">Payment method</legend>
                <h2 class="font-semibold">Payment method</h2>
                <p class="mt-1 text-sm text-stone-500">You'll finish paying on the gateway's own page.</p>

                @php($selected = old('gateway', array_key_first($available)))
                <div class="mt-5 grid gap-3">
                    @foreach ($gateways as $name => $label)
                        @php($enabled = array_key_exists($name, $available))
                        <label @class([
                            'group/gateway flex items-center gap-4 rounded-xl border p-4 transition',
                            'cursor-pointer border-stone-200 hover:border-stone-300 has-checked:border-indigo-500 has-checked:bg-indigo-50/50 has-checked:ring-4 has-checked:ring-indigo-100' => $enabled,
                            'cursor-not-allowed border-dashed border-stone-200 opacity-60' => ! $enabled,
                        ])>
                            <input type="radio" name="gateway" value="{{ $name }}" class="sr-only" @checked($enabled && $selected === $name) @disabled(! $enabled)>
                            <x-gateway-mark :gateway="$name" />
                            <span class="flex-1">
                                <span class="flex flex-wrap items-center gap-x-2 font-medium">
                                    {{ $label }}
                                    @unless ($enabled)
                                        <span class="rounded-full bg-stone-100 px-2 py-0.5 text-xs font-medium text-stone-500">Not set up</span>
                                    @endunless
                                </span>
                                <span class="block text-sm text-stone-500">{{ config("payments.gateways.{$name}.description") }}</span>
                            </span>
                            @if ($enabled)
                                <span class="flex size-5 items-center justify-center rounded-full border border-stone-300 bg-white group-has-checked/gateway:border-indigo-600 group-has-checked/gateway:bg-indigo-600">
                                    <span class="size-2 rounded-full bg-white opacity-0 group-has-checked/gateway:opacity-100"></span>
                                </span>
                            @endif
                        </label>
                    @endforeach
                </div>
                @error('gateway') <p class="mt-3 text-sm text-red-600">{{ $message }}</p> @enderror
            </fieldset>

            <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl bg-stone-900 px-5 py-3.5 font-medium text-white shadow-sm transition hover:bg-indigo-600 focus:ring-4 focus:ring-indigo-200 focus:outline-none">
                <x-icon name="lock" class="size-4.5" />
                <span>Pay <x-money :paisa="$product->price" /></span>
            </button>
        </form>

        <aside class="order-first rounded-2xl border border-stone-200 bg-white p-6 lg:order-none lg:sticky lg:top-24">
            <h2 class="font-semibold">Order summary</h2>
            <div class="mt-5 flex gap-4">
                <span class="inline-flex size-12 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                    <x-icon :name="$product->category" class="size-6" />
                </span>
                <div>
                    <p class="font-medium">{{ $product->name }}</p>
                    <p class="mt-0.5 text-sm text-stone-500">{{ $product->description }}</p>
                </div>
            </div>

            <dl class="mt-6 space-y-3 border-t border-stone-100 pt-5 text-sm">
                <div class="flex justify-between"><dt class="text-stone-500">Subtotal</dt><dd><x-money :paisa="$product->price" /></dd></div>
                <div class="flex justify-between"><dt class="text-stone-500">Fees</dt><dd>None</dd></div>
                <div class="flex justify-between border-t border-stone-100 pt-3 text-base font-semibold"><dt>Total</dt><dd><x-money :paisa="$product->price" /></dd></div>
            </dl>

            <p class="mt-6 flex gap-2.5 rounded-xl bg-stone-50 p-3.5 text-xs leading-relaxed text-stone-600">
                <x-icon name="shield" class="size-4 shrink-0 text-emerald-600" />
                The order is only marked paid after the gateway confirms the payment and the amount matches.
            </p>
        </aside>
    </div>
@endsection

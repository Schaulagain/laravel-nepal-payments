@extends('layouts.app', ['title' => 'Checkout'])

@section('content')
    <h1>Checkout</h1>
    <p class="muted">{{ $product->name }} · Rs {{ number_format($product->price / 100, 2) }}</p>

    <form class="card" method="POST" action="{{ route('checkout.store', $product) }}">
        @csrf

        <label for="customer_name">Name</label>
        <input id="customer_name" type="text" name="customer_name" value="{{ old('customer_name') }}" required>
        @error('customer_name') <div class="error">{{ $message }}</div> @enderror

        <label for="customer_email">Email</label>
        <input id="customer_email" type="email" name="customer_email" value="{{ old('customer_email') }}" required>
        @error('customer_email') <div class="error">{{ $message }}</div> @enderror

        <label>Pay with</label>
        <div class="gateways">
            @foreach ($gateways as $name => $label)
                <label><input type="radio" name="gateway" value="{{ $name }}" @checked(old('gateway', 'esewa') === $name)> {{ $label }}</label>
            @endforeach
        </div>
        @error('gateway') <div class="error">{{ $message }}</div> @enderror

        <p><button class="btn" type="submit">Continue to payment</button></p>
    </form>
@endsection

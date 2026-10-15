@extends('layouts.app', ['title' => 'Store'])

@section('content')
    <h1>Cloud products</h1>
    <p class="muted">A demo store for testing eSewa and Khalti payments end to end.</p>

    <div class="grid">
        @foreach ($products as $product)
            <div class="card">
                <div class="tag">{{ $product->category }}</div>
                <h2>{{ $product->name }}</h2>
                <p class="muted">{{ $product->description }}</p>
                <div class="price">Rs {{ number_format($product->price / 100, 2) }}</div>
                <a class="btn" href="{{ route('checkout.create', $product) }}">Buy now</a>
            </div>
        @endforeach
    </div>
@endsection

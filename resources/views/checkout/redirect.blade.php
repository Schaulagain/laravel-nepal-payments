@extends('layouts.app', ['title' => 'Redirecting'])

@section('content')
    <div class="card">
        <h1>Redirecting to the payment gateway…</h1>
        <form id="gateway-form" method="POST" action="{{ $redirect->url }}">
            @foreach ($redirect->fields as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <noscript><button class="btn" type="submit">Continue</button></noscript>
        </form>
    </div>
    <script>document.getElementById('gateway-form').submit();</script>
@endsection

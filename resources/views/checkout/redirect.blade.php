@extends('layouts.app', ['title' => 'Redirecting'])

@section('content')
    <div class="mx-auto max-w-md rounded-2xl border border-stone-200 bg-white p-8 text-center">
        <span class="mx-auto flex size-12 items-center justify-center">
            <span class="size-10 animate-spin rounded-full border-4 border-stone-200 border-t-indigo-600"></span>
        </span>
        <h1 class="mt-5 text-xl font-semibold">Taking you to the payment page</h1>
        <p class="mt-2 text-sm text-stone-500">This should only take a second.</p>

        <form id="gateway-form" method="POST" action="{{ $redirect->url }}" class="mt-6">
            @foreach ($redirect->fields as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <button type="submit" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">Not moving? Continue</button>
        </form>
    </div>
    <script>document.getElementById('gateway-form').submit();</script>
@endsection
